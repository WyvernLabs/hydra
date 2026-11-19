import React, { Suspense } from 'react';
import styled, { css, keyframes } from 'styled-components/macro';
import tw from 'twin.macro';
import ErrorBoundary from '@/components/elements/ErrorBoundary';
import { Hourglass } from 'lucide-react';
import type { SiteSettings } from '@/state/settings';

export type SpinnerSize = 'small' | 'base' | 'large';

interface Props {
    size?: SpinnerSize;
    centered?: boolean;
    isBlue?: boolean;
}

interface Spinner extends React.FC<Props> {
    Size: Record<'SMALL' | 'BASE' | 'LARGE', SpinnerSize>;
    Suspense: React.FC<Props>;
}

const spin = keyframes`
    to { transform: rotate(360deg); }
`;

// noinspection CssOverwrittenProperties
const SpinnerComponent = styled.div<Props>`
    ${tw`w-8 h-8`};
    border-width: 3px;
    border-radius: 50%;
    animation: ${spin} 1s cubic-bezier(0.55, 0.25, 0.25, 0.7) infinite;

    ${(props) =>
        props.size === 'small'
            ? tw`w-4 h-4 border-2`
            : props.size === 'large'
            ? css`
                  ${tw`w-16 h-16`};
                  border-width: 6px;
              `
            : null};

    border-color: ${(props) => (!props.isBlue ? 'rgba(255, 255, 255, 0.2)' : 'hsla(212, 92%, 43%, 0.2)')};
    border-top-color: ${(props) => (!props.isBlue ? 'rgb(255, 255, 255)' : 'rgb(var(--hydra-accent-500))')};
`;

// Hydra: animation picked under Designer > Loading animation. Read once from the
// page configuration, so spinners also work outside of the store provider.
const loader = (window as { SiteConfiguration?: Partial<SiteSettings> }).SiteConfiguration?.loader;

/**
 * Hydra: the templates of LoaderDesignService, drawn by ".hydra-loader" in
 * public/hydra/layout.css (same markup as partials/hydra/loader.blade.php).
 */
const HydraLoader = ({ size = 'base', isBlue }: Props) => {
    const style = loader?.style === 'gif' && !loader.gif ? 'ring' : loader?.style || 'ring';
    const className = `hydra-loader hydra-loader--${style} hydra-loader--${size}${
        isBlue ? ' hydra-loader--accent' : ''
    }`;
    const speed = { '--hydra-loader-speed': (loader?.speed || 100) / 100 } as React.CSSProperties;
    const dots = { dots: 3, shockwave: 3, pulse: 1, bars: 4 }[style as string] || 0;

    return (
        <span className={className} style={speed} role={'status'}>
            {style === 'gif' && <img src={loader!.gif!} alt={''} />}
            {style === 'hourglass' && <Hourglass />}
            {Array.from({ length: dots }, (_, i) => (
                <i key={i} />
            ))}
        </span>
    );
};

const Indicator = (props: Props) =>
    !loader || loader.style === 'ring' ? <SpinnerComponent {...props} /> : <HydraLoader {...props} />;

const Spinner: Spinner = ({ centered, ...props }) =>
    centered ? (
        <div css={[tw`flex justify-center items-center`, props.size === 'large' ? tw`m-20` : tw`m-6`]}>
            <Indicator {...props} />
        </div>
    ) : (
        <Indicator {...props} />
    );
Spinner.displayName = 'Spinner';

Spinner.Size = {
    SMALL: 'small',
    BASE: 'base',
    LARGE: 'large',
};

Spinner.Suspense = ({ children, centered = true, size = Spinner.Size.LARGE, ...props }) => (
    <Suspense fallback={<Spinner centered={centered} size={size} {...props} />}>
        <ErrorBoundary>{children}</ErrorBoundary>
    </Suspense>
);
Spinner.Suspense.displayName = 'Spinner.Suspense';

export default Spinner;
