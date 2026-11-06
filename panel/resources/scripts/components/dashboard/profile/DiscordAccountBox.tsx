import React, { useEffect, useState } from 'react';
import useSWR from 'swr';
import { useTranslation } from 'react-i18next';
import { useStoreState } from '@/state/hooks';
import ContentBox from '@/components/elements/ContentBox';
import Input from '@/components/elements/Input';
import Label from '@/components/elements/Label';
import { Button } from '@/components/elements/button/index';
import { useFlashKey } from '@/plugins/useFlash';
import {
    cancelDiscordCode,
    confirmDiscordCode,
    DiscordState,
    getDiscord,
    sendDiscordCode,
    unlinkDiscord,
    updateDiscord,
} from '@/api/account/discord';

const FLASH_KEY = 'account:discord';

// Hydra: the user enters the Discord user ID, the bot sends a code, and entering
// the code in time connects the accounts.
const useSecondsLeft = (expiresAt?: number): number => {
    const left = () => (expiresAt ? Math.max(0, expiresAt - Math.floor(Date.now() / 1000)) : 0);
    const [seconds, setSeconds] = useState(left);

    useEffect(() => {
        setSeconds(left());
        if (!expiresAt) return;

        const timer = setInterval(() => setSeconds(left()), 1000);
        return () => clearInterval(timer);
    }, [expiresAt]);

    return seconds;
};

const DiscordForm = ({ state, onChange }: { state: DiscordState; onChange: (state: DiscordState) => void }) => {
    const { t } = useTranslation('client/account');
    const { clearFlashes, clearAndAddHttpError } = useFlashKey(FLASH_KEY);
    const showTickets = useStoreState((store) => store.settings.data!.discord.tickets);
    const [discordId, setDiscordId] = useState('');
    const [code, setCode] = useState('');
    const [busy, setBusy] = useState(false);
    const seconds = useSecondsLeft(state.pending?.expires_at);

    const run = (action: () => Promise<DiscordState | void>) => {
        clearFlashes();
        setBusy(true);
        action()
            .then((next) => {
                setCode('');
                onChange(next || { link: null, pending: null });
            })
            .catch((error) => clearAndAddHttpError(error))
            .then(() => setBusy(false));
    };

    if (state.link) {
        return (
            <div>
                <p className={'text-sm text-neutral-300'}>
                    {t('discord.linked', { name: state.link.username || state.link.discord_id })}
                </p>
                <p className={'mt-1 text-xs text-neutral-400'}>
                    {t('discord.user_id')}: <code>{state.link.discord_id}</code>
                </p>
                {showTickets && (
                    <div className={'mt-4 flex items-center gap-2'}>
                        <input
                            type={'checkbox'}
                            id={'discordNotifyTickets'}
                            disabled={busy}
                            checked={state.link.notify_tickets}
                            onChange={(event) => run(() => updateDiscord(event.currentTarget.checked))}
                        />
                        <label htmlFor={'discordNotifyTickets'} className={'text-sm text-neutral-300'}>
                            {t('discord.notify_tickets')}
                        </label>
                    </div>
                )}
                <div className={'mt-4 text-right'}>
                    <Button.Danger size={Button.Sizes.Small} disabled={busy} onClick={() => run(unlinkDiscord)}>
                        {t('discord.unlink')}
                    </Button.Danger>
                </div>
            </div>
        );
    }

    if (state.pending && seconds > 0) {
        return (
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    run(() => confirmDiscordCode(code));
                }}
            >
                <p className={'text-sm text-neutral-300'}>{t('discord.code_sent')}</p>
                <div className={'mt-3'}>
                    <Label htmlFor={'discordCode'}>{t('discord.code')}</Label>
                    <Input
                        id={'discordCode'}
                        type={'text'}
                        autoComplete={'one-time-code'}
                        maxLength={32}
                        value={code}
                        onChange={(event) => setCode(event.currentTarget.value)}
                    />
                    <p className={'mt-1 text-xs text-neutral-400'}>
                        {t('discord.time_left', {
                            time: `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`,
                        })}
                    </p>
                </div>
                <div className={'mt-4 flex justify-end gap-2'}>
                    <Button.Text size={Button.Sizes.Small} type={'button'} disabled={busy} onClick={() => run(cancelDiscordCode)}>
                        {t('discord.cancel')}
                    </Button.Text>
                    <Button size={Button.Sizes.Small} type={'submit'} disabled={busy || code.trim() === ''}>
                        {t('discord.confirm')}
                    </Button>
                </div>
            </form>
        );
    }

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                run(() => sendDiscordCode(discordId.trim()));
            }}
        >
            <p className={'text-sm text-neutral-300'}>{t('discord.intro')}</p>
            <div className={'mt-3'}>
                <Label htmlFor={'discordId'}>{t('discord.user_id')}</Label>
                <Input
                    id={'discordId'}
                    type={'text'}
                    inputMode={'numeric'}
                    maxLength={20}
                    value={discordId}
                    onChange={(event) => setDiscordId(event.currentTarget.value)}
                />
                <p className={'mt-1 text-xs text-neutral-400'}>{t('discord.user_id_help')}</p>
            </div>
            <div className={'mt-4 text-right'}>
                <Button size={Button.Sizes.Small} type={'submit'} disabled={busy || discordId.trim() === ''}>
                    {t('discord.send_code')}
                </Button>
            </div>
        </form>
    );
};

export default () => {
    const { t } = useTranslation('client/account');
    const { data, mutate } = useSWR<DiscordState>('/api/client/account/discord', getDiscord);

    return (
        <ContentBox title={t('discord.title')} showFlashes={FLASH_KEY}>
            {data ? <DiscordForm state={data} onChange={(next) => mutate(next, false)} /> : null}
        </ContentBox>
    );
};
