import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CheckCircle2, CircleAlert, RefreshCw } from 'lucide-react';
import { ServerContext } from '@/state/server';
import { Dialog } from '@/components/elements/dialog';
import { Button } from '@/components/elements/button/index';
import Spinner from '@/components/elements/Spinner';
import { httpErrorToHuman } from '@/api/http';
import {
    copyServerRole,
    getRoleCopyTargets,
    RoleCopyResult,
    RoleCopyTarget,
    ServerRole,
} from '@/api/server/users/roles';

// Hydra: copies a role to other servers of the user. Roles with the same name
// are replaced there, otherwise a new one is created.

interface Props {
    role: ServerRole | null;
    onClose: () => void;
}

export default ({ role, onClose }: Props) => {
    const { t } = useTranslation('client/server');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [targets, setTargets] = useState<RoleCopyTarget[] | null>(null);
    const [selected, setSelected] = useState<string[]>([]);
    const [filter, setFilter] = useState('');
    const [results, setResults] = useState<RoleCopyResult[] | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (!role) return;

        setTargets(null);
        setSelected([]);
        setFilter('');
        setResults(null);
        setError(null);
        getRoleCopyTargets(uuid)
            .then(setTargets)
            .catch((e) => setError(httpErrorToHuman(e)));
    }, [role?.id]);

    const visible = (targets || []).filter((target) =>
        `${target.name} ${target.identifier}`.toLowerCase().includes(filter.trim().toLowerCase())
    );
    const allVisibleSelected = visible.length > 0 && visible.every((target) => selected.includes(target.uuid));

    const toggle = (target: string) =>
        setSelected((current) =>
            current.includes(target) ? current.filter((item) => item !== target) : [...current, target]
        );

    const toggleAll = () =>
        setSelected((current) =>
            allVisibleSelected
                ? current.filter((item) => !visible.some((target) => target.uuid === item))
                : Array.from(new Set([...current, ...visible.map((target) => target.uuid)]))
        );

    const submit = () => {
        if (!role || selected.length === 0) return;

        setBusy(true);
        setError(null);
        copyServerRole(uuid, role.id, selected)
            .then(setResults)
            .catch((e) => setError(httpErrorToHuman(e)))
            .then(() => setBusy(false));
    };

    const nameOf = (result: RoleCopyResult) =>
        result.name || targets?.find((target) => target.uuid === result.uuid)?.name || result.uuid;

    return (
        <Dialog
            open={!!role}
            onClose={onClose}
            title={t('users.roles.copy_title', { name: role?.name || '' })}
            description={t('users.roles.copy_help')}
        >
            {error && <p className={'mb-3 rounded bg-red-500/15 px-3 py-2 text-sm text-red-300'}>{error}</p>}
            {results ? (
                <ul className={'space-y-2'}>
                    {results.map((result) => {
                        const ok = result.status === 'created' || result.status === 'updated';

                        return (
                            <li
                                key={result.uuid}
                                className={
                                    'flex items-start gap-3 rounded-md border border-gray-700 bg-gray-900/40 px-3 py-2'
                                }
                            >
                                {ok ? (
                                    result.status === 'updated' ? (
                                        <RefreshCw className={'mt-0.5 h-4 w-4 shrink-0 text-accent-400'} />
                                    ) : (
                                        <CheckCircle2 className={'mt-0.5 h-4 w-4 shrink-0 text-accent-400'} />
                                    )
                                ) : (
                                    <CircleAlert className={'mt-0.5 h-4 w-4 shrink-0 text-yellow-400'} />
                                )}
                                <div className={'min-w-0'}>
                                    <p className={'truncate text-sm font-medium text-gray-100'}>{nameOf(result)}</p>
                                    <p className={`text-xs ${ok ? 'text-gray-400' : 'text-yellow-300'}`}>
                                        {t(`users.roles.copy_status.${result.status}`)}
                                    </p>
                                </div>
                            </li>
                        );
                    })}
                </ul>
            ) : !targets ? (
                !error && <Spinner centered />
            ) : targets.length === 0 ? (
                <p className={'text-sm text-gray-400'}>{t('users.roles.copy_no_servers')}</p>
            ) : (
                <>
                    <div className={'mb-3 flex flex-wrap items-center gap-3'}>
                        <input
                            type={'search'}
                            value={filter}
                            onChange={(e) => setFilter(e.currentTarget.value)}
                            placeholder={t('users.roles.copy_search')}
                            className={
                                'min-w-0 flex-1 rounded-md border border-gray-600 bg-gray-900/60 px-3 py-2 text-sm text-gray-100 outline-none focus:border-accent-500'
                            }
                        />
                        <button type={'button'} className={'hydra-permission-bulk'} onClick={toggleAll}>
                            {allVisibleSelected ? t('users.select_none') : t('users.select_all')}
                        </button>
                    </div>
                    <div className={'max-h-80 space-y-1 overflow-y-auto pr-1'}>
                        {visible.map((target) => (
                            <label
                                key={target.uuid}
                                className={
                                    'flex cursor-pointer items-center gap-3 rounded-md border border-transparent px-3 py-2 hover:border-gray-600 hover:bg-gray-900/40'
                                }
                            >
                                <input
                                    type={'checkbox'}
                                    checked={selected.includes(target.uuid)}
                                    onChange={() => toggle(target.uuid)}
                                />
                                <span className={'min-w-0 flex-1 truncate text-sm text-gray-100'}>{target.name}</span>
                                <span className={'font-mono text-xs text-gray-500'}>{target.identifier}</span>
                            </label>
                        ))}
                        {visible.length === 0 && <p className={'px-3 py-2 text-sm text-gray-500'}>—</p>}
                    </div>
                    <p className={'mt-3 text-xs text-gray-400'}>{t('users.roles.copy_replace_notice')}</p>
                </>
            )}
            <Dialog.Footer>
                <Button.Text onClick={onClose}>
                    {results ? t('users.roles.copy_done') : t('client/common:actions.cancel')}
                </Button.Text>
                {!results && (
                    <Button onClick={submit} disabled={busy || selected.length === 0}>
                        {t('users.roles.copy_submit', { count: selected.length })}
                    </Button>
                )}
            </Dialog.Footer>
        </Dialog>
    );
};
