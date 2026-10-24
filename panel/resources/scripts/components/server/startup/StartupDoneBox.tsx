import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import { Button } from '@/components/elements/button/index';
import FlashMessageRender from '@/components/FlashMessageRender';
import { ServerContext } from '@/state/server';
import { usePermissions } from '@/plugins/usePermissions';
import useFlash from '@/plugins/useFlash';
import http from '@/api/http';

// Hydra: the user decides which console messages mark the server as running.
// Without own messages those of the egg are used. Wings applies them on the
// next start.

interface Props {
    // Own messages, null for those of the egg.
    done: string[] | null;
    eggDone: string[];
    onSaved: (done: string[] | null) => void;
}

export default ({ done, eggDone, onSaved }: Props) => {
    const { t } = useTranslation('client/server');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [canEdit] = usePermissions(['startup.update']);
    const { addFlash, clearFlashes, clearAndAddHttpError } = useFlash();
    const [value, setValue] = useState((done || []).join('\n'));
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        setValue((done || []).join('\n'));
    }, [done]);

    const save = (lines: string[] | null) => {
        clearFlashes('startup:done');
        setSaving(true);
        http.put(`/api/client/servers/${uuid}/startup/done`, { done: lines })
            .then(({ data }) => {
                onSaved(data.startup_done || null);
                addFlash({ key: 'startup:done', type: 'success', message: t('startup.done_saved') });
            })
            .catch((error) => clearAndAddHttpError({ key: 'startup:done', error }))
            .then(() => setSaving(false));
    };

    const lines = value
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line.length > 0);

    return (
        <TitledGreyBox title={t('startup.done_title')} className={'mt-8'}>
            <FlashMessageRender byKey={'startup:done'} className={'mb-3'} />
            <p className={'mb-3 text-sm text-gray-300'}>{t('startup.done_help')}</p>
            <textarea
                value={value}
                onChange={(event) => setValue(event.currentTarget.value)}
                disabled={!canEdit || saving}
                rows={3}
                placeholder={eggDone.join('\n')}
                className={
                    'block w-full resize-y rounded border-2 border-gray-600 bg-gray-700 p-3 font-mono text-sm text-gray-100 outline-none focus:border-primary-400 disabled:opacity-60'
                }
            />
            <p className={'mt-2 text-xs text-gray-400'}>
                {eggDone.length > 0 ? t('startup.done_default', { done: eggDone.join(' | ') }) : t('startup.done_none')}
            </p>
            {canEdit && (
                <div className={'mt-4 flex flex-wrap justify-end gap-2'}>
                    {done && (
                        <Button.Text disabled={saving} onClick={() => save(null)}>
                            {t('startup.done_reset')}
                        </Button.Text>
                    )}
                    <Button disabled={saving || lines.length > 5} onClick={() => save(lines.length ? lines : null)}>
                        {t('startup.done_save')}
                    </Button>
                </div>
            )}
        </TitledGreyBox>
    );
};
