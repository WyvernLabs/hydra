import React, { forwardRef } from 'react';
import { Form } from 'formik';
import styled from 'styled-components/macro';
import classNames from 'classnames';
import FlashMessageRender from '@/components/FlashMessageRender';
import tw from 'twin.macro';
import { useStoreState } from '@/state/hooks';
import Footer from '@/components/hydra/Footer';
import { protectedClass } from '@/lib/protectedImage';
import { useDesignerBackground, useLoginDesign } from '@/lib/designer';

// Hydra: in the designer preview the form only shows; the browser must not fill in saved logins.
const isPreview = () => document.documentElement.classList.contains('hydra-designer-frame');

const PreviewLock = ({ children }: { children: React.ReactNode }) =>
    isPreview() ? (
        <fieldset disabled className={'hydra-login-preview-lock'}>
            {children}
        </fieldset>
    ) : (
        <>{children}</>
    );

type Props = React.DetailedHTMLProps<React.FormHTMLAttributes<HTMLFormElement>, HTMLFormElement> & {
    title?: string;
    // Hydra: only the login page has these (Designer > Login).
    subtitle?: string | null;
    info?: string | null;
};

// Centered column, the same width on every screen size above mobile.
const Container = styled.div`
    ${tw`w-full mx-auto px-4`};
    max-width: 472px;
`;

/**
 * Frame of every page for visitors (login, password, register, 2FA) in the
 * style picked under Designer > Login: classic card, split card with a
 * picture, or the form on one side of the screen with a picture on the other.
 */
export default forwardRef<HTMLFormElement, Props>(({ title, subtitle, info, ...props }, ref) => {
    const name = useStoreState((state) => state.settings.data!.name);
    const branding = useStoreState((state) => state.settings.data!.branding);
    const loginBackground = useStoreState((state) => state.settings.data!.backgrounds?.login);
    const design = useLoginDesign();
    const background = useDesignerBackground('login', loginBackground);

    const logo = (
        <img
            src={branding.logo}
            alt={name}
            className={`hydra-auth-logo ${protectedClass(branding)}`}
            draggable={false}
        />
    );
    const infoBox = info ? <div className={'hydra-login-info'}>{info}</div> : null;

    if (design.style === 'classic') {
        return (
            <Container>
                {logo}
                <FlashMessageRender css={tw`mb-2 px-1`} />
                <Form {...props} ref={ref}>
                    <div className={'hydra-card'} css={tw`w-full p-6 md:p-8`}>
                        {title && (
                            <h2 css={tw`text-xl text-neutral-100 font-semibold`} className={subtitle ? 'mb-1' : 'mb-6'}>
                                {title}
                            </h2>
                        )}
                        {subtitle && <p className={'hydra-login-subtitle mb-6'}>{subtitle}</p>}
                        <PreviewLock>{props.children}</PreviewLock>
                    </div>
                </Form>
                {infoBox && <div className={'mt-4'}>{infoBox}</div>}
                <Footer />
            </Container>
        );
    }

    // The own picture of the login page, otherwise its background.
    const image = design.image || background?.image || null;

    return (
        <div className={classNames(`hydra-login-${design.style}`, design.mirror && 'is-mirrored')}>
            <div className={`hydra-login-${design.style}-form`}>
                <div className={'hydra-login-column'}>
                    {logo}
                    <FlashMessageRender css={tw`mb-2`} />
                    {title && <h1 className={'hydra-login-title'}>{title}</h1>}
                    {subtitle && <p className={'hydra-login-subtitle'}>{subtitle}</p>}
                    <Form {...props} ref={ref} className={'mt-6'}>
                        <PreviewLock>{props.children}</PreviewLock>
                    </Form>
                    {infoBox && <div className={'mt-6'}>{infoBox}</div>}
                    <Footer />
                </div>
            </div>
            <div
                className={classNames(`hydra-login-${design.style}-image`, !image && 'is-empty')}
                style={image ? { backgroundImage: `url("${image}")` } : undefined}
                aria-hidden
            />
        </div>
    );
});
