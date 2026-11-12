import React from 'react';
import classNames from 'classnames';
import { BoardClaimer } from '@/api/server/board';

// Hydra: profile picture of a user on the board, or the first letter of the name.

export default ({ user, className }: { user: BoardClaimer; className?: string }) =>
    user.avatar ? (
        <img
            src={user.avatar}
            alt={user.username}
            className={classNames('shrink-0 rounded-full object-cover', className || 'h-5 w-5')}
        />
    ) : (
        <span
            className={classNames(
                'flex shrink-0 items-center justify-center rounded-full bg-primary-500 text-2xs font-semibold uppercase text-white',
                className || 'h-5 w-5'
            )}
        >
            {user.username.slice(0, 1)}
        </span>
    );
