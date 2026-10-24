import React, { useEffect, useState } from 'react';
import { Trans, useTranslation } from 'react-i18next';
import { Dialog } from '@/components/elements/dialog';
import { Button } from '@/components/elements/button/index';
import { Input } from '@/components/elements/inputs';
import { fileBitsToString } from '@/helpers';

// Hydra: dialogs of the file manager.

const invalidName = (name: string) => !name.trim() || /[\\\0]/.test(name) || name === '.' || name === '..';

/**
 * Asks for a name, for new files and folders (which may contain "/" to create
 * folders on the way) and for renaming.
 */
export const NameDialog = ({
    open,
    title,
    label,
    initial,
    confirm,
    allowSlash,
    onClose,
    onSubmit,
}: {
    open: boolean;
    title: string;
    label: string;
    initial: string;
    confirm: string;
    allowSlash?: boolean;
    onClose: () => void;
    onSubmit: (name: string) => void;
}) => {
    const { t } = useTranslation('client/files');
    const [name, setName] = useState(initial);
    const invalid = invalidName(name) || (!allowSlash && name.includes('/'));

    useEffect(() => {
        if (open) setName(initial);
    }, [open, initial]);

    const submit = () => {
        if (invalid) return;
        onSubmit(name.trim());
    };

    return (
        <Dialog open={open} onClose={onClose} title={title}>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    submit();
                }}
            >
                <label className={'mb-1 block text-xs text-gray-300'}>{label}</label>
                <Input.Text
                    autoFocus
                    value={name}
                    onChange={(event: React.ChangeEvent<HTMLInputElement>) => setName(event.currentTarget.value)}
                    onFocus={(event: React.FocusEvent<HTMLInputElement>) => {
                        // Select the name without the extension, like a desktop.
                        const dot = event.currentTarget.value.lastIndexOf('.');
                        event.currentTarget.setSelectionRange(0, dot > 0 ? dot : event.currentTarget.value.length);
                    }}
                    className={'font-mono'}
                />
                {allowSlash && <p className={'mt-1 text-xs text-gray-400'}>{t('dialogs.slash_help')}</p>}
            </form>
            <Dialog.Footer>
                <Button.Text onClick={onClose}>{t('dialogs.cancel')}</Button.Text>
                <Button onClick={submit} disabled={invalid}>
                    {confirm}
                </Button>
            </Dialog.Footer>
        </Dialog>
    );
};

export const DeleteDialog = ({
    open,
    names,
    trashEnabled,
    permanent: initialPermanent,
    onClose,
    onConfirm,
}: {
    open: boolean;
    names: string[];
    trashEnabled: boolean;
    permanent: boolean;
    onClose: () => void;
    onConfirm: (permanent: boolean) => void;
}) => {
    const { t } = useTranslation('client/files');
    const [permanent, setPermanent] = useState(initialPermanent);
    const forever = permanent || !trashEnabled;

    useEffect(() => {
        if (open) setPermanent(initialPermanent);
    }, [open, initialPermanent]);

    return (
        <Dialog
            open={open}
            onClose={onClose}
            title={forever ? t('dialogs.delete_forever_title') : t('dialogs.delete_title')}
        >
            <p className={'text-sm text-gray-300'}>
                <Trans
                    t={t}
                    i18nKey={forever ? 'dialogs.delete_forever_text' : 'dialogs.delete_text'}
                    count={names.length}
                    values={{ name: names[0], count: names.length }}
                    components={{ bold: <span className={'font-semibold text-gray-50'} /> }}
                />
            </p>
            {names.length > 1 && (
                <ul
                    className={
                        'mt-2 max-h-32 overflow-y-auto rounded bg-gray-900/50 p-2 font-mono text-xs text-gray-300'
                    }
                >
                    {names.slice(0, 50).map((name) => (
                        <li key={name} className={'truncate'}>
                            {name}
                        </li>
                    ))}
                    {names.length > 50 && <li>{t('dialogs.and_more', { count: names.length - 50 })}</li>}
                </ul>
            )}
            {trashEnabled && (
                <label className={'mt-4 flex items-center gap-2 text-sm text-gray-300'}>
                    <Input.Checkbox
                        checked={permanent}
                        onChange={(event: React.ChangeEvent<HTMLInputElement>) =>
                            setPermanent(event.currentTarget.checked)
                        }
                    />
                    {t('dialogs.skip_trash')}
                </label>
            )}
            <Dialog.Footer>
                <Button.Text onClick={onClose}>{t('dialogs.cancel')}</Button.Text>
                <Button.Danger onClick={() => onConfirm(forever)}>
                    {forever ? t('dialogs.delete_forever') : t('dialogs.move_to_trash')}
                </Button.Danger>
            </Dialog.Footer>
        </Dialog>
    );
};

export const ChmodDialog = ({
    open,
    name,
    mode,
    onClose,
    onSubmit,
}: {
    open: boolean;
    name: string;
    mode: string;
    onClose: () => void;
    onSubmit: (mode: string) => void;
}) => {
    const { t } = useTranslation('client/files');
    const [value, setValue] = useState(mode);
    const valid = /^[0-7]{3}$/.test(value);

    useEffect(() => {
        if (open) setValue(mode);
    }, [open, mode]);

    return (
        <Dialog open={open} onClose={onClose} title={t('dialogs.chmod_title', { name })}>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    if (valid) onSubmit(value);
                }}
            >
                <label className={'mb-1 block text-xs text-gray-300'}>{t('dialogs.chmod_label')}</label>
                <Input.Text
                    autoFocus
                    value={value}
                    maxLength={3}
                    onChange={(event: React.ChangeEvent<HTMLInputElement>) => setValue(event.currentTarget.value)}
                    className={'font-mono'}
                />
                <p className={'mt-1 font-mono text-xs text-gray-400'}>
                    {valid ? fileBitsToString(value, false) : t('dialogs.chmod_invalid')}
                </p>
            </form>
            <Dialog.Footer>
                <Button.Text onClick={onClose}>{t('dialogs.cancel')}</Button.Text>
                <Button onClick={() => valid && onSubmit(value)} disabled={!valid}>
                    {t('dialogs.save')}
                </Button>
            </Dialog.Footer>
        </Dialog>
    );
};

export const UnsavedDialog = ({
    open,
    name,
    onClose,
    onDiscard,
}: {
    open: boolean;
    name: string;
    onClose: () => void;
    onDiscard: () => void;
}) => {
    const { t } = useTranslation('client/files');

    return (
        <Dialog.Confirm
            open={open}
            onClose={onClose}
            title={t('dialogs.unsaved_title')}
            confirm={t('dialogs.discard')}
            onConfirmed={onDiscard}
        >
            {t('dialogs.unsaved_text', { name })}
        </Dialog.Confirm>
    );
};
