import React, { useEffect, useState } from 'react';
import { ServerContext } from '@/state/server';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import reinstallServer from '@/api/server/reinstallServer';
import { Actions, useStoreActions } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import { httpErrorToHuman } from '@/api/http';
import tw from 'twin.macro';
import { Button } from '@/components/elements/button/index';
import { Dialog } from '@/components/elements/dialog';
import { useTranslation } from 'react-i18next';

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { t } = useTranslation('client/server');
    const skipScripts = ServerContext.useStoreState((state) => state.server.data!.skipScripts);
    const [modalVisible, setModalVisible] = useState(false);
    const { addFlash, clearFlashes } = useStoreActions((actions: Actions<ApplicationStore>) => actions.flashes);

    const reinstall = () => {
        clearFlashes('settings');
        reinstallServer(uuid)
            .then(() => {
                addFlash({
                    key: 'settings',
                    type: 'success',
                    message: t('settings.reinstall_started'),
                });
            })
            .catch((error) => {
                console.error(error);

                addFlash({ key: 'settings', type: 'error', message: httpErrorToHuman(error) });
            })
            .then(() => setModalVisible(false));
    };

    useEffect(() => {
        clearFlashes();
    }, []);

    if (skipScripts) {
        return (
            <TitledGreyBox title={t('settings.reinstall_title')}>
                <p css={tw`text-sm`}>{t('settings.reinstall_disabled')}</p>
            </TitledGreyBox>
        );
    }

    return (
        <TitledGreyBox title={t('settings.reinstall_title')} css={tw`relative`}>
            <Dialog.Confirm
                open={modalVisible}
                title={t('settings.reinstall_confirm_title')}
                confirm={t('settings.reinstall_confirm')}
                onClose={() => setModalVisible(false)}
                onConfirmed={reinstall}
            >
                {t('settings.reinstall_confirm_text')}
            </Dialog.Confirm>
            <p css={tw`text-sm`}>
                {t('settings.reinstall_text')}&nbsp;
                <strong css={tw`font-medium`}>{t('settings.reinstall_warning')}</strong>
            </p>
            <div css={tw`mt-6 text-right`}>
                <Button.Danger variant={Button.Variants.Secondary} onClick={() => setModalVisible(true)}>
                    {t('settings.reinstall_button')}
                </Button.Danger>
            </div>
        </TitledGreyBox>
    );
};
