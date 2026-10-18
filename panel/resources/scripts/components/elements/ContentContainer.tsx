import styled from 'styled-components/macro';
import tw from 'twin.macro';

// Hydra: the sidebar layout already provides the outer spacing, the content
// only gets a maximum width so it does not stretch endlessly on wide screens.
const ContentContainer = styled.div`
    max-width: 1440px;
    ${tw`mx-auto`};
`;
ContentContainer.displayName = 'ContentContainer';

export default ContentContainer;
