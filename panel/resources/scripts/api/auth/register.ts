import http from '@/api/http';

// Hydra: registration (Design > Addons > Registration). A code is mailed to
// the address, confirming it unlocks the account form.

export const requestRegistrationCode = (email: string, captcha?: string): Promise<void> =>
    http
        .get('/sanctum/csrf-cookie')
        .then(() => http.post('/auth/register/code', { email, 'cf-turnstile-response': captcha }))
        .then(() => undefined);

export const verifyRegistrationCode = (email: string, code: string): Promise<void> =>
    http.post('/auth/register/verify', { email, code }).then(() => undefined);

export interface RegisterData {
    nameFirst: string;
    nameLast: string;
    username: string;
    password: string;
    passwordConfirmation: string;
}

export default (data: RegisterData): Promise<{ intended?: string }> =>
    http
        .post('/auth/register', {
            name_first: data.nameFirst,
            name_last: data.nameLast,
            username: data.username,
            password: data.password,
            password_confirmation: data.passwordConfirmation,
        })
        .then((response) => ({ intended: response.data.data.intended || undefined }));
