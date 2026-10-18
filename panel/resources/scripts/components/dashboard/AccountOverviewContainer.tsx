import * as React from 'react';
import { useTranslation } from 'react-i18next';
import ContentBox from '@/components/elements/ContentBox';
import UpdatePasswordForm from '@/components/dashboard/forms/UpdatePasswordForm';
import UpdateEmailAddressForm from '@/components/dashboard/forms/UpdateEmailAddressForm';
import UpdateLanguageForm from '@/components/dashboard/forms/UpdateLanguageForm';
import ConfigureTwoFactorForm from '@/components/dashboard/forms/ConfigureTwoFactorForm';
import PageContentBlock from '@/components/elements/PageContentBlock';
import tw from 'twin.macro';
import MessageBox from '@/components/MessageBox';
import { useLocation } from 'react-router-dom';

export default () => {
    const { t } = useTranslation('client/account');
    const { state } = useLocation<undefined | { twoFactorRedirect?: boolean }>();

    return (
        <PageContentBlock title={t('overview.title')}>
            {state?.twoFactorRedirect && (
                <MessageBox title={t('overview.two_factor_required_title')} type={'error'}>
                    {t('overview.two_factor_required')}
                </MessageBox>
            )}

            <div
                css={[tw`grid grid-cols-1 lg:grid-cols-2 gap-8 mb-10`, state?.twoFactorRedirect ? tw`mt-4` : tw`mt-0`]}
            >
                <ContentBox title={t('password.title')} showFlashes={'account:password'}>
                    <UpdatePasswordForm />
                </ContentBox>
                <ContentBox title={t('email.title')} showFlashes={'account:email'}>
                    <UpdateEmailAddressForm />
                </ContentBox>
                <ContentBox title={t('two_factor.title')}>
                    <ConfigureTwoFactorForm />
                </ContentBox>
                <ContentBox title={t('language.title')} showFlashes={'account:language'}>
                    <UpdateLanguageForm />
                </ContentBox>
            </div>
        </PageContentBlock>
    );
};
