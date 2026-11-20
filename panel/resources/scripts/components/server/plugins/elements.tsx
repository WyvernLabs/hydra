import React, { useEffect, useMemo, useState } from 'react';
import classNames from 'classnames';
import { Check, ChevronDown, LucideIcon } from 'lucide-react';

// Hydra: building blocks shared by the plugin manager (addon "plugins") and the
// mod manager (addon "mods"): project icon, badges and the sidebar radios.

// Icon of a project, loaded through the panel; a placeholder when there is none.
export const ProjectIcon = ({
    src,
    size = 'large',
    fallback: Fallback,
}: {
    src: string | null;
    size?: 'large' | 'small';
    fallback: LucideIcon;
}) => {
    const [failed, setFailed] = useState(false);
    const box = size === 'large' ? 'h-14 w-14' : 'h-10 w-10';

    useEffect(() => setFailed(false), [src]);

    return !src || failed ? (
        <div
            className={classNames(
                box,
                'flex shrink-0 items-center justify-center rounded-lg bg-gray-700 text-gray-400'
            )}
        >
            <Fallback className={'h-1/2 w-1/2'} />
        </div>
    ) : (
        <img
            src={src}
            alt={''}
            loading={'lazy'}
            onError={() => setFailed(true)}
            className={classNames(box, 'shrink-0 rounded-lg object-cover')}
        />
    );
};

export const Badge = ({
    tone,
    children,
    title,
}: {
    tone: 'accent' | 'yellow' | 'gray' | 'red';
    children: React.ReactNode;
    title?: string;
}) => (
    <span
        title={title}
        className={classNames('inline-flex items-center rounded px-1.5 py-px text-2xs font-semibold uppercase', {
            'bg-primary-500/20 text-primary-300': tone === 'accent',
            'bg-yellow-500/20 text-yellow-300': tone === 'yellow',
            'bg-gray-600/60 text-gray-300': tone === 'gray',
            'bg-red-500/20 text-red-300': tone === 'red',
        })}
    >
        {children}
    </span>
);

// Round radio marker of the sidebar: filled with a check when active, a dot when
// only something inside a collapsed version group is selected.
export const RadioMark = ({ state }: { state: 'on' | 'partial' | 'off' }) => (
    <span
        className={classNames(
            'flex h-4 w-4 shrink-0 items-center justify-center rounded-full border transition-colors',
            state === 'on'
                ? 'border-primary-400 bg-primary-500 text-white'
                : state === 'partial'
                ? 'border-primary-400 bg-transparent'
                : 'border-gray-500 bg-gray-900/40 group-hover:border-gray-300'
        )}
    >
        {state === 'on' && <Check className={'h-2.5 w-2.5'} strokeWidth={4} />}
        {state === 'partial' && <span className={'h-1.5 w-1.5 rounded-full bg-primary-400'} />}
    </span>
);

export const SideButton = ({
    active,
    disabled,
    title,
    onClick,
    children,
}: {
    active: boolean;
    disabled?: boolean;
    title?: string;
    onClick: () => void;
    children: React.ReactNode;
}) => (
    <button
        type={'button'}
        disabled={disabled}
        title={title}
        onClick={onClick}
        className={classNames(
            'group flex w-full items-center gap-2.5 rounded-md px-2 py-1.5 text-left text-sm transition-colors',
            active ? 'text-gray-50' : 'text-gray-300 hover:bg-white/5 hover:text-gray-50',
            disabled && 'cursor-not-allowed opacity-40 hover:bg-transparent hover:text-gray-300'
        )}
    >
        <RadioMark state={active ? 'on' : 'off'} />
        <span className={'truncate'}>{children}</span>
    </button>
);

export const SideSection = ({ title, children }: { title: React.ReactNode; children: React.ReactNode }) => (
    <section>
        <h3 className={'mb-2 px-2 text-sm font-semibold text-gray-100'}>{title}</h3>
        <div className={'space-y-0.5'}>{children}</div>
    </section>
);

