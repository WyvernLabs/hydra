import React, { useState } from 'react';
import { Subuser } from '@/state/server/subusers';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faPencilAlt, faUnlockAlt, faUserLock } from '@fortawesome/free-solid-svg-icons';
import RemoveSubuserButton from '@/components/server/users/RemoveSubuserButton';
import EditSubuserModal from '@/components/server/users/EditSubuserModal';
import Can from '@/components/elements/Can';
import { useStoreState } from 'easy-peasy';
import tw from 'twin.macro';
import GreyRowBox from '@/components/elements/GreyRowBox';
import { useTranslation } from 'react-i18next';

interface Props {
    subuser: Subuser;
}

export default ({ subuser }: Props) => {
    const uuid = useStoreState((state) => state.user!.data!.uuid);
    const [visible, setVisible] = useState(false);
    const { t } = useTranslation('client/server');

    return (
        <GreyRowBox css={tw`mb-2`}>
            <EditSubuserModal subuser={subuser} visible={visible} onModalDismissed={() => setVisible(false)} />
            <div
                css={tw`w-10 h-10 rounded-full bg-neutral-700 border-2 border-neutral-800 overflow-hidden hidden md:block`}
            >
                <img css={tw`w-full h-full`} src={`${subuser.image}?s=400`} />
            </div>
            <div css={tw`ml-4 flex-1 overflow-hidden`}>
                <p css={tw`text-sm truncate`}>{subuser.email}</p>
                {subuser.allowedPaths.length > 0 && (
                    <p css={tw`text-xs text-neutral-400 truncate`} title={subuser.allowedPaths.join(', ')}>
                        {t('users.folders_limited', { count: subuser.allowedPaths.length })}:{' '}
                        <span css={tw`font-mono`}>{subuser.allowedPaths.join(', ')}</span>
                    </p>
                )}
            </div>
            <div css={tw`ml-4`}>
                <p css={tw`font-medium text-center`}>
                    &nbsp;
                    <FontAwesomeIcon
                        icon={subuser.twoFactorEnabled ? faUserLock : faUnlockAlt}
                        fixedWidth
                        css={!subuser.twoFactorEnabled ? tw`text-red-400` : undefined}
                    />
                    &nbsp;
                </p>
                <p css={tw`text-2xs text-neutral-500 uppercase hidden md:block`}>{t('users.two_factor')}</p>
            </div>
            <div css={tw`ml-4 hidden md:block`}>
                <p css={tw`font-medium text-center`}>
                    {subuser.permissions.filter((permission) => permission !== 'websocket.connect').length}
                </p>
                <p css={tw`text-2xs text-neutral-500 uppercase`}>{t('users.permissions')}</p>
            </div>
            {subuser.uuid !== uuid && (
                <>
                    <Can action={'user.update'}>
                        <button
                            type={'button'}
                            aria-label={t('users.edit_label')}
                            css={tw`block text-sm p-1 md:p-2 text-neutral-500 hover:text-neutral-100 transition-colors duration-150 mx-4`}
                            onClick={() => setVisible(true)}
                        >
                            <FontAwesomeIcon icon={faPencilAlt} />
                        </button>
                    </Can>
                    <Can action={'user.delete'}>
                        <RemoveSubuserButton subuser={subuser} />
                    </Can>
                </>
            )}
        </GreyRowBox>
    );
};
