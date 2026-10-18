import styled from 'styled-components/macro';
import tw from 'twin.macro';

const Label = styled.label<{ isLight?: boolean }>`
    ${tw`block text-xs uppercase font-semibold tracking-wider text-neutral-400 mb-1 sm:mb-2`};
`;

export default Label;
