import React, { useRef, useState } from 'react';
import { Link, useRouteMatch } from 'react-router-dom';
import { useStoreState } from 'easy-peasy';
import { useTranslation } from 'react-i18next';
import { Download, ExternalLink, Table, Upload } from 'lucide-react';
import { ServerContext } from '@/state/server';
import { usePermissions } from '@/plugins/usePermissions';
import { httpErrorToHuman } from '@/api/http';
import useFlash from '@/plugins/useFlash';
import { Dialog } from '@/components/elements/dialog';
import { Button } from '@/components/elements/button/index';
import FlashMessageRender from '@/components/FlashMessageRender';
import { exportDatabase, importDatabase, openPhpMyAdmin } from '@/api/server/databases/manager';
import { ServerDatabase } from '@/api/server/databases/getServerDatabases';

// Hydra: buttons for the database manager, import, export and phpMyAdmin.

interface Props {
    database: ServerDatabase;
    // Shows the link to the database manager (not needed on its own page).
    showManage?: boolean;
    // Called after an import, e.g. to reload the tables.
    onImported?: () => void;
    // Buttons with text next to the icon, e.g. on the database cards.
    labels?: boolean;
    flashKey: string;
}

const ImportDialog = ({
    database,
    open,
    onClose,
    onImported,
}: {
    database: ServerDatabase;
    open: boolean;
    onClose: () => void;
    onImported?: () => void;
}) => {
    const { t } = useTranslation('client/server');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { clearFlashes, clearAndAddHttpError, addFlash } = useFlash();
    const [file, setFile] = useState<File | null>(null);
    const [progress, setProgress] = useState<number | null>(null);
    const input = useRef<HTMLInputElement>(null);

    const close = () => {
        if (progress !== null) return;
        setFile(null);
        clearFlashes('database:import');
        onClose();
    };

    const submit = () => {
        if (!file) return;
        clearFlashes('database:import');
        setProgress(0);
        importDatabase(uuid, database.id, file, setProgress)
            .then(() => {
                setProgress(null);
                setFile(null);
                onClose();
                addFlash({
                    key: 'databases',
                    type: 'success',
                    message: t('databases.import_done', { name: database.name }),
                });
                onImported?.();
            })
            .catch((error) => {
                setProgress(null);
                clearAndAddHttpError({ key: 'database:import', error });
            });
    };

    return (
        <Dialog open={open} onClose={close} title={t('databases.import_title', { name: database.name })}>
            <FlashMessageRender byKey={'database:import'} className={'mb-4'} />
            <p className={'text-sm text-gray-300'}>{t('databases.import_text')}</p>
            <input
                ref={input}
                type={'file'}
                accept={'.sql,.gz,.sql.gz,application/sql,application/gzip'}
                className={'hidden'}
                onChange={(e) => setFile(e.currentTarget.files?.[0] || null)}
            />
            <div className={'mt-4 flex items-center gap-3'}>
                <Button.Text type={'button'} onClick={() => input.current?.click()} disabled={progress !== null}>
                    {t('databases.import_choose')}
                </Button.Text>
                <span className={'truncate text-sm text-gray-200'}>
                    {file ? file.name : t('databases.import_none')}
                </span>
            </div>
            {progress !== null && (
                <p className={'mt-4 text-sm text-gray-300'}>
                    {progress < 100
                        ? t('databases.import_uploading', { percent: progress })
                        : t('databases.import_running')}
                </p>
            )}
            <Dialog.Footer>
                <Button.Text onClick={close} disabled={progress !== null}>
                    {t('client/common:actions.cancel')}
                </Button.Text>
                <Button onClick={submit} disabled={!file || progress !== null}>
                    {t('databases.import')}
                </Button>
            </Dialog.Footer>
        </Dialog>
    );
};

export default ({ database, showManage, onImported, flashKey, labels }: Props) => {
    const { t } = useTranslation('client/server');
    const match = useRouteMatch();
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const phpMyAdmin = useStoreState((state) => state.settings.data!.phpMyAdmin);
    const [canBrowse, canImport, canExport, canPhpMyAdmin] = usePermissions([
        'database.browse',
        'database.import',
        'database.export',
        'database.phpmyadmin',
    ]);
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const [importing, setImporting] = useState(false);
    const [busy, setBusy] = useState(false);

    const onExport = () => {
        clearFlashes(flashKey);
        setBusy(true);
        exportDatabase(uuid, database.id)
            .then((url) => {
                window.location.href = url;
            })
            .catch((error) => clearAndAddHttpError({ key: flashKey, error }))
            .then(() => setBusy(false));
    };

    const onPhpMyAdmin = () => {
        clearFlashes(flashKey);
        // Opened right away, popup blockers only allow windows opened by a click.
        const tab = window.open('about:blank', '_blank');
        openPhpMyAdmin(uuid, database.id)
            .then((url) => {
                if (tab) {
                    tab.opener = null;
                    tab.location.href = url;
                } else {
                    window.location.href = url;
                }
            })
            .catch((error) => {
                tab?.close();
                clearAndAddHttpError({ key: flashKey, error: httpErrorToHuman(error) });
            });
    };

    // With labels the buttons fill the cells of the grid on the database cards.
    const iconButton = labels ? 'w-full gap-1.5 px-3 py-2' : 'p-2';
    const label = (text: string) => (labels ? <span>{text}</span> : null);

    return (
        <>
            {canImport && (
                <ImportDialog
                    database={database}
                    open={importing}
                    onClose={() => setImporting(false)}
                    onImported={onImported}
                />
            )}
            {showManage && canBrowse && (
                <Link
                    to={`${match.url.replace(/\/+$/, '')}/${database.id}`}
                    title={t('databases.manage')}
                    className={labels ? 'block' : undefined}
                >
                    <Button.Text className={iconButton} type={'button'}>
                        <Table className={'h-4 w-4'} />
                        {label(t('databases.manage'))}
                    </Button.Text>
                </Link>
            )}
            {canImport && (
                <Button.Text className={iconButton} title={t('databases.import')} onClick={() => setImporting(true)}>
                    <Upload className={'h-4 w-4'} />
                    {label(t('databases.import'))}
                </Button.Text>
            )}
            {canExport && (
                <Button.Text className={iconButton} title={t('databases.export')} onClick={onExport} disabled={busy}>
                    <Download className={'h-4 w-4'} />
                    {label(t('databases.export'))}
                </Button.Text>
            )}
            {phpMyAdmin && canPhpMyAdmin && (
                <Button.Text className={iconButton} title={t('databases.phpmyadmin')} onClick={onPhpMyAdmin}>
                    <ExternalLink className={'h-4 w-4'} />
                    {label(t('databases.phpmyadmin'))}
                </Button.Text>
            )}
        </>
    );
};
