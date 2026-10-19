import React, { useContext, useEffect, useRef } from 'react';
import { Subuser } from '@/state/server/subusers';
import { Form, Formik } from 'formik';
import { array, boolean, object, string } from 'yup';
import Field from '@/components/elements/Field';
import { Actions, useStoreActions, useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import createOrUpdateSubuser from '@/api/server/users/createOrUpdateSubuser';
import { ServerContext } from '@/state/server';
import FlashMessageRender from '@/components/FlashMessageRender';
import Can from '@/components/elements/Can';
import { usePermissions } from '@/plugins/usePermissions';
import { useDeepCompareMemo } from '@/plugins/useDeepCompareMemo';
import tw from 'twin.macro';
import Button from '@/components/elements/Button';
import PermissionTitleBox from '@/components/server/users/PermissionTitleBox';
import asModal from '@/hoc/asModal';
import PermissionRow from '@/components/server/users/PermissionRow';
import ModalContext from '@/context/ModalContext';
import { useTranslation } from 'react-i18next';
import FolderAccessBox from '@/components/server/users/FolderAccessBox';
import { coversPaths, normalizePath } from '@/plugins/useAllowedPaths';

type Props = {
    subuser?: Subuser;
};

interface Values {
    username: string;
    permissions: string[];
    restrictFolders: boolean;
    allowedPaths: string[];
}

// Hydra: the folders from the form, cleaned up; "/" means the whole server.
const cleanPaths = (paths: string[]): string[] =>
    Array.from(new Set(paths.filter((p) => p.trim().length > 0).map((p) => normalizePath(p.trim())))).filter(
        (p) => p !== '/'
    );

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
    const permissions = useStoreState((state) => state.permissions.data);
    // The currently logged in user's permissions. We're going to filter out any permissions
    // that they should not need.
    const loggedInPermissions = ServerContext.useStoreState((state) => state.server.permissions);
    const ownPaths = ServerContext.useStoreState((state) => state.server.allowedPaths);
    const [canEditUser] = usePermissions(subuser ? ['user.update'] : ['user.create']);

    // The permissions that can be modified by this user.
    const editablePermissions = useDeepCompareMemo(() => {
        const cleaned = Object.keys(permissions).map((key) =>
            Object.keys(permissions[key].keys).map((pkey) => `${key}.${pkey}`)
        );

        const list: string[] = ([] as string[]).concat.apply([], Object.values(cleaned));

        if (isRootAdmin || (loggedInPermissions.length === 1 && loggedInPermissions[0] === '*')) {
            return list;
        }

        return list.filter((key) => loggedInPermissions.indexOf(key) >= 0);
    }, [isRootAdmin, permissions, loggedInPermissions]);

    const submit = (values: Values) => {
        setPropOverrides({ showSpinnerOverlay: true });
        clearFlashes('user:edit');

        createOrUpdateSubuser(
            uuid,
            {
                username: values.username,
                permissions: values.permissions,
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
                <div css={tw`my-6`}>
                    {Object.keys(permissions)
                        .filter((key) => key !== 'websocket')
                        .map((key, index) => (
                            <PermissionTitleBox
                                key={`permission_${key}`}
                                title={permissions[key].title || key}
                                isEditable={canEditUser}
                                permissions={Object.keys(permissions[key].keys).map((pkey) => `${key}.${pkey}`)}
                                css={index > 0 ? tw`mt-4` : undefined}
                            >
                                <p css={tw`text-sm text-neutral-400 mb-4`}>{permissions[key].description}</p>
                                {Object.keys(permissions[key].keys).map((pkey) => (
                                    <PermissionRow
                                        key={`permission_${key}.${pkey}`}
                                        permission={`${key}.${pkey}`}
                                        disabled={!canEditUser || editablePermissions.indexOf(`${key}.${pkey}`) < 0}
                                    />
                                ))}
                            </PermissionTitleBox>
                        ))}
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
