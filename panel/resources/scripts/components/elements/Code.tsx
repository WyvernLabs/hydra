import React from 'react';
import classNames from 'classnames';

interface CodeProps {
    dark?: boolean | undefined;
    className?: string;
    // Optional so it can be used as a <Trans> component, which fills in the content itself.
    children?: React.ReactNode;
}

export default ({ dark, className, children }: CodeProps) => (
    <code
        className={classNames('font-mono text-sm px-2 py-1 inline-block rounded', className, {
            'bg-neutral-700': !dark,
            'bg-neutral-900 text-gray-100': dark,
        })}
    >
        {children}
    </code>
);
