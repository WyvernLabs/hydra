import React from 'react';
import tw from 'twin.macro';
import { useStoreState } from 'easy-peasy';
import { ServerContext } from '@/state/server';
import { useDeepCompareMemo } from '@/plugins/useDeepCompareMemo';
import PermissionTitleBox from '@/components/server/users/PermissionTitleBox';
import PermissionRow from '@/components/server/users/PermissionRow';

// Hydra: every permission of a server with its checkbox, for subusers and
// roles alike. Works on the "permissions" field of the surrounding form.

export default ({ isEditable }: { isEditable: boolean }) => {
    const isRootAdmin = useStoreState((state) => state.user.data!.serverAccess);
    const permissions = useStoreState((state) => state.permissions.data);
    const loggedInPermissions = ServerContext.useStoreState((state) => state.server.permissions);

    // Only the permissions the logged in user has can be handed out.
    const editablePermissions = useDeepCompareMemo(() => {
        const list = Object.keys(permissions).flatMap((key) =>
            Object.keys(permissions[key].keys).map((pkey) => `${key}.${pkey}`)
        );

        if (isRootAdmin || (loggedInPermissions.length === 1 && loggedInPermissions[0] === '*')) {
            return list;
        }

        return list.filter((key) => loggedInPermissions.indexOf(key) >= 0);
    }, [isRootAdmin, permissions, loggedInPermissions]);

    return (
        <>
            {Object.keys(permissions)
                .filter((key) => key !== 'websocket')
                .map((key, index) => (
                    <PermissionTitleBox
                        key={`permission_${key}`}
                        title={permissions[key].title || key}
                        isEditable={isEditable}
                        permissions={Object.keys(permissions[key].keys).map((pkey) => `${key}.${pkey}`)}
                        css={index > 0 ? tw`mt-4` : undefined}
                    >
                        <p css={tw`text-sm text-neutral-400 mb-4`}>{permissions[key].description}</p>
                        {Object.keys(permissions[key].keys).map((pkey) => (
                            <PermissionRow
                                key={`permission_${key}.${pkey}`}
                                permission={`${key}.${pkey}`}
                                disabled={!isEditable || editablePermissions.indexOf(`${key}.${pkey}`) < 0}
                            />
                        ))}
                    </PermissionTitleBox>
                ))}
        </>
    );
};
