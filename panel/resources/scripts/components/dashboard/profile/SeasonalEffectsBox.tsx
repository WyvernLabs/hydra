import React, { useState } from 'react';
import useSWR from 'swr';
import { useTranslation } from 'react-i18next';
import http from '@/api/http';
import ContentBox from '@/components/elements/ContentBox';
import { useFlashKey } from '@/plugins/useFlash';

// Hydra: what the user wants to see of the running seasonal event (Design > Effects).

interface EffectsState {
    event: string;
    options: string[];
    values: Record<string, boolean>;
}

const FLASH_KEY = 'account:effects';

declare global {
    interface Window {
        HydraEffects?: { apply: (values: Record<string, boolean>) => void };
    }
}

export default () => {
    const { t } = useTranslation('client/account');
    const { clearFlashes, clearAndAddHttpError } = useFlashKey(FLASH_KEY);
    const { data, mutate } = useSWR<EffectsState>('/api/client/account/effects', () =>
        http.get('/api/client/account/effects').then((response) => response.data)
    );
    const [busy, setBusy] = useState(false);

    const change = (option: string, value: boolean) => {
        clearFlashes();
        setBusy(true);
        http.put('/api/client/account/effects', { values: { [option]: value } })
            .then((response) => {
                mutate(response.data, false);
                // Takes effect at once, without loading the page again.
                window.HydraEffects?.apply(response.data.values);
            })
            .catch((error) => clearAndAddHttpError(error))
            .then(() => setBusy(false));
    };

    return (
        <ContentBox title={t('effects.title')} showFlashes={FLASH_KEY}>
            {data && (
                <>
                    <p className={'text-sm text-neutral-300'}>{t(`effects.intro.${data.event}`)}</p>
                    {data.options.map((option) => (
                        <div key={option} className={'mt-3 flex items-center gap-2'}>
                            <input
                                type={'checkbox'}
                                id={`effect-${option}`}
                                disabled={busy}
                                checked={data.values[option]}
                                onChange={(event) => change(option, event.currentTarget.checked)}
                            />
                            <label htmlFor={`effect-${option}`} className={'text-sm text-neutral-300'}>
                                {t(`effects.options.${data.event}.${option}`)}
                            </label>
                        </div>
                    ))}
                    <p className={'mt-3 text-xs text-neutral-400'}>{t('effects.saved_hint')}</p>
                </>
            )}
        </ContentBox>
    );
};
