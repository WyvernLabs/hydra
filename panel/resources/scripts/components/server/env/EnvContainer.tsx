import React, { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import classNames from 'classnames';
import { Eye, EyeOff, FileCode, Plus, RotateCcw, Save, Search, Trash2 } from 'lucide-react';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import FlashMessageRender from '@/components/FlashMessageRender';
import Spinner from '@/components/elements/Spinner';
import { ServerContext } from '@/state/server';
import { useStoreState } from '@/state/hooks';
import { usePermissions } from '@/plugins/usePermissions';
import useFlash from '@/plugins/useFlash';
import getFileContents from '@/api/server/files/getFileContents';
import saveFileContents from '@/api/server/files/saveFileContents';
import { httpErrorToHuman } from '@/api/http';
import { encodePathSegments } from '@/helpers';
import { EnvLine, isSecret, isValidKey, parseEnv, stringifyEnv } from '@/lib/env';

// Hydra: the .env file of a server (addon ".env editor") as a form. Comments,
// order and quotes of the file are kept when saving.

const FLASH = 'server:env';

export default () => {
    const { t } = useTranslation('client/env');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const id = ServerContext.useStoreState((state) => state.server.data!.id);
    const path = useStoreState((state) => state.settings.data!.env.path);
    const [canSave] = usePermissions(['file.update']);
    const { addFlash, clearFlashes, clearAndAddHttpError } = useFlash();
    const [lines, setLines] = useState<EnvLine[] | null>(null);
    const [values, setValues] = useState<Record<string, string>>({});
    const [added, setAdded] = useState<string[]>([]);
    const [missing, setMissing] = useState<string | null>(null);
    const [search, setSearch] = useState('');
    const [shown, setShown] = useState<string[]>([]);
    const [newKey, setNewKey] = useState('');
    const [newValue, setNewValue] = useState('');
    const [saving, setSaving] = useState(false);

    const load = () => {
        setMissing(null);
        setAdded([]);
        getFileContents(uuid, path)
            .then((content) => {
                const parsed = parseEnv(content);
                setLines(parsed);
                setValues(
                    Object.fromEntries(
                        parsed.filter((line) => line.key !== null).map((line) => [line.key!, line.value])
                    )
                );
            })
            .catch((error) => {
                // A missing file is created on the first save.
                setLines([]);
                setValues({});
                setMissing(httpErrorToHuman(error));
            });
    };

    useEffect(load, [uuid, path]);

    const original = useMemo(
        () =>
            Object.fromEntries(
                (lines || []).filter((line) => line.key !== null).map((line) => [line.key!, line.value])
            ),
        [lines]
    );
    const keys = useMemo(
        () => [...Object.keys(original).filter((key) => key in values), ...added],
        [original, values, added]
    );
    const changed =
        keys.filter((key) => values[key] !== original[key]).length +
        Object.keys(original).filter((key) => !(key in values)).length;
    const filtered = keys.filter((key) => key.toLowerCase().includes(search.trim().toLowerCase()));
    const keyError =
        newKey && !isValidKey(newKey) ? t('invalid_key') : newKey && keys.includes(newKey) ? t('key_exists') : null;

    const set = (key: string, value: string) => setValues((current) => ({ ...current, [key]: value }));

    const remove = (key: string) => {
        setValues((current) => {
            const next = { ...current };
            delete next[key];
            return next;
        });
        setAdded((current) => current.filter((k) => k !== key));
    };

    const add = () => {
        if (!newKey || keyError) return;
        set(newKey, newValue);
        setAdded((current) => [...current, newKey]);
        setNewKey('');
        setNewValue('');
    };

    const save = () => {
        if (!lines) return;
        clearFlashes(FLASH);
        setSaving(true);
        saveFileContents(uuid, path, stringifyEnv(lines, values, added))
            .then(() => {
                load();
                addFlash({ key: FLASH, type: 'success', message: t('saved') });
            })
            .catch((error) => clearAndAddHttpError({ key: FLASH, error }))
            .then(() => setSaving(false));
    };

    return (
        <ServerContentBlock title={t('title')}>
            <div className={'mb-4 flex flex-wrap items-center gap-3'}>
                <div className={'min-w-0 flex-1'}>
                    <h2 className={'font-mono text-lg text-gray-50'}>{path}</h2>
                    <p className={'text-xs text-gray-400'}>{t('help')}</p>
                </div>
                {!missing && (
                    <Link
                        to={`/server/${id}/files#/${encodePathSegments(path)}`}
                        className={
                            'flex items-center gap-2 rounded border border-gray-600 px-3 py-1.5 text-sm text-gray-100 no-underline hover:bg-gray-700'
                        }
                    >
                        <FileCode className={'h-4 w-4'} />
                        {t('open_editor')}
                    </Link>
                )}
                {canSave && (
                    <>
                        {changed > 0 && (
                            <button
                                type={'button'}
                                onClick={load}
                                disabled={saving}
                                title={t('discard')}
                                className={'rounded p-2 text-gray-300 hover:bg-gray-700 hover:text-gray-100'}
                            >
                                <RotateCcw className={'h-4 w-4'} />
                            </button>
                        )}
                        <button
                            type={'button'}
                            onClick={save}
                            disabled={saving || changed === 0}
                            className={
                                'flex items-center gap-2 rounded bg-primary-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-400 disabled:cursor-not-allowed disabled:opacity-50'
                            }
                        >
                            <Save className={'h-4 w-4'} />
                            {changed > 0 ? t('save_count', { count: changed }) : t('save')}
                        </button>
                    </>
                )}
            </div>
            <FlashMessageRender byKey={FLASH} className={'mb-4'} />

            {!lines ? (
                <Spinner centered size={'large'} />
            ) : (
                <>
                    {missing && (
                        <div
                            className={
                                'mb-4 rounded-lg border border-yellow-500/30 bg-yellow-500/10 px-4 py-3 text-sm text-yellow-100'
                            }
                        >
                            {canSave ? t('missing_create', { path }) : t('missing', { path })}
                        </div>
                    )}
                    {keys.length > 0 && (
                        <div className={'relative mb-4'}>
                            <Search className={'absolute left-3 top-2.5 h-4 w-4 text-gray-400'} />
                            <input
                                type={'search'}
                                value={search}
                                onChange={(event) => setSearch(event.currentTarget.value)}
                                placeholder={t('search')}
                                className={
                                    'w-full rounded border-2 border-gray-600 bg-gray-700 py-1.5 pl-9 pr-3 text-sm text-gray-100 outline-none focus:border-primary-400'
                                }
                            />
                        </div>
                    )}
                    <div className={'space-y-2'}>
                        {filtered.map((key) => {
                            const secret = isSecret(key) && !shown.includes(key);
                            const dirty = values[key] !== original[key];

                            return (
                                <div
                                    key={key}
                                    className={classNames(
                                        'flex flex-wrap items-center gap-3 rounded-lg border bg-gray-800/80 px-3 py-2 backdrop-blur md:flex-nowrap',
                                        dirty ? 'border-primary-500' : 'border-gray-700'
                                    )}
                                >
                                    <label
                                        htmlFor={`env-${key}`}
                                        className={
                                            'w-full truncate font-mono text-sm text-gray-200 md:w-72 md:shrink-0'
                                        }
                                        title={key}
                                    >
                                        {key}
                                    </label>
                                    <input
                                        id={`env-${key}`}
                                        type={secret ? 'password' : 'text'}
                                        value={values[key]}
                                        disabled={!canSave || saving}
                                        autoComplete={'off'}
                                        spellCheck={false}
                                        onChange={(event) => set(key, event.currentTarget.value)}
                                        className={
                                            'min-w-0 flex-1 rounded border border-gray-600 bg-gray-700 px-2 py-1 font-mono text-sm text-gray-100 outline-none focus:border-primary-400 disabled:opacity-60'
                                        }
                                    />
                                    {isSecret(key) && (
                                        <button
                                            type={'button'}
                                            title={secret ? t('show') : t('hide')}
                                            onClick={() =>
                                                setShown((current) =>
                                                    current.includes(key)
                                                        ? current.filter((k) => k !== key)
                                                        : [...current, key]
                                                )
                                            }
                                            className={
                                                'rounded p-1.5 text-gray-400 hover:bg-gray-700 hover:text-gray-100'
                                            }
                                        >
                                            {secret ? <Eye className={'h-4 w-4'} /> : <EyeOff className={'h-4 w-4'} />}
                                        </button>
                                    )}
                                    {canSave && (
                                        <button
                                            type={'button'}
                                            title={t('remove')}
                                            onClick={() => remove(key)}
                                            className={
                                                'rounded p-1.5 text-gray-400 hover:bg-red-500/20 hover:text-red-400'
                                            }
                                        >
                                            <Trash2 className={'h-4 w-4'} />
                                        </button>
                                    )}
                                </div>
                            );
                        })}
                        {keys.length === 0 && !missing && (
                            <p className={'py-6 text-center text-sm text-gray-400'}>{t('empty')}</p>
                        )}
                        {keys.length > 0 && filtered.length === 0 && (
                            <p className={'py-6 text-center text-sm text-gray-400'}>{t('no_match')}</p>
                        )}
                    </div>
                    {canSave && (
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                add();
                            }}
                            className={
                                'mt-4 flex flex-wrap items-start gap-3 rounded-lg border border-dashed border-gray-600 p-3 md:flex-nowrap'
                            }
                        >
                            <div className={'w-full md:w-72 md:shrink-0'}>
                                <input
                                    value={newKey}
                                    onChange={(event) =>
                                        setNewKey(event.currentTarget.value.toUpperCase().replace(/\s/g, '_'))
                                    }
                                    placeholder={t('new_key')}
                                    spellCheck={false}
                                    className={classNames(
                                        'w-full rounded border bg-gray-700 px-2 py-1 font-mono text-sm text-gray-100 outline-none',
                                        keyError ? 'border-red-500' : 'border-gray-600 focus:border-primary-400'
                                    )}
                                />
                                {keyError && <p className={'mt-1 text-xs text-red-400'}>{keyError}</p>}
                            </div>
                            <input
                                value={newValue}
                                onChange={(event) => setNewValue(event.currentTarget.value)}
                                placeholder={t('new_value')}
                                spellCheck={false}
                                className={
                                    'min-w-0 flex-1 rounded border border-gray-600 bg-gray-700 px-2 py-1 font-mono text-sm text-gray-100 outline-none focus:border-primary-400'
                                }
                            />
                            <button
                                type={'submit'}
                                disabled={!newKey || !!keyError}
                                className={
                                    'flex items-center gap-1.5 rounded bg-gray-700 px-3 py-1 text-sm text-gray-100 hover:bg-gray-600 disabled:opacity-50'
                                }
                            >
                                <Plus className={'h-4 w-4'} />
                                {t('add')}
                            </button>
                        </form>
                    )}
                </>
            )}
        </ServerContentBlock>
    );
};
