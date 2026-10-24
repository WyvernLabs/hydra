import React, { useEffect, useLayoutEffect, useRef, useState } from 'react';
import classNames from 'classnames';
import Portal from '@/components/elements/Portal';

// Hydra: right-click menu of the file manager.

export interface MenuItem {
    label: string;
    icon: React.ComponentType<{ className?: string }>;
    onClick: () => void;
    danger?: boolean;
    shortcut?: string;
    // Draws a separator above the item.
    separated?: boolean;
}

export default ({ x, y, items, onClose }: { x: number; y: number; items: MenuItem[]; onClose: () => void }) => {
    const ref = useRef<HTMLDivElement>(null);
    const [position, setPosition] = useState({ left: x, top: y });

    // Keep the menu inside the window.
    useLayoutEffect(() => {
        const box = ref.current?.getBoundingClientRect();
        if (!box) return;
        setPosition({
            left: Math.max(4, Math.min(x, window.innerWidth - box.width - 4)),
            top: Math.max(4, Math.min(y, window.innerHeight - box.height - 4)),
        });
    }, [x, y]);

    useEffect(() => {
        const close = (event: Event) => {
            if (event instanceof KeyboardEvent && event.key !== 'Escape') return;
            if (event.target instanceof Node && ref.current?.contains(event.target)) return;
            onClose();
        };
        window.addEventListener('mousedown', close, true);
        window.addEventListener('keydown', close, true);
        window.addEventListener('blur', onClose);
        window.addEventListener('resize', onClose);

        return () => {
            window.removeEventListener('mousedown', close, true);
            window.removeEventListener('keydown', close, true);
            window.removeEventListener('blur', onClose);
            window.removeEventListener('resize', onClose);
        };
    }, [onClose]);

    return (
        <Portal>
            <div
                ref={ref}
                role={'menu'}
                onContextMenu={(event) => event.preventDefault()}
                style={position}
                className={'fixed z-50 min-w-[13rem] rounded-lg border border-gray-600 bg-gray-800 p-1 shadow-2xl'}
            >
                {items.map((item, index) => (
                    <React.Fragment key={index}>
                        {item.separated && index > 0 && <div className={'my-1 border-t border-gray-700'} />}
                        <button
                            type={'button'}
                            role={'menuitem'}
                            onClick={() => {
                                onClose();
                                item.onClick();
                            }}
                            className={classNames(
                                'flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm',
                                item.danger
                                    ? 'text-red-400 hover:bg-red-500/10'
                                    : 'text-gray-200 hover:bg-gray-700 hover:text-gray-50'
                            )}
                        >
                            <item.icon className={'h-4 w-4 shrink-0'} />
                            <span className={'flex-1'}>{item.label}</span>
                            {item.shortcut && <span className={'text-2xs text-gray-500'}>{item.shortcut}</span>}
                        </button>
                    </React.Fragment>
                ))}
            </div>
        </Portal>
    );
};
