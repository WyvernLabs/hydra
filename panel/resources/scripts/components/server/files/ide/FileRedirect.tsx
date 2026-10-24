import React from 'react';
import { Redirect, useLocation, useParams } from 'react-router-dom';
import { ServerContext } from '@/state/server';

// Hydra: the old editor pages (/files/edit#/path and /files/new#/folder) open
// the file manager instead.
export default () => {
    const { action } = useParams<{ action: 'edit' | 'new' }>();
    const { hash } = useLocation();
    const id = ServerContext.useStoreState((state) => state.server.data!.id);

    return <Redirect to={`/server/${id}/files${action === 'new' ? '?new=1' : ''}${hash}`} />;
};
