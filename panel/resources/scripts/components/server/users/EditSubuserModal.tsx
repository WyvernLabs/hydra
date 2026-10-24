import React, { useContext, useEffect, useRef } from 'react';
import { Subuser } from '@/state/server/subusers';
import { Form, Formik, useFormikContext } from 'formik';
import { array, boolean, object, string } from 'yup';
import Field from '@/components/elements/Field';
import { Actions, useStoreActions, useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import createOrUpdateSubuser from '@/api/server/users/createOrUpdateSubuser';
import { ServerContext } from '@/state/server';
import FlashMessageRender from '@/components/FlashMessageRender';
import Can from '@/components/elements/Can';
import { usePermissions } from '@/plugins/usePermissions';
import tw from 'twin.macro';
import Button from '@/components/elements/Button';
import asModal from '@/hoc/asModal';
import PermissionList from '@/components/server/users/PermissionList';
import Label from '@/components/elements/Label';
import Select from '@/components/elements/Select';
import { useServerRoles } from '@/api/server/users/roles';
import ModalContext from '@/context/ModalContext';
import { useTranslation } from 'react-i18next';
import FolderAccessBox from '@/components/server/users/FolderAccessBox';
import { coversPaths, normalizePath } from '@/plugins/useAllowedPaths';

type Props = {
    subuser?: Subuser;
};

interface Values {
    username: string;
    // Hydra: id of the role, empty for own permissions.
    role: string;
    permissions: string[];
    restrictFolders: boolean;
    allowedPaths: string[];
}

// Hydra: the folders from the form, cleaned up; "/" means the whole server.
const cleanPaths = (paths: string[]): string[] =>
    Array.from(new Set(paths.filter((p) => p.trim().length > 0).map((p) => normalizePath(p.trim())))).filter(
        (p) => p !== '/'
    );

// Hydra: a role brings its permissions; own permissions are ticked one by one.
const RolePicker = ({ isEditable }: { isEditable: boolean }) => {
    const { t } = useTranslation('client/server');
    const { data: roles } = useServerRoles();
    const { values, setFieldValue } = useFormikContext<Values>();

    if (!roles || roles.length === 0) return null;

    return (
        <div css={tw`mt-6`}>
            <Label htmlFor={'subuserRole'}>{t('users.roles.pick')}</Label>
            <Select
                id={'subuserRole'}
                value={values.role}
                disabled={!isEditable}
                onChange={(event) => {
                    const role = roles.find((item) => String(item.id) === event.currentTarget.value);
                    setFieldValue('role', event.currentTarget.value);
                    if (role) setFieldValue('permissions', role.permissions);
                }}
            >
                <option value={''}>{t('users.roles.own')}</option>
                {roles.map((role) => (
                    <option key={role.id} value={String(role.id)}>
                        {role.name}
                    </option>
                ))}
            </Select>
            <p css={tw`mt-1 text-xs text-neutral-400`}>{t('users.roles.pick_help')}</p>
        </div>
    );
};

const RolePermissions = ({ isEditable }: { isEditable: boolean }) => {
    const { values } = useFormikContext<Values>();

    return <PermissionList isEditable={isEditable && !values.role} />;
};

const EditSubuserModal = ({ subuser }: Props) => {
    const ref = useRef<HTMLHeadingElement>(null);
    const { t } = useTranslation('client/server');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const appendSubuser = ServerContext.useStoreActions((actions) => actions.subusers.appendSubuser);
    const { clearFlashes, clearAndAddHttpError } = useStoreActions(
        (actions: Actions<ApplicationStore>) => actions.flashes
    );
    const { dismiss, setPropOverrides } = useContext(ModalContext);

    const isRootAdmin = useStoreState((state) => state.user.data!.serverAccess);
    // The currently logged in user's permissions. We're going to filter out any permissions
    // that they should not need.
    const loggedInPermissions = ServerContext.useStoreState((state) => state.server.permissions);
    const ownPaths = ServerContext.useStoreState((state) => state.server.allowedPaths);
    const [canEditUser] = usePermissions(subuser ? ['user.update'] : ['user.create']);

    const submit = (values: Values) => {
        setPropOverrides({ showSpinnerOverlay: true });
        clearFlashes('user:edit');

        createOrUpdateSubuser(
            uuid,
            {
                username: values.username,
                permissions: values.permissions,
                role: values.role ? Number(values.role) : null,
                allowedPaths: values.restrictFolders ? cleanPaths(values.allowedPaths) : [],
            },
            subuser
        )
            .then((subuser) => {
                appendSubuser(subuser);
                dismiss();
            })
            .catch((error) => {
                console.error(error);
                setPropOverrides(null);
                clearAndAddHttpError({ key: 'user:edit', error });

                if (ref.current) {
                    ref.current.scrollIntoView();
                }
            });
    };

    useEffect(
        () => () => {
            clearFlashes('user:edit');
        },
        []
    );

    return (
        <Formik
            onSubmit={submit}
            initialValues={
                {
                    username: subuser?.username || '',
                    role: subuser?.roleId ? String(subuser.roleId) : '',
                    permissions: subuser?.permissions || [],
                    restrictFolders: (subuser?.allowedPaths.length || 0) > 0 || ownPaths.length > 0,
                    allowedPaths: subuser?.allowedPaths.length ? subuser.allowedPaths : ownPaths,
                } as Values
            }
            validationSchema={object().shape({
                username: string().trim().max(191).required(t('users.username_required')),
                permissions: array().of(string()),
                restrictFolders: boolean(),
                allowedPaths: array()
                    .of(string())
                    .when('restrictFolders', {
                        is: true,
                        then: array()
                            .of(string())
                            .test(
                                'required',
                                t('users.folders_required'),
                                (value?: string[]) => cleanPaths(value || []).length > 0
                            )
                            .test('own', `${t('users.folders_own_only')} ${ownPaths.join(', ')}`, (value?: string[]) =>
                                coversPaths(ownPaths, cleanPaths(value || []))
                            ),
                    }),
            })}
        >
            <Form>
                <div css={tw`flex justify-between`}>
                    <h2 css={tw`text-2xl`} ref={ref}>
                        {subuser
                            ? t(canEditUser ? 'users.modify_title' : 'users.view_title', { username: subuser.username })
                            : t('users.create_title')}
                    </h2>
                    <div>
                        <Button type={'submit'} css={tw`w-full sm:w-auto`}>
                            {subuser ? t('users.save') : t('users.invite')}
                        </Button>
                    </div>
                </div>
                <FlashMessageRender byKey={'user:edit'} css={tw`mt-4`} />
                {!isRootAdmin && loggedInPermissions[0] !== '*' && (
                    <div css={tw`mt-4 pl-4 py-2 border-l-4 border-cyan-400`}>
                        <p css={tw`text-sm text-neutral-300`}>{t('users.own_permissions_only')}</p>
                    </div>
                )}
                {!subuser && (
                    <div css={tw`mt-6`}>
                        <Field
                            name={'username'}
                            label={t('users.username')}
                            description={t('users.username_help')}
                            autoComplete={'off'}
                        />
                    </div>
                )}
                <FolderAccessBox isEditable={canEditUser} ownPaths={ownPaths} css={tw`mt-6`} />
                <RolePicker isEditable={canEditUser} />
                <div css={tw`my-6`}>
                    <RolePermissions isEditable={canEditUser} />
                </div>
                <Can action={subuser ? 'user.update' : 'user.create'}>
                    <div css={tw`pb-6 flex justify-end`}>
                        <Button type={'submit'} css={tw`w-full sm:w-auto`}>
                            {subuser ? t('users.save') : t('users.invite')}
                        </Button>
                    </div>
                </Can>
            </Form>
        </Formik>
    );
};

export default asModal<Props>({
    top: false,
})(EditSubuserModal);
