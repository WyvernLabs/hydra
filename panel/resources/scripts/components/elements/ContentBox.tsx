import React from 'react';
import FlashMessageRender from '@/components/FlashMessageRender';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import classNames from 'classnames';

type Props = Readonly<
    React.DetailedHTMLProps<React.HTMLAttributes<HTMLDivElement>, HTMLDivElement> & {
        title?: string;
        borderColor?: string;
        showFlashes?: string | boolean;
        showLoadingOverlay?: boolean;
    }
>;

// Hydra: a box in the style of the sidebar, with the title in a header strip.
const ContentBox = ({
    title,
    borderColor: _,
    showFlashes,
    showLoadingOverlay,
    children,
    className,
    ...props
}: Props) => (
    <section className={classNames('hydra-box', className)} {...props}>
        <SpinnerOverlay visible={showLoadingOverlay || false} />
        {title && <h3 className={'hydra-box-header'}>{title}</h3>}
        <div className={'hydra-box-body'}>
            {showFlashes && (
                <FlashMessageRender
                    byKey={typeof showFlashes === 'string' ? showFlashes : undefined}
                    className={'mb-4'}
                />
            )}
            {children}
        </div>
    </section>
);

export default ContentBox;
