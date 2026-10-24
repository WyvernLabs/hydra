import React from 'react';
import ContentContainer from '@/components/elements/ContentContainer';
import { CSSTransition } from 'react-transition-group';
import tw from 'twin.macro';
import Footer from '@/components/hydra/Footer';
import FlashMessageRender from '@/components/FlashMessageRender';
import { useDocumentTitle } from '@/components/hydra/tabs/context';

export interface PageContentBlockProps {
    title?: string;
    className?: string;
    showFlashKey?: string;
}

const PageContentBlock: React.FC<PageContentBlockProps> = ({ title, showFlashKey, className, children }) => {
    // Hydra: in the installed app this names the tab.
    useDocumentTitle(title);

    return (
        <CSSTransition timeout={150} classNames={'fade'} appear in>
            <>
                <ContentContainer className={className}>
                    {showFlashKey && <FlashMessageRender byKey={showFlashKey} css={tw`mb-4`} />}
                    {children}
                </ContentContainer>
                <ContentContainer>
                    <Footer />
                </ContentContainer>
            </>
        </CSSTransition>
    );
};

export default PageContentBlock;
