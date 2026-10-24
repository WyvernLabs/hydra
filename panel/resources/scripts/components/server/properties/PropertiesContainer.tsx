import React, { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { FileCode, RotateCcw, Save, Search } from 'lucide-react';
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
import { parseProperties, propertyType, PropertyLine, stringifyProperties } from '@/lib/properties';

// Hydra: a .properties file (addon "Properties") as a form: true/false as
// switches, numbers and texts as fields. Comments and the order of the file
// are kept when saving.

const Toggle = ({ checked, disabled, onChange }: { checked: boolean; disabled: boolean; onChange: () => void }) => (
    <button
        type={'button'}
        role={'switch'}
        aria-checked={checked}
        disabled={disabled}
        onClick={onChange}
        className={`relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors disabled:cursor-not-allowed disabled:opacity-50 ${
            checked ? 'bg-primary-500' : 'bg-gray-600'
        }`}
    >
        <span
            className={`inline-block h-5 w-5 rounded-full bg-white shadow transition-transform ${
                checked ? 'translate-x-[1.375rem]' : 'translate-x-0.5'
            }`}
        />
    </button>
);

export default () => {
    const { t } = useTranslation('client/server');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const id = ServerContext.useStoreState((state) => state.server.data!.id);
    const path = useStoreState((state) => state.settings.data!.properties.path);
    const [canSave] = usePermissions(['file.update']);
    const { addFlash, clearFlashes, clearAndAddHttpError } = useFlash();
    const [lines, setLines] = useState<PropertyLine[] | null>(null);
    const [values, setValues] = useState<Record<string, string>>({});
    const [error, setError] = useState<string | null>(null);
    const [search, setSearch] = useState('');
    const [saving, setSaving] = useState(false);

    const load = () => {
        setError(null);
        getFileContents(uuid, path)
            .then((content) => {
                const parsed = parseProperties(content);
                setLines(parsed);
                setValues(
                    Object.fromEntries(
                        parsed.filter((line) => line.key !== null).map((line) => [line.key!, line.value])
                    )
                );
            })
            .catch((error) => setError(httpErrorToHuman(error)));
    };

    useEffect(load, [uuid, path]);

    const properties = useMemo(() => (lines || []).filter((line) => line.key !== null), [lines]);
    const changed = properties.filter((line) => values[line.key!] !== line.value).length;
    const shown = properties.filter((line) => line.key!.toLowerCase().includes(search.trim().toLowerCase()));

    const save = () => {
        if (!lines) return;
        clearFlashes('server:properties');
        setSaving(true);
        saveFileContents(uuid, path, stringifyProperties(lines, values))
            .then(() => {
                load();
                addFlash({ key: 'server:properties', type: 'success', message: t('properties.saved') });
            })
            .catch((error) => clearAndAddHttpError({ key: 'server:properties', error }))
            .then(() => setSaving(false));
    };

    const set = (key: string, value: string) => setValues((current) => ({ ...current, [key]: value }));

    return (
        <ServerContentBlock title={t('properties.title')}>
            <div className={'mb-4 flex flex-wrap items-center gap-3'}>
                <div className={'min-w-0 flex-1'}>
                    <h2 className={'font-mono text-lg text-gray-50'}>{path}</h2>
                    <p className={'text-xs text-gray-400'}>{t('properties.help')}</p>
                </div>
                <Link
                    to={`/server/${id}/files/edit#/${encodePathSegments(path)}`}
                    className={
                        'flex items-center gap-2 rounded border border-gray-600 px-3 py-1.5 text-sm text-gray-100 no-underline hover:bg-gray-700'
                    }
                >
                    <FileCode className={'h-4 w-4'} />
                    {t('properties.open_editor')}
                </Link>
                {canSave && (
                    <>
                        {changed > 0 && (
                            <button
                                type={'button'}
                                onClick={load}
                                disabled={saving}
                                title={t('properties.discard')}
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
                            {changed > 0 ? t('properties.save_count', { count: changed }) : t('properties.save')}
                        </button>
                    </>
                )}
            </div>
            <FlashMessageRender byKey={'server:properties'} className={'mb-4'} />

            {error ? (
                <div className={'hydra-card p-6 text-center text-sm text-gray-300'}>
                    <p>{t('properties.missing', { path })}</p>
                    <p className={'mt-1 text-xs text-gray-500'}>{error}</p>
                </div>
            ) : !lines ? (
                <Spinner centered size={'large'} />
            ) : (
                <>
                    <div className={'relative mb-4'}>
                        <Search className={'absolute left-3 top-2.5 h-4 w-4 text-gray-400'} />
                        <input
                            type={'search'}
                            value={search}
                            onChange={(event) => setSearch(event.currentTarget.value)}
                            placeholder={t('properties.search')}
                            className={
                                'w-full rounded border-2 border-gray-600 bg-gray-700 py-1.5 pl-9 pr-3 text-sm text-gray-100 outline-none focus:border-primary-400'
                            }
                        />
                    </div>
                    {shown.length === 0 ? (
                        <p className={'py-6 text-center text-sm text-gray-400'}>{t('properties.empty')}</p>
                    ) : (
                        <div className={'grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3'}>
                            {shown.map((line) => {
                                const key = line.key!;
                                const type = propertyType(line.value);
                                const value = values[key] ?? '';
                                const dirty = value !== line.value;

                                return (
                                    <div
                                        key={key}
                                        className={`flex items-center gap-3 rounded-lg border bg-gray-800/80 px-3 py-2.5 backdrop-blur ${
                                            dirty ? 'border-primary-500' : 'border-gray-700'
                                        }`}
                                    >
                                        <label
                                            htmlFor={`property-${key}`}
                                            className={'min-w-0 flex-1 truncate font-mono text-sm text-gray-200'}
                                            title={key}
                                        >
                                            {key}
                                        </label>
                                        {type === 'boolean' ? (
                                            <Toggle
                                                checked={value === 'true'}
                                                disabled={!canSave || saving}
                                                onChange={() => set(key, value === 'true' ? 'false' : 'true')}
                                            />
                                        ) : (
                                            <input
                                                id={`property-${key}`}
                                                type={type === 'number' ? 'number' : 'text'}
                                                value={value}
                                                disabled={!canSave || saving}
                                                onChange={(event) => set(key, event.currentTarget.value)}
                                                className={`rounded border border-gray-600 bg-gray-700 px-2 py-1 font-mono text-sm text-gray-100 outline-none focus:border-primary-400 disabled:opacity-60 ${
                                                    type === 'number' ? 'w-28 text-right' : 'w-1/2 min-w-[8rem]'
                                                }`}
                                            />
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </>
            )}
        </ServerContentBlock>
    );
};
