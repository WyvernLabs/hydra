import React from 'react';
import classNames from 'classnames';
import { ServerContext } from '@/state/server';

/**
 * Name and power state of the current server, shown above the server navigation.
 */
export default () => {
    const name = ServerContext.useStoreState((state) => state.server.data?.name);
    const node = ServerContext.useStoreState((state) => state.server.data?.node);
    const status = ServerContext.useStoreState((state) => state.status.value);

    return (
        <div className={'hydra-server-card'}>
            <span className={classNames('hydra-status-dot', `hydra-status-dot--${status || 'offline'}`)} />
            <div className={'hydra-server-card-text'}>
                <strong title={name}>{name}</strong>
                <small>{node}</small>
            </div>
        </div>
    );
};
