import * as React from 'react';
import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import requestPasswordResetEmail from '@/api/auth/requestPasswordResetEmail';
import { httpErrorToHuman } from '@/api/http';
import LoginFormContainer from '@/components/auth/LoginFormContainer';
import Field from '@/components/elements/Field';
import { Formik, FormikHelpers } from 'formik';
import { object, string } from 'yup';
import tw from 'twin.macro';
import Button from '@/components/elements/Button';
import Turnstile, { TurnstileHandle, useTurnstile } from '@/components/auth/Turnstile';
import useFlash from '@/plugins/useFlash';
import { useTranslation } from 'react-i18next';

interface Values {
    email: string;
}

export default () => {
    const ref = useRef<TurnstileHandle>(null);
    const { t } = useTranslation('client/auth');
    const [token, setToken] = useState('');

    const { clearFlashes, addFlash } = useFlash();
    const turnstile = useTurnstile('password');

    useEffect(() => {
        clearFlashes();
    }, []);

    const handleSubmission = ({ email }: Values, { setSubmitting, resetForm }: FormikHelpers<Values>) => {
        clearFlashes();

        if (turnstile.enabled && !token) {
            setSubmitting(false);
            addFlash({ type: 'error', title: t('client/common:error'), message: t('captcha.required') });

            return;
        }

        requestPasswordResetEmail(email, token)
            .then((response) => {
                resetForm();
                addFlash({ type: 'success', title: t('client/common:success'), message: response });
            })
            .catch((error) => {
                console.error(error);
                addFlash({ type: 'error', title: t('client/common:error'), message: httpErrorToHuman(error) });
            })
            .then(() => {
                if (ref.current) ref.current.reset();

                setSubmitting(false);
            });
    };

    return (
        <Formik
            onSubmit={handleSubmission}
            initialValues={{ email: '' }}
            validationSchema={object().shape({
                email: string().email(t('forgot.email_invalid')).required(t('forgot.email_invalid')),
            })}
        >
            {({ isSubmitting }) => (
                <LoginFormContainer title={t('forgot.title')}>
                    <Field
                        label={t('forgot.email')}
                        description={t('forgot.email_help')}
                        name={'email'}
                        type={'email'}
                    />
                    {turnstile.enabled && <Turnstile ref={ref} siteKey={turnstile.siteKey} onToken={setToken} />}
                    <div css={tw`mt-6`}>
                        <Button type={'submit'} size={'xlarge'} disabled={isSubmitting} isLoading={isSubmitting}>
                            {t('forgot.submit')}
                        </Button>
                    </div>
                    <div css={tw`mt-6 text-center`}>
                        <Link
                            to={'/auth/login'}
                            css={tw`text-xs text-neutral-500 tracking-wide uppercase no-underline hover:text-neutral-700`}
                        >
                            {t('return_to_login')}
                        </Link>
                    </div>
                </LoginFormContainer>
            )}
        </Formik>
    );
};
