import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import tw from 'twin.macro';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import { Button } from '@/components/elements/button/index';
import ImportServerDialog from '@/components/server/settings/ImportServerDialog';

// Hydra: box on the settings page that opens the import of another server
// over SFTP (addon "importer").

export default () => {
    const { t } = useTranslation('client/import');
    const [open, setOpen] = useState(false);

    return (
        <TitledGreyBox title={t('box_title')} css={tw`mb-6 md:mb-10`}>
            <p css={tw`text-sm`}>{t('box_text')}</p>
            <div css={tw`mt-6 text-right`}>
                <Button onClick={() => setOpen(true)}>{t('box_button')}</Button>
            </div>
            <ImportServerDialog open={open} onClose={() => setOpen(false)} />
        </TitledGreyBox>
    );
};
