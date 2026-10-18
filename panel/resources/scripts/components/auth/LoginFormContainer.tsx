import React, { forwardRef } from 'react';
import { Form } from 'formik';
import styled from 'styled-components/macro';
import { breakpoint } from '@/theme';
import FlashMessageRender from '@/components/FlashMessageRender';
import tw from 'twin.macro';
import { useStoreState } from '@/state/hooks';

type Props = React.DetailedHTMLProps<React.FormHTMLAttributes<HTMLFormElement>, HTMLFormElement> & {
    title?: string;
};

const Container = styled.div`
    ${breakpoint('sm')`
        ${tw`w-4/5 mx-auto`}
    `};

    ${breakpoint('md')`
        ${tw`p-10`}
    `};

    ${breakpoint('lg')`
        ${tw`w-2/5`}
    `};

    ${breakpoint('xl')`
        ${tw`w-full`}
        max-width: 440px;
    `};
`;

export default forwardRef<HTMLFormElement, Props>(({ title, ...props }, ref) => {
    const name = useStoreState((state) => state.settings.data!.name);
    const logo = useStoreState((state) => state.settings.data!.branding.logo);

    return (
        <Container>
            <img src={logo} alt={name} className={'hydra-auth-logo'} />
            <FlashMessageRender css={tw`mb-2 px-1`} />
            <Form {...props} ref={ref}>
                <div className={'hydra-card'} css={tw`p-6 md:p-8 mx-1`}>
                    {title && <h2 css={tw`text-xl text-neutral-100 font-semibold mb-6`}>{title}</h2>}
                    {props.children}
                </div>
            </Form>
            <p className={'hydra-footer'}>
                {name} &copy; {new Date().getFullYear()} &middot; Powered by Hydra
            </p>
        </Container>
    );
});
