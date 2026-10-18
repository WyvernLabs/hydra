import React, { forwardRef } from 'react';
import { Form } from 'formik';
import styled from 'styled-components/macro';
import FlashMessageRender from '@/components/FlashMessageRender';
import tw from 'twin.macro';
import { useStoreState } from '@/state/hooks';
import Footer from '@/components/hydra/Footer';

type Props = React.DetailedHTMLProps<React.FormHTMLAttributes<HTMLFormElement>, HTMLFormElement> & {
    title?: string;
};

// Centered column, the same width on every screen size above mobile.
const Container = styled.div`
    ${tw`w-full mx-auto px-4`};
    max-width: 472px;
`;

export default forwardRef<HTMLFormElement, Props>(({ title, ...props }, ref) => {
    const name = useStoreState((state) => state.settings.data!.name);
    const logo = useStoreState((state) => state.settings.data!.branding.logo);

    return (
        <Container>
            <img src={logo} alt={name} className={'hydra-auth-logo'} />
            <FlashMessageRender css={tw`mb-2 px-1`} />
            <Form {...props} ref={ref}>
                <div className={'hydra-card'} css={tw`w-full p-6 md:p-8`}>
                    {title && <h2 css={tw`text-xl text-neutral-100 font-semibold mb-6`}>{title}</h2>}
                    {props.children}
                </div>
            </Form>
            <Footer />
        </Container>
    );
});
