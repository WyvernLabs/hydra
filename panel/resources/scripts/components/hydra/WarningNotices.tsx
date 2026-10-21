import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { TriangleAlert } from 'lucide-react';
import { useStoreActions, useStoreState } from '@/state/hooks';
import http from '@/api/http';
import { formatDate } from '@/lib/date';

// Hydra: warnings from the admins, shown on every page until the user confirms them.
export default () => {
    const { t } = useTranslation();
    const warnings = useStoreState((state) => state.user.data?.warnings || []);
    const updateUserData = useStoreActions((actions) => actions.user.updateUserData);
    const [busy, setBusy] = useState<number | null>(null);

    if (warnings.length === 0) return null;

    const acknowledge = (id: number) => {
        setBusy(id);
        http.post(`/api/client/account/warnings/${id}/acknowledge`)
            .then(() => updateUserData({ warnings: warnings.filter((warning) => warning.id !== id) }))
            .catch(console.error)
            .then(() => setBusy(null));
    };

    return (
        <div className={'mb-4 space-y-2'}>
            {warnings.map((warning) => (
                <div
                    key={warning.id}
                    role={'alert'}
                    className={
                        'flex flex-col gap-3 rounded-lg border border-yellow-500/60 bg-yellow-500/10 p-4 sm:flex-row sm:items-start'
                    }
                >
                    <TriangleAlert className={'h-5 w-5 shrink-0 text-yellow-400'} />
                    <div className={'min-w-0 flex-1'}>
                        <p className={'font-medium text-yellow-200'}>{t('warnings.title')}</p>
                        <p className={'mt-1 whitespace-pre-wrap text-sm text-gray-100'}>{warning.reason}</p>
                        <p className={'mt-1 text-xs text-gray-400'}>
                            {formatDate(warning.createdAt)} · {t('warnings.hint')}
                        </p>
                    </div>
                    <button
                        type={'button'}
                        disabled={busy === warning.id}
                        onClick={() => acknowledge(warning.id)}
                        className={
                            'shrink-0 rounded bg-yellow-500 px-3 py-1.5 text-sm font-medium text-gray-900 hover:bg-yellow-400 disabled:opacity-50'
                        }
                    >
                        {t('warnings.acknowledge')}
                    </button>
                </div>
            ))}
        </div>
    );
};