// Minecraft versions grouped by major release (1.21 -> 1.21.4, 1.21.3, ...).
export const VersionPicker = ({
    versions,
    value,
    allLabel,
    onChange,
}: {
    versions: string[];
    value: string | null;
    allLabel: string;
    onChange: (version: string | null) => void;
}) => {
    const groups = useMemo(() => {
        const list: { key: string; versions: string[] }[] = [];
        const all = value && !versions.includes(value) ? [value, ...versions] : versions;
        all.forEach((version) => {
            const key = version.split('.').slice(0, 2).join('.');
            const group = list.find((g) => g.key === key);
            if (group) group.versions.push(version);
            else list.push({ key, versions: [version] });
        });

        return list;
    }, [versions, value]);

    const current = value ? value.split('.').slice(0, 2).join('.') : null;
    const [expanded, setExpanded] = useState<string | null>(current);

    return (
        <>
            <SideButton active={!value} onClick={() => onChange(null)}>
                {allLabel}
            </SideButton>
            {groups.map((group) => {
                const single = group.versions.length === 1;
                const isOpen = expanded === group.key && !single;
                const inside = !!value && group.versions.includes(value);

                return (
                    <div key={group.key}>
                        <button
                            type={'button'}
                            onClick={() =>
                                single ? onChange(group.versions[0]) : setExpanded(isOpen ? null : group.key)
                            }
                            className={classNames(
                                'group flex w-full items-center gap-2.5 rounded-md px-2 py-1.5 text-left text-sm transition-colors',
                                inside ? 'text-gray-50' : 'text-gray-300 hover:bg-white/5 hover:text-gray-50'
                            )}
                        >
                            <RadioMark state={inside ? (single || !isOpen ? 'on' : 'partial') : 'off'} />
                            <span className={'truncate'}>
                                {single ? group.versions[0] : group.key}
                                {inside && !single && !isOpen && (
                                    <span className={'ml-1.5 text-xs text-gray-400'}>{value}</span>
                                )}
                            </span>
                            {!single && (
                                <ChevronDown
                                    className={classNames(
                                        'h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform',
                                        isOpen && 'rotate-180'
                                    )}
                                />
                            )}
                        </button>
                        {isOpen && (
                            <div className={'mb-1 ml-[1.0625rem] space-y-0.5 border-l border-gray-600/70 pl-2'}>
                                {group.versions.map((version) => (
                                    <SideButton
                                        key={version}
                                        active={value === version}
                                        onClick={() => onChange(version)}
                                    >
                                        {version}
                                    </SideButton>
                                ))}
                            </div>
                        )}
                    </div>
                );
            })}
        </>
    );
};

export interface PickerCategory {
    key: string;
    slug: string;
    name: string;
    parent: string | null;
}

// "game-mechanics" -> "Game mechanics" for categories without a translation.
export const prettyCategory = (category: PickerCategory) =>
    category.name.includes(' ')
        ? category.name
        : (category.name.charAt(0).toUpperCase() + category.name.slice(1)).replace(/-/g, ' ');

// Categories of a source; CurseForge has subcategories that fold out.
export const CategoryPicker = ({
    categories,
    value,
    allLabel,
    label,
    onChange,
}: {
    categories: PickerCategory[];
    value: string | null;
    allLabel: string;
    label: (category: PickerCategory) => string;
    onChange: (category: string | null) => void;
}) => {
    const top = useMemo(
        () => categories.filter((c) => !c.parent || !categories.some((p) => p.key === c.parent)),
        [categories]
    );
    const children = (key: string) => categories.filter((c) => c.parent === key);
    const parentOfValue = categories.find((c) => c.key === value)?.parent || null;
    const [expanded, setExpanded] = useState<string | null>(parentOfValue);

    return (
        <>
            <SideButton active={!value} onClick={() => onChange(null)}>
                {allLabel}
            </SideButton>
            {top.map((category) => {
                const subs = children(category.key);
                const isOpen = expanded === category.key && subs.length > 0;
                const inside = subs.some((c) => c.key === value);

                return (
                    <div key={category.key}>
                        <div className={'flex items-center'}>
                            <button
                                type={'button'}
                                onClick={() => onChange(category.key)}
                                className={classNames(
                                    'group flex min-w-0 flex-1 items-center gap-2.5 rounded-md px-2 py-1.5 text-left text-sm transition-colors',
                                    value === category.key || inside
                                        ? 'text-gray-50'
                                        : 'text-gray-300 hover:bg-white/5 hover:text-gray-50'
                                )}
                            >
                                <RadioMark state={value === category.key ? 'on' : inside ? 'partial' : 'off'} />
                                <span className={'truncate'}>{label(category)}</span>
                            </button>
                            {subs.length > 0 && (
                                <button
                                    type={'button'}
                                    onClick={() => setExpanded(isOpen ? null : category.key)}
                                    className={'rounded p-1 text-gray-400 hover:bg-white/5 hover:text-gray-100'}
                                >
                                    <ChevronDown
                                        className={classNames(
                                            'h-3.5 w-3.5 transition-transform',
                                            isOpen && 'rotate-180'
                                        )}
                                    />
                                </button>
                            )}
                        </div>
                        {isOpen && (
                            <div className={'mb-1 ml-[1.0625rem] space-y-0.5 border-l border-gray-600/70 pl-2'}>
                                {subs.map((sub) => (
                                    <SideButton
                                        key={sub.key}
                                        active={value === sub.key}
                                        onClick={() => onChange(sub.key)}
                                    >
                                        {label(sub)}
                                    </SideButton>
                                ))}
                            </div>
                        )}
                    </div>
                );
            })}
        </>
    );
};
