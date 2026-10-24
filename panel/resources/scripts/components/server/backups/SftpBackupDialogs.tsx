import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Dialog } from '@/components/elements/dialog';
import { Button } from '@/components/elements/button/index';
import Input, { Textarea } from '@/components/elements/Input';
import Spinner from '@/components/elements/Spinner';
import FlashMessageRender from '@/components/FlashMessageRender';
import useFlash from '@/plugins/useFlash';
import { ServerContext } from '@/state/server';
import { bytesToString } from '@/lib/formatters';
import { formatDate } from '@/lib/date';
import getServerBackups from '@/api/swr/getServerBackups';
import {
    deleteSftpBackupSettings,
    getSftpBackupFiles,
    getSftpBackupSettings,
    importSftpBackup,
    saveSftpBackupSettings,
    SftpBackupFile,
    SftpBackupSettings,
} from '@/api/server/backups/sftp';

// Hydra: the own SFTP server for backups (addon "sftp_backups") and importing
// backups that are already on it.

interface DialogProps {
    open: boolean;
    onClose: () => void;
}

const Field = ({ label, help, children }: { label: string; help?: string; children: React.ReactNode }) => (
    <label className={'block'}>
        <span className={'mb-1 block text-sm text-gray-200'}>{label}</span>
        {children}
        {help && <span className={'mt-1 block text-xs text-gray-400'}>{help}</span>}
    </label>
);

export const SftpSettingsDialog = ({ open, onClose }: DialogProps) => {
    const { t } = useTranslation('client/server');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { mutate } = getServerBackups();
    const { addFlash, clearFlashes, clearAndAddHttpError } = useFlash();
    const [settings, setSettings] = useState<SftpBackupSettings | null>(null);
    const [busy, setBusy] = useState(false);
    const [values, setValues] = useState({ host: '', port: 22, username: '', password: '', privateKey: '', path: '' });

    useEffect(() => {
        if (!open) return;

        clearFlashes('backups:sftp');
        setSettings(null);
        getSftpBackupSettings(uuid)
            .then((data) => {
                setSettings(data);
                setValues({
                    host: data.host,
                    port: data.port,
                    username: data.username,
                    password: '',
                    privateKey: '',
                    path: data.path,
                });
            })
            .catch((error) => clearAndAddHttpError({ key: 'backups:sftp', error }));
    }, [open]);

    const set = (key: keyof typeof values) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) =>
        setValues((v) => ({ ...v, [key]: key === 'port' ? Number(e.currentTarget.value) : e.currentTarget.value }));

    const save = () => {
        clearFlashes('backups:sftp');
        setBusy(true);
        saveSftpBackupSettings(uuid, values)
            .then((data) => {
                setSettings(data);
                setValues((v) => ({ ...v, password: '', privateKey: '' }));
                mutate();
                addFlash({
                    key: 'backups:sftp',
                    type: 'success',
                    message: t('backups.sftp.saved', { fingerprint: data.hostKey, count: data.found }),
                });
            })
            .catch((error) => clearAndAddHttpError({ key: 'backups:sftp', error }))
            .then(() => setBusy(false));
    };

    const remove = () => {
        clearFlashes('backups:sftp');
        setBusy(true);
        deleteSftpBackupSettings(uuid)
            .then(() => {
                mutate();
                onClose();
            })
            .catch((error) => clearAndAddHttpError({ key: 'backups:sftp', error }))
            .then(() => setBusy(false));
    };

    return (
        <Dialog
            open={open}
            onClose={onClose}
            title={t('backups.sftp.title')}
            description={t('backups.sftp.description')}
        >
            <FlashMessageRender byKey={'backups:sftp'} className={'mb-4'} />
            {!settings ? (
                <Spinner size={'large'} centered />
            ) : (
                <div className={'space-y-4'}>
                    <div className={'grid grid-cols-3 gap-3'}>
                        <div className={'col-span-2'}>
                            <Field label={t('backups.sftp.host')}>
                                <Input value={values.host} onChange={set('host')} placeholder={'backup.example.com'} />
                            </Field>
                        </div>
                        <Field label={t('backups.sftp.port')}>
                            <Input type={'number'} min={1} max={65535} value={values.port} onChange={set('port')} />
                        </Field>
                    </div>
                    <div className={'grid grid-cols-1 gap-3 sm:grid-cols-2'}>
                        <Field label={t('backups.sftp.username')}>
                            <Input value={values.username} onChange={set('username')} autoComplete={'off'} />
                        </Field>
                        <Field label={t('backups.sftp.password')}>
                            <Input
                                type={'password'}
                                value={values.password}
                                onChange={set('password')}
                                autoComplete={'new-password'}
                                placeholder={settings.hasPassword ? t('backups.sftp.unchanged') : ''}
                            />
                        </Field>
                    </div>
                    <Field label={t('backups.sftp.private_key')} help={t('backups.sftp.private_key_help')}>
                        <Textarea
                            rows={3}
                            className={'font-mono text-xs'}
                            value={values.privateKey}
                            onChange={set('privateKey')}
                            placeholder={
                                settings.hasPrivateKey
                                    ? t('backups.sftp.unchanged')
                                    : '-----BEGIN OPENSSH PRIVATE KEY-----'
                            }
                        />
                    </Field>
                    <Field label={t('backups.sftp.path')} help={t('backups.sftp.path_help')}>
                        <Input value={values.path} onChange={set('path')} placeholder={'backups'} />
                    </Field>
                    {settings.hostKey && (
                        <p className={'break-all text-xs text-gray-400'}>
                            {t('backups.sftp.host_key')} <code className={'text-gray-200'}>{settings.hostKey}</code>
                        </p>
                    )}
                    <p className={'text-xs text-gray-400'}>{t('backups.sftp.note')}</p>
                </div>
            )}
            <Dialog.Footer>
                {settings?.configured && (
                    <Button.Danger variant={Button.Variants.Secondary} onClick={remove} disabled={busy}>
                        {t('backups.sftp.remove')}
                    </Button.Danger>
                )}
                <Button.Text onClick={onClose} disabled={busy}>
                    {t('client/common:actions.close')}
                </Button.Text>
                <Button onClick={save} disabled={busy || !settings || !values.host || !values.username}>
                    {busy ? t('backups.sftp.testing') : t('backups.sftp.save')}
                </Button>
            </Dialog.Footer>
        </Dialog>
    );
};

