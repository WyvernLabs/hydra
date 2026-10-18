import React, { memo } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { IconProp } from '@fortawesome/fontawesome-svg-core';
import tw from 'twin.macro';
import isEqual from 'react-fast-compare';
import classNames from 'classnames';

interface Props {
    icon?: IconProp;
    title: string | React.ReactNode;
    className?: string;
    children: React.ReactNode;
}

const TitledGreyBox = ({ icon, title, children, className }: Props) => (
    <div className={classNames('hydra-card', className)}>
        <div className={'hydra-card-header'}>
            {typeof title === 'string' ? (
                <p>
                    {icon && <FontAwesomeIcon icon={icon} css={tw`mr-2 text-accent-500`} />}
                    {title}
                </p>
            ) : (
                title
            )}
        </div>
        <div className={'hydra-card-body'}>{children}</div>
    </div>
);

export default memo(TitledGreyBox, isEqual);
