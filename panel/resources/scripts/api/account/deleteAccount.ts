import http from '@/api/http';

/**
 * Hydra: deletes the account of the logged in user.
 */
export default async (password: string, confirm: string): Promise<void> => {
    await http.delete('/api/client/account', { data: { password, confirm } });
};
