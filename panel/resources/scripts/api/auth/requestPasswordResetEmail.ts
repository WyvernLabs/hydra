import http from '@/api/http';

export default (email: string, captcha?: string): Promise<string> => {
    return new Promise((resolve, reject) => {
        http.post('/auth/password', { email, 'cf-turnstile-response': captcha })
            .then((response) => resolve(response.data.status || ''))
            .catch(reject);
    });
};
