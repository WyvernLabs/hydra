import React, { useContext, useEffect } from 'react';
import { Form, Formik } from 'formik';
import { array, object, string } from 'yup';
import tw from 'twin.macro';
import { useTranslation } from 'react-i18next';
import { Actions, useStoreActions } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import { ServerContext } from '@/state/server';
import Field from '@/components/elements/Field';
import Label from '@/components/elements/Label';
import Button from '@/components/elements/Button';
import FlashMessageRender from '@/components/FlashMessageRender';
import ModalContext from '@/context/ModalContext';
import asModal from '@/hoc/asModal';
import PermissionList from '@/components/server/users/PermissionList';
import { RoleValues, saveServerRole, ServerRole } from '@/api/server/users/roles';

// Hydra: creates or changes a role of the server. Members of the role get its
// new permissions right away.

interface Props {
    role?: ServerRole;
    onSaved: () => void;
}

const COLORS = ['#74c236', '#3b82f6', '#f59e0b', '#ef4444', '#a855f7', '#14b8a6', '#ec4899', '#94a3b8'];

const RoleModal = ({ role, onSaved }: Props) => {
    const { t } = useTranslation('client/server');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { dismiss, setPropOverrides } = useContext(ModalContext);
    const { clearFlashes, clearAndAddHttpError } = useStoreActions(
        (actions: Actions<ApplicationStore>) => actions.flashes
    );

    useEffect(() => () => clearFlashes('role:edit'), []);

    const submit = (values: RoleValues) => {
        setPropOverrides({ showSpinnerOverlay: true });
        clearFlashes('role:edit');
        saveServerRole(uuid, values, role?.id)
            .then(() => {
                onSaved();
                dismiss();
            })
            .catch((error) => {
                setPropOverrides(null);
                clearAndAddHttpError({ key: 'role:edit', error });
            });
    };

    return (
        <Formik
            onSubmit={submit}
            initialValues={{
                name: role?.name || '',
                color: role?.color || COLORS[0],
                permissions: role?.permissions || [],
            }}
            validationSchema={object().shape({
                name: string().trim().max(60).required(t('users.roles.name_required')),
                color: string().matches(/^#[0-9a-fA-F]{6}$/),
                permissions: array().of(string()),
            })}
        >
            {({ values, setFieldValue }) => (
                <Form>
                    <div css={tw`flex justify-between`}>
                        <h2 css={tw`text-2xl`}>
                            {role ? t('users.roles.edit_title', { name: role.name }) : t('users.roles.create_title')}
                        </h2>
                        <Button type={'submit'}>{role ? t('users.save') : t('users.roles.create')}</Button>
                    </div>
                    <FlashMessageRender byKey={'role:edit'} css={tw`mt-4`} />
                    {role && role.members > 0 && (
                        <div css={tw`mt-4 pl-4 py-2 border-l-4 border-cyan-400`}>
                            <p css={tw`text-sm text-neutral-300`}>
                                {t('users.roles.members_notice', { count: role.members })}
                            </p>
                        </div>
                    )}
                    <div className={'mt-6 grid grid-cols-1 gap-4 sm:grid-cols-[1fr_auto]'}>
                        <Field name={'name'} label={t('users.roles.name')} autoComplete={'off'} maxLength={60} />
                        <div>
                            <Label>{t('users.roles.color')}</Label>
                            <div className={'flex h-10 items-center gap-1.5'}>
                                {COLORS.map((color) => (
                                    <button
                                        key={color}
                                        type={'button'}
                                        aria-label={color}
                                        onClick={() => setFieldValue('color', color)}
                                        className={`h-6 w-6 rounded-full ring-offset-2 ring-offset-gray-800 ${
                                            values.color === color ? 'ring-2 ring-white' : ''
                                        }`}
                                        style={{ backgroundColor: color }}
                                    />
                                ))}
                            </div>
                        </div>
                    </div>
                    <div css={tw`my-6`}>
                        <PermissionList isEditable />
                    </div>
                    <div css={tw`pb-6 flex justify-end`}>
                        <Button type={'submit'}>{role ? t('users.save') : t('users.roles.create')}</Button>
                    </div>
                </Form>
            )}
        </Formik>
    );
};

export default asModal<Props>({ top: false })(RoleModal);
