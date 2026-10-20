import React from 'react';
import { useStoreState } from '@/state/hooks';

// Hydra: a Lucide icon picked by name in the admin area (navigation, links).
// The Panel sends the drawing data of every picked icon along with the site
// configuration (window.SiteConfiguration.icons), so the whole icon set does
// not have to be part of the bundle.

export type IconNode = Array<[string, Record<string, string>]>;

interface Props {
    name: string;
    className?: string;
    style?: React.CSSProperties;
}

// "fill-rule" and friends have to be camelCase in React.
const toReactProps = (attributes: Record<string, string>) =>
    Object.fromEntries(
        Object.entries(attributes).map(([key, value]) => [key.replace(/-([a-z])/g, (_, c) => c.toUpperCase()), value])
    );

const HydraIcon = ({ name, className, style }: Props) => {
    const nodes = useStoreState((state) => state.settings.data!.icons?.[name] || state.settings.data!.icons?.circle);

    return (
        <svg
            xmlns={'http://www.w3.org/2000/svg'}
            width={24}
            height={24}
            viewBox={'0 0 24 24'}
            fill={'none'}
            stroke={'currentColor'}
            strokeWidth={2}
            strokeLinecap={'round'}
            strokeLinejoin={'round'}
            aria-hidden={'true'}
            className={className}
            style={style}
        >
            {(nodes || []).map(([tag, attributes], index) =>
                React.createElement(tag, { key: index, ...toReactProps(attributes) })
            )}
        </svg>
    );
};

/**
 * Returns a component that draws the given icon, for places that expect an
 * icon component (e.g. the sidebar).
 */
export const iconComponent =
    (name: string) =>
    // eslint-disable-next-line react/display-name
    (props: Omit<Props, 'name'>) =>
        <HydraIcon name={name} {...props} />;

export default HydraIcon;
