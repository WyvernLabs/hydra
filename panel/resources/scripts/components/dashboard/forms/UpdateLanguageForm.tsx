import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import tw from 'twin.macro';
import { useStoreActions, useStoreState } from '@/state/hooks';
import updateAccountLanguage from '@/api/account/updateAccountLanguage';
import { httpErrorToHuman } from '@/api/http';
import Label from '@/components/elements/Label';
import Select from '@/components/elements/Select';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';

export default () => {
    const { t } = useTranslation('client/account');
    const languages = useStoreState((state) => state.settings.data!.languages);
    const current = useStoreState((state) => state.user.data!.language);
    const updateUserData = useStoreActions((actions) => actions.user.updateUserData);
    const { clearFlashes, addFlash } = useStoreActions((actions) => actions.flashes);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const onChange = (language: string) => {
        clearFlashes('account:language');
        setIsSubmitting(true);

        updateAccountLanguage(language)
            .then(() => {
                updateUserData({ language });
                // Reload so that everything follows the new language: server rendered texts,
                // error messages from the API and date formats included.
                window.location.reload();
            })
            .catch((error) => {
                addFlash({
                    type: 'error',
                    key: 'account:language',
                    title: t('client/common:error'),
                    message: httpErrorToHuman(error),
                });
                setIsSubmitting(false);
            });
    };

    return (
        <div css={tw`relative`}>
            <SpinnerOverlay size={'large'} visible={isSubmitting} />
            <Label htmlFor={'account_language'}>{t('language.label')}</Label>
            <Select
                id={'account_language'}
                value={current}
                disabled={isSubmitting}
                onChange={(e) => onChange(e.currentTarget.value)}
            >
                {Object.entries(languages).map(([code, name]) => (
                    <option key={code} value={code}>
                        {name}
                    </option>
                ))}
            </Select>
            <p css={tw`mt-2 text-xs text-neutral-400`}>{t('language.description')}</p>
        </div>
    );
};
