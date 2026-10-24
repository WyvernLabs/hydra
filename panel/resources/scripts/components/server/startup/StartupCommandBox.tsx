import React, { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { RotateCcw } from 'lucide-react';
import { ServerContext } from '@/state/server';
import { usePermissions } from '@/plugins/usePermissions';
import useFlash from '@/plugins/useFlash';
import http from '@/api/http';

// Hydra: the startup command. With the addon "Startup Command" a click on it
// shows the raw command with its {{VARIABLES}} for editing; it is saved when
// the field is left and Esc cancels. The grey button goes back to the command
// of the egg.

interface Props {
    // Command with the values of the variables filled in.
    invocation: string;
    raw: string;
    eggCommand: string;
    editable: boolean;
    onSaved: (invocation: string, raw: string) => void;
}

export default ({ invocation, raw, eggCommand, editable, onSaved }: Props) => {
    const { t } = useTranslation('client/server');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [canEdit] = usePermissions(['startup.update']);
    const { addFlash, clearFlashes, clearAndAddHttpError } = useFlash();
    const [editing, setEditing] = useState(false);
    const [value, setValue] = useState(raw);
    const [saving, setSaving] = useState(false);
    const input = useRef<HTMLTextAreaElement>(null);
    const allowed = editable && canEdit;

    useEffect(() => {
        if (!editing) setValue(raw);
    }, [raw]);

    useEffect(() => {
        if (!editing || !input.current) return;
        input.current.focus();
        input.current.setSelectionRange(input.current.value.length, input.current.value.length);
    }, [editing]);

    // Grows with the command, which is a single line that wraps.
    useEffect(() => {
        if (!input.current) return;
        input.current.style.height = 'auto';
        input.current.style.height = `${input.current.scrollHeight}px`;
    }, [value, editing]);

    const save = (startup: string | null) => {
        clearFlashes('startup:command');
        setSaving(true);
        http.put(`/api/client/servers/${uuid}/startup/command`, { startup })
            .then(({ data }) => {
                onSaved(data.startup_command, data.raw_startup_command);
                addFlash({ key: 'startup:command', type: 'success', message: t('startup.command_saved') });
            })
            .catch((error) => {
                setValue(raw);
                clearAndAddHttpError({ key: 'startup:command', error });
            })
            .then(() => setSaving(false));
    };

    const finish = () => {
        setEditing(false);
        const command = value.replace(/[\r\n]+/g, ' ').trim();
        if (command && command !== raw) {
            save(command);
        } else {
            setValue(raw);
        }
    };

    return (
        <div className={'relative px-1 py-2'}>
            {editing ? (
                <textarea
                    ref={input}
                    value={value}
                    rows={1}
                    spellCheck={false}
                    maxLength={4096}
                    onChange={(event) => setValue(event.currentTarget.value)}
                    onBlur={finish}
                    onKeyDown={(event) => {
                        if (event.key === 'Escape') {
                            setValue(raw);
                            setEditing(false);
                        } else if (event.key === 'Enter') {
                            event.preventDefault();
                            event.currentTarget.blur();
                        }
                    }}
                    className={
                        'block w-full resize-none overflow-hidden rounded bg-neutral-900 py-2 px-4 pr-12 font-mono text-gray-50 outline-none ring-2 ring-primary-500'
                    }
                />
            ) : (
                <p
                    role={allowed ? 'button' : undefined}
                    tabIndex={allowed ? 0 : undefined}
                    title={allowed ? t('startup.command_edit') : undefined}
                    onClick={() => allowed && !saving && setEditing(true)}
                    onKeyDown={(event) => allowed && event.key === 'Enter' && setEditing(true)}
                    className={`break-all rounded bg-neutral-900 py-2 px-4 font-mono ${
                        allowed ? 'cursor-text pr-12 hover:ring-1 hover:ring-gray-500' : ''
                    } ${saving ? 'opacity-60' : ''}`}
                >
                    {invocation}
                </p>
            )}
            {allowed && raw !== eggCommand && (
                <button
                    type={'button'}
                    title={t('startup.command_reset')}
                    disabled={saving}
                    // Before the blur of the field, so the reset is not followed by a save.
                    onMouseDown={(event) => event.preventDefault()}
                    onClick={() => {
                        setEditing(false);
                        save(null);
                    }}
                    className={
                        'absolute right-3 top-4 rounded bg-gray-600 p-1.5 text-gray-100 hover:bg-gray-500 disabled:opacity-50'
                    }
                >
                    <RotateCcw className={'h-3.5 w-3.5'} />
                </button>
            )}
        </div>
    );
};