export const SftpImportDialog = ({ open, onClose }: DialogProps) => {
    const { t } = useTranslation('client/server');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { mutate } = getServerBackups();
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const [files, setFiles] = useState<SftpBackupFile[] | null>(null);
    const [busy, setBusy] = useState<string | null>(null);

    useEffect(() => {
        if (!open) return;

        clearFlashes('backups:import');
        setFiles(null);
        getSftpBackupFiles(uuid)
            .then(setFiles)
            .catch((error) => {
                setFiles([]);
                clearAndAddHttpError({ key: 'backups:import', error });
            });
    }, [open]);

    const doImport = (file: SftpBackupFile) => {
        clearFlashes('backups:import');
        setBusy(file.name);
        importSftpBackup(uuid, file.name)
            .then(() => {
                setFiles((list) => (list || []).map((f) => (f.name === file.name ? { ...f, imported: true } : f)));
                mutate();
            })
            .catch((error) => clearAndAddHttpError({ key: 'backups:import', error }))
            .then(() => setBusy(null));
    };

    return (
        <Dialog
            open={open}
            onClose={onClose}
            title={t('backups.import.title')}
            description={t('backups.import.description')}
        >
            <FlashMessageRender byKey={'backups:import'} className={'mb-4'} />
            {files === null ? (
                <Spinner size={'large'} centered />
            ) : files.length === 0 ? (
                <p className={'text-sm text-gray-300'}>{t('backups.import.none')}</p>
            ) : (
                <div className={'max-h-80 space-y-2 overflow-y-auto'}>
                    {files.map((file) => (
                        <div key={file.name} className={'flex items-center gap-3 rounded bg-gray-700 p-3'}>
                            <div className={'min-w-0 flex-1'}>
                                <p className={'truncate text-sm text-gray-50'}>{file.name}</p>
                                <p className={'text-xs text-gray-400'}>
                                    {bytesToString(file.size)} · {formatDate(file.modified, 'datetime')}
                                </p>
                            </div>
                            {file.imported ? (
                                <span className={'text-xs text-gray-400'}>{t('backups.import.imported')}</span>
                            ) : (
                                <Button size={Button.Sizes.Small} onClick={() => doImport(file)} disabled={!!busy}>
                                    {busy === file.name ? '…' : t('backups.import.button')}
                                </Button>
                            )}
                        </div>
                    ))}
                </div>
            )}
            <Dialog.Footer>
                <Button.Text onClick={onClose}>{t('client/common:actions.close')}</Button.Text>
            </Dialog.Footer>
        </Dialog>
    );
};
