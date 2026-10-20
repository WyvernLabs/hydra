import React from 'react';
import { Link, useRouteMatch } from 'react-router-dom';
import tw from 'twin.macro';
import isEqual from 'react-fast-compare';
import { useTranslation } from 'react-i18next';
import { useStoreState } from 'easy-peasy';
import { ServerContext } from '@/state/server';
import { Alert } from '@/components/elements/alert';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import CopyOnClick from '@/components/elements/CopyOnClick';
import Input from '@/components/elements/Input';
import Can from '@/components/elements/Can';
import HydraIcon from '@/components/hydra/HydraIcon';
import { useServerNavigation } from '@/components/hydra/serverNavigation';
import { ip } from '@/lib/formatters';

/**
 * Banner above every page of a suspended server.
 */
export const SuspendedBanner = () => {
    const { t } = useTranslation('client/server');
    const access = ServerContext.useStoreState((state) => state.server.suspendedAccess);

    if (access === 'none') return null;

    return (
        <Alert type={access === 'full' ? 'warning' : 'danger'} className={'mb-4'}>
            {access === 'full' ? t('conflict.suspended_full') : t('conflict.suspended_limited')}
        </Alert>
    );
};

/**
 * Replaces the console of a suspended server for users without full access:
 * links to the parts that stay available and the SFTP details.
 */
export default () => {
    const { t } = useTranslation('client/server');
    const match = useRouteMatch();
    const id = ServerContext.useStoreState((state) => state.server.data!.id);
    const sftp = ServerContext.useStoreState((state) => state.server.data!.sftpDetails, isEqual);
    const username = useStoreState((state) => state.user.data!.username);
    const base = match.url.replace(/\/*$/, '');
    // Already limited to the parts a suspended server keeps.
    const { categories } = useServerNavigation();

    return (
        <ServerContentBlock title={t('conflict.suspended_title')}>
            <h1 css={tw`font-header font-medium text-2xl text-neutral-50 mb-4`}>{t('conflict.suspended_title')}</h1>
            <div css={tw`grid grid-cols-1 md:grid-cols-3 gap-4 mb-6`}>
                {categories.flatMap((category) =>
                    category.items.map((item) => (
                        <Link
                            key={item.key}
                            to={`${base}${item.path}`}
                            className={'hydra-card'}
                            css={tw`flex items-center gap-3 p-4 no-underline`}
                        >
                            <HydraIcon
                                name={item.icon}
                                css={tw`w-6 h-6 text-primary-400`}
                                style={category.color ? { color: category.color } : undefined}
                            />
                            <span css={tw`flex-1 text-neutral-100 font-semibold`}>
                                {t(`client/common:${item.name}`)}
                            </span>
                            <span css={tw`text-xs text-neutral-400 uppercase`}>{t('conflict.suspended_open')}</span>
                        </Link>
                    ))
                )}
            </div>
            <Can action={'file.sftp'}>
                <TitledGreyBox title={t('conflict.suspended_sftp')}>
                    <div css={tw`grid grid-cols-1 sm:grid-cols-2 gap-4`}>
                        <CopyOnClick text={`sftp://${ip(sftp.ip)}:${sftp.port}`}>
                            <Input type={'text'} value={`sftp://${ip(sftp.ip)}:${sftp.port}`} readOnly />
                        </CopyOnClick>
                        <CopyOnClick text={`${username}.${id}`}>
                            <Input type={'text'} value={`${username}.${id}`} readOnly />
                        </CopyOnClick>
                    </div>
                </TitledGreyBox>
            </Can>
        </ServerContentBlock>
    );
};
