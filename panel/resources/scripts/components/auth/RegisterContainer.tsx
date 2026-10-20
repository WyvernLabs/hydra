import React, { useEffect, useRef, useState } from 'react';
import { Link, Redirect } from 'react-router-dom';
import { Formik, FormikHelpers } from 'formik';
import { object, ref as yupRef, string } from 'yup';
import tw from 'twin.macro';
import { useTranslation } from 'react-i18next';
import { useStoreState } from '@/state/hooks';
import register, { requestRegistrationCode, verifyRegistrationCode } from '@/api/auth/register';
import LoginFormContainer from '@/components/auth/LoginFormContainer';
import Turnstile, { TurnstileHandle, useTurnstile } from '@/components/auth/Turnstile';
import Field from '@/components/elements/Field';
import Button from '@/components/elements/Button';
import useFlash from '@/plugins/useFlash';

// Hydra: registration in three steps. 1. the email address gets a code,
// 2. the code confirms the address, 3. name, username and password create
// the account, which is logged in right away.

type Step = 'email' | 'code' | 'details';

// Same rule as Pterodactyl\Rules\Username.
const USERNAME_REGEX = /^[a-z0-9]([\w.-]+)[a-z0-9]$/i;

const linkStyle = tw`text-xs text-neutral-500 tracking-wide no-underline uppercase hover:text-neutral-400 bg-transparent border-0 p-0 cursor-pointer`;

const LoginLink = () => {
    const { t } = useTranslation('client/auth');

    return (
        <div css={tw`mt-6 text-center`}>
            <Link to={'/auth/login'} css={linkStyle}>
                {t('register.have_account')}
            </Link>
        </div>
    );
};

const EmailStep = ({ email, onSent }: { email: string; onSent: (email: string) => void }) => {
    const ref = useRef<TurnstileHandle>(null);
    const { t } = useTranslation('client/auth');
    const [token, setToken] = useState('');
    const { clearFlashes, clearAndAddHttpError, addFlash } = useFlash();
    const turnstile = useTurnstile('register');

    const submit = (values: { email: string }, { setSubmitting }: FormikHelpers<{ email: string }>) => {
        clearFlashes();

        if (turnstile.enabled && !token) {
            setSubmitting(false);
            addFlash({ type: 'error', title: t('client/common:error'), message: t('captcha.required') });

            return;
        }

        requestRegistrationCode(values.email, token)
            .then(() => onSent(values.email))
            .catch((error) => {
                console.error(error);
                if (ref.current) ref.current.reset();
                setSubmitting(false);
                clearAndAddHttpError({ error });
            });
    };

    return (
        <Formik
            onSubmit={submit}
            initialValues={{ email }}
            validationSchema={object().shape({
                email: string().email(t('register.email_invalid')).required(t('register.email_invalid')),
            })}
        >
            {({ isSubmitting }) => (
                <LoginFormContainer title={t('register.title')}>
                    <Field
                        type={'email'}
                        name={'email'}
                        label={t('register.email')}
                        description={t('register.email_help')}
                        autoComplete={'email'}
                        disabled={isSubmitting}
                    />
                    {turnstile.enabled && <Turnstile ref={ref} siteKey={turnstile.siteKey} onToken={setToken} />}
                    <div css={tw`mt-6`}>
                        <Button type={'submit'} size={'xlarge'} isLoading={isSubmitting} disabled={isSubmitting}>
                            {t('register.send_code')}
                        </Button>
                    </div>
                    <LoginLink />
                </LoginFormContainer>
            )}
        </Formik>
    );
};

const CodeStep = ({ email, onBack, onVerified }: { email: string; onBack: () => void; onVerified: () => void }) => {
    const { t } = useTranslation('client/auth');
    const { clearFlashes, clearAndAddHttpError, addFlash } = useFlash();
    const turnstile = useTurnstile('register');
    const [resending, setResending] = useState(false);

    const submit = (values: { code: string }, { setSubmitting }: FormikHelpers<{ code: string }>) => {
        clearFlashes();

        verifyRegistrationCode(email, values.code)
            .then(() => onVerified())
            .catch((error) => {
                console.error(error);
                setSubmitting(false);
                clearAndAddHttpError({ error });
            });
    };

    // With Turnstile a new code needs a new check, which lives on the first step.
    const resend = () => {
        clearFlashes();
        if (turnstile.enabled) {
            onBack();
            return;
        }

        setResending(true);
        requestRegistrationCode(email)
            .then(() =>
                addFlash({ type: 'success', title: t('client/common:success'), message: t('register.code_resent') })
            )
            .catch((error) => clearAndAddHttpError({ error }))
            .then(() => setResending(false));
    };

    return (
        <Formik
            onSubmit={submit}
            initialValues={{ code: '' }}
            validationSchema={object().shape({
                code: string()
                    .matches(/^\d{6}$/, t('register.code_invalid'))
                    .required(t('register.code_invalid')),
            })}
        >
            {({ isSubmitting }) => (
                <LoginFormContainer title={t('register.code_title')}>
                    <Field
                        type={'text'}
                        name={'code'}
                        label={t('register.code')}
                        description={t('register.code_help', { email })}
                        inputMode={'numeric'}
                        autoComplete={'one-time-code'}
                        maxLength={6}
                        autoFocus
                        disabled={isSubmitting}
                        css={tw`text-center text-xl tracking-widest font-mono`}
                    />
                    <div css={tw`mt-6`}>
                        <Button type={'submit'} size={'xlarge'} isLoading={isSubmitting} disabled={isSubmitting}>
                            {t('register.verify')}
                        </Button>
                    </div>
                    <div css={tw`mt-6 flex justify-between gap-4`}>
                        <button type={'button'} css={linkStyle} onClick={onBack} disabled={isSubmitting}>
                            {t('register.change_email')}
                        </button>
                        <button type={'button'} css={linkStyle} onClick={resend} disabled={isSubmitting || resending}>
                            {t('register.resend')}
                        </button>
                    </div>
                </LoginFormContainer>
            )}
        </Formik>
    );
};

