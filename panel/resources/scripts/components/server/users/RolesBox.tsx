import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pencil, Plus, Trash2, Users } from 'lucide-react';
import { ServerContext } from '@/state/server';
import Can from '@/components/elements/Can';
import { Dialog } from '@/components/elements/dialog';
import { Button } from '@/components/elements/button/index';
import { useFlashKey } from '@/plugins/useFlash';
import FlashMessageRender from '@/components/FlashMessageRender';
import getServerSubusers from '@/api/server/users/getServerSubusers';
import { deleteServerRole, ServerRole, useServerRoles } from '@/api/server/users/roles';
import RoleModal from '@/components/server/users/RoleModal';

// Hydra: the roles of the server on the users page.

export default () => {
    const { t } = useTranslation('client/server');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const setSubusers = ServerContext.useStoreActions((actions) => actions.subusers.setSubusers);
    const { data: roles, mutate } = useServerRoles();
    const { clearFlashes, clearAndAddHttpError } = useFlashKey('roles');
    const [editing, setEditing] = useState<ServerRole | null>(null);
    const [modal, setModal] = useState(false);
    const [deleting, setDeleting] = useState<ServerRole | null>(null);

    // Members get the permissions of a changed role, so they are loaded again.
    const reload = () => {
        mutate();
        getServerSubusers(uuid)
            .then(setSubusers)
            .catch(() => undefined);
    };

    const remove = () => {
        if (!deleting) return;
        clearFlashes();
        deleteServerRole(uuid, deleting.id)
            .then(() => {
                setDeleting(null);
                reload();
            })
            .catch((error) => {
                setDeleting(null);
                clearAndAddHttpError(error);
            });
    };

    return (
        <section className={'mb-6 rounded-lg border border-gray-700 bg-gray-800/80 p-4 backdrop-blur'}>
            <RoleModal
                role={editing || undefined}
                visible={modal}
                onModalDismissed={() => setModal(false)}
                onSaved={reload}
            />
            <Dialog.Confirm
                open={!!deleting}
                onClose={() => setDeleting(null)}
                title={t('users.roles.delete_title', { name: deleting?.name || '' })}
                confirm={t('users.roles.delete')}
                onConfirmed={remove}
            >
                {t('users.roles.delete_text')}
            </Dialog.Confirm>
            <div className={'mb-3 flex flex-wrap items-center justify-between gap-3'}>
                <div>
                    <h3 className={'text-sm font-medium uppercase tracking-wide text-gray-300'}>
                        {t('users.roles.title')}
                    </h3>
                    <p className={'text-xs text-gray-400'}>{t('users.roles.help')}</p>
                </div>
                <Can action={'user.create'}>
                    <Button.Text
                        onClick={() => {
                            setEditing(null);
                            setModal(true);
                        }}
                    >
                        <Plus className={'mr-1 h-4 w-4'} />
                        {t('users.roles.new')}
                    </Button.Text>
                </Can>
            </div>
            <FlashMessageRender byKey={'roles'} className={'mb-3'} />
            {!roles || roles.length === 0 ? (
                <p className={'text-sm text-gray-500'}>{roles ? t('users.roles.none') : '…'}</p>
            ) : (
                <div className={'flex flex-wrap gap-2'}>
                    {roles.map((role) => (
                        <div
                            key={role.id}
                            className={
                                'flex items-center gap-2 rounded-full border bg-gray-900/60 py-1 pl-3 pr-1 text-sm'
                            }
                            style={{ borderColor: role.color }}
                        >
                            <span className={'h-2.5 w-2.5 rounded-full'} style={{ backgroundColor: role.color }} />
                            <span className={'font-medium text-gray-100'}>{role.name}</span>
                            <span
                                className={'flex items-center gap-1 text-xs text-gray-400'}
                                title={t('users.roles.members')}
                            >
                                <Users className={'h-3 w-3'} />
                                {role.members}
                            </span>
                            <Can action={'user.update'}>
                                <button
                                    type={'button'}
                                    className={'rounded-full p-1.5 text-gray-400 hover:bg-gray-700 hover:text-gray-100'}
                                    title={t('users.roles.edit')}
                                    onClick={() => {
                                        setEditing(role);
                                        setModal(true);
                                    }}
                                >
                                    <Pencil className={'h-3.5 w-3.5'} />
                                </button>
                            </Can>
                            <Can action={'user.delete'}>
                                <button
                                    type={'button'}
                                    className={'rounded-full p-1.5 text-gray-400 hover:bg-gray-700 hover:text-red-400'}
                                    title={t('users.roles.delete')}
                                    onClick={() => setDeleting(role)}
                                >
                                    <Trash2 className={'h-3.5 w-3.5'} />
                                </button>
                            </Can>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
};
