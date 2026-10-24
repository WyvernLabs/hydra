import React from 'react';
import { useTranslation } from 'react-i18next';
import classNames from 'classnames';
import { ServerContext } from '@/state/server';
import Can from '@/components/elements/Can';
import PowerButtons from '@/components/server/console/PowerButtons';
import ContentContainer from '@/components/elements/ContentContainer';

// Hydra: name, state and power buttons of the server above every page of it,
// so it can be controlled from every tab.

const dotColors: Record<string, string> = {
    running: 'bg-green-500',
    starting: 'bg-yellow-500',
    stopping: 'bg-yellow-500',
    offline: 'bg-red-500',
};

export default () => {
    const { t } = useTranslation('client/server');
    const name = ServerContext.useStoreState((state) => state.server.data!.name);
    const description = ServerContext.useStoreState((state) => state.server.data!.description);
    const status = ServerContext.useStoreState((state) => state.status.value);
    // Users who only keep the files of a suspended server cannot start it.
    const limited = ServerContext.useStoreState((state) => state.server.suspendedAccess === 'limited');

    return (
        // Same width as the pages below it.
        <ContentContainer>
            <div
                className={
                    'mb-4 flex flex-wrap items-center gap-4 rounded-lg border border-gray-700 bg-gray-800/80 px-4 py-3 backdrop-blur'
                }
            >
                <div className={'min-w-0 flex-1'}>
                    <h1 className={'flex items-center gap-2 font-header text-xl font-medium text-gray-50'}>
                        <span
                            className={classNames('h-2.5 w-2.5 shrink-0 rounded-full', dotColors[status || 'offline'])}
                            title={t(`status.${status || 'offline'}`)}
                        />
                        <span className={'truncate'}>{name}</span>
                        <span className={'shrink-0 text-xs font-normal uppercase tracking-wide text-gray-400'}>
                            {t(`status.${status || 'offline'}`)}
                        </span>
                    </h1>
                    {description && <p className={'mt-0.5 line-clamp-1 text-sm text-gray-300'}>{description}</p>}
                </div>
                {!limited && (
                    <Can action={['control.start', 'control.stop', 'control.restart']} matchAny>
                        <PowerButtons className={'flex w-full space-x-2 sm:w-auto'} />
                    </Can>
                )}
            </div>
        </ContentContainer>
    );
};