interface DetailValues {
    nameFirst: string;
    nameLast: string;
    username: string;
    password: string;
    passwordConfirmation: string;
}

const DetailsStep = ({ email }: { email: string }) => {
    const { t } = useTranslation('client/auth');
    const { clearFlashes, clearAndAddHttpError } = useFlash();

    const submit = (values: DetailValues, { setSubmitting }: FormikHelpers<DetailValues>) => {
        clearFlashes();

        register(values)
            .then(({ intended }) => {
                // @ts-expect-error this is valid
                window.location = intended || '/';
            })
            .catch((error) => {
                console.error(error);
                setSubmitting(false);
                clearAndAddHttpError({ error });
            });
    };

    const required = t('register.required');

    return (
        <Formik
            onSubmit={submit}
            initialValues={{ nameFirst: '', nameLast: '', username: '', password: '', passwordConfirmation: '' }}
            validationSchema={object().shape({
                nameFirst: string().max(191).required(required),
                nameLast: string().max(191).required(required),
                username: string()
                    .min(3)
                    .max(191)
                    .matches(USERNAME_REGEX, t('register.username_invalid'))
                    .required(required),
                password: string().min(8, t('register.password_min')).required(required),
                passwordConfirmation: string()
                    .oneOf([yupRef('password')], t('register.password_mismatch'))
                    .required(required),
            })}
        >
            {({ isSubmitting }) => (
                <LoginFormContainer title={t('register.details_title')}>
                    <p css={tw`text-sm text-neutral-400 mb-6`}>{t('register.details_help', { email })}</p>
                    <div css={tw`grid grid-cols-1 sm:grid-cols-2 gap-6`}>
                        <Field
                            type={'text'}
                            name={'nameFirst'}
                            label={t('register.name_first')}
                            autoComplete={'given-name'}
                            disabled={isSubmitting}
                        />
                        <Field
                            type={'text'}
                            name={'nameLast'}
                            label={t('register.name_last')}
                            autoComplete={'family-name'}
                            disabled={isSubmitting}
                        />
                    </div>
                    <div css={tw`mt-6`}>
                        <Field
                            type={'text'}
                            name={'username'}
                            label={t('register.username')}
                            description={t('register.username_help')}
                            autoComplete={'username'}
                            disabled={isSubmitting}
                        />
                    </div>
                    <div css={tw`mt-6`}>
                        <Field
                            type={'password'}
                            name={'password'}
                            label={t('register.password')}
                            description={t('register.password_help')}
                            autoComplete={'new-password'}
                            disabled={isSubmitting}
                        />
                    </div>
                    <div css={tw`mt-6`}>
                        <Field
                            type={'password'}
                            name={'passwordConfirmation'}
                            label={t('register.confirm_password')}
                            autoComplete={'new-password'}
                            disabled={isSubmitting}
                        />
                    </div>
                    <div css={tw`mt-6`}>
                        <Button type={'submit'} size={'xlarge'} isLoading={isSubmitting} disabled={isSubmitting}>
                            {t('register.submit')}
                        </Button>
                    </div>
                </LoginFormContainer>
            )}
        </Formik>
    );
};

export default () => {
    const enabled = useStoreState((state) => state.settings.data!.registration);
    const { clearFlashes } = useFlash();
    const [step, setStep] = useState<Step>('email');
    const [email, setEmail] = useState('');

    useEffect(() => {
        clearFlashes();
    }, []);

    if (!enabled) {
        return <Redirect to={'/auth/login'} />;
    }

    switch (step) {
        case 'code':
            return <CodeStep email={email} onBack={() => setStep('email')} onVerified={() => setStep('details')} />;
        case 'details':
            return <DetailsStep email={email} />;
        default:
            return (
                <EmailStep
                    email={email}
                    onSent={(value) => {
                        setEmail(value);
                        setStep('code');
                    }}
                />
            );
    }
};
