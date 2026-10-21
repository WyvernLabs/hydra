import React, { useEffect, useRef, useState } from 'react';
import { Link, RouteComponentProps } from 'react-router-dom';
import login from '@/api/auth/login';
import LoginFormContainer from '@/components/auth/LoginFormContainer';
import { useStoreState } from 'easy-peasy';
import { Formik, FormikHelpers } from 'formik';
import { object, string } from 'yup';
import Field from '@/components/elements/Field';
import tw from 'twin.macro';
import Button from '@/components/elements/Button';
import Turnstile, { TurnstileHandle, useTurnstile } from '@/components/auth/Turnstile';
import useFlash from '@/plugins/useFlash';
import { useTranslation } from 'react-i18next';

interface Values {
    username: string;
    password: string;
}

const LoginContainer = ({ history }: RouteComponentProps) => {
    const ref = useRef<TurnstileHandle>(null);
    const { t } = useTranslation('client/auth');
    const [token, setToken] = useState('');

    const { clearFlashes, clearAndAddHttpError, addFlash } = useFlash();
    const turnstile = useTurnstile('login');
    const registration = useStoreState((state) => state.settings.data!.registration);

    useEffect(() => {
        clearFlashes();

        // Hydra: the session was ended because the account got banned.
        if (new URLSearchParams(window.location.search).has('banned')) {
            addFlash({ type: 'error', title: t('client/common:error'), message: t('login.banned_notice') });
        }
    }, []);

    const onSubmit = (values: Values, { setSubmitting }: FormikHelpers<Values>) => {
        clearFlashes();

        if (turnstile.enabled && !token) {
            setSubmitting(false);
            addFlash({ type: 'error', title: t('client/common:error'), message: t('captcha.required') });

            return;
        }

        login({ ...values, captcha: token })
            .then((response) => {
                if (response.complete) {
                    // @ts-expect-error this is valid
                    window.location = response.intended || '/';
                    return;
                }

                history.replace('/auth/login/checkpoint', { token: response.confirmationToken });
            })
            .catch((error) => {
                console.error(error);

                if (ref.current) ref.current.reset();

                setSubmitting(false);
                clearAndAddHttpError({ error });
            });
    };

    return (
        <Formik
            onSubmit={onSubmit}
            initialValues={{ username: '', password: '' }}
            validationSchema={object().shape({
                username: string().required(t('login.username_required')),
                password: string().required(t('login.password_required')),
            })}
        >
            {({ isSubmitting }) => (
                <LoginFormContainer title={t('login.title')}>
                    <Field type={'text'} label={t('login.username')} name={'username'} disabled={isSubmitting} />
                    <div css={tw`mt-6`}>
                        <Field
                            type={'password'}
                            label={t('login.password')}
                            name={'password'}
                            disabled={isSubmitting}
                        />
                    </div>
                    {turnstile.enabled && <Turnstile ref={ref} siteKey={turnstile.siteKey} onToken={setToken} />}
                    <div css={tw`mt-6`}>
                        <Button type={'submit'} size={'xlarge'} isLoading={isSubmitting} disabled={isSubmitting}>
                            {t('login.submit')}
                        </Button>
                    </div>
                    {registration && (
                        <div css={tw`mt-4`}>
                            <p css={tw`text-center text-xs text-neutral-400 mb-2`}>{t('login.no_account')}</p>
                            <Link
                                to={'/auth/register'}
                                css={tw`block w-full p-4 rounded border border-neutral-600 bg-transparent text-center text-sm font-semibold text-neutral-200 no-underline transition-all duration-150 hover:border-neutral-500 hover:text-neutral-100`}
                            >
                                {t('login.register')}
                            </Link>
                        </div>
                    )}
                    <div css={tw`mt-6 text-center`}>
                        <Link
                            to={'/auth/password'}
                            css={tw`text-xs text-neutral-500 tracking-wide no-underline uppercase hover:text-neutral-600`}
                        >
                            {t('login.forgot_password')}
                        </Link>
                    </div>
                </LoginFormContainer>
            )}
        </Formik>
    );
};

export default LoginContainer;
