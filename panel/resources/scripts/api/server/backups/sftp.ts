import http from '@/api/http';
import { ServerBackup } from '@/api/server/types';
import { rawDataToServerBackup } from '@/api/transformers';

// Hydra: the own SFTP server of a server for backups (addon "sftp_backups").

export interface SftpBackupSettings {
    configured: boolean;
    host: string;
    port: number;
    username: string;
    path: string;
    hostKey: string | null;
    hasPassword: boolean;
    hasPrivateKey: boolean;
}

export interface SftpBackupFile {
    name: string;
    size: number;
    modified: Date;
    imported: boolean;
}

export interface SftpBackupValues {
    host: string;
    port: number;
    username: string;
    password: string;
    privateKey: string;
    path: string;
}

const toSettings = (data: any): SftpBackupSettings => ({
    configured: data.configured,
    host: data.host,
    port: data.port,
    username: data.username,
    path: data.path,
    hostKey: data.host_key,
    hasPassword: data.has_password,
    hasPrivateKey: data.has_private_key,
});

export const getSftpBackupSettings = async (uuid: string): Promise<SftpBackupSettings> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/backups/sftp`);

    return toSettings(data);
};

/**
 * Tests and saves the settings; returns them with the backups found.
 */
export const saveSftpBackupSettings = async (
    uuid: string,
    values: SftpBackupValues
): Promise<SftpBackupSettings & { found: number }> => {
    const { data } = await http.put(
        `/api/client/servers/${uuid}/backups/sftp`,
        {
            host: values.host,
            port: values.port,
            username: values.username,
            password: values.password || null,
            private_key: values.privateKey || null,
            path: values.path,
        },
        { timeout: 60000 }
    );

    return { ...toSettings(data), found: (data.files || []).length };
};

export const deleteSftpBackupSettings = async (uuid: string): Promise<void> => {
    await http.delete(`/api/client/servers/${uuid}/backups/sftp`);
};

export const getSftpBackupFiles = async (uuid: string): Promise<SftpBackupFile[]> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/backups/sftp/files`, { timeout: 60000 });

    return (data || []).map((file: any) => ({
        name: file.name,
        size: file.size,
        modified: new Date(file.modified),
        imported: file.imported,
    }));
};

export const importSftpBackup = async (uuid: string, file: string): Promise<ServerBackup> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/backups/sftp/import`, { file }, { timeout: 60000 });

    return rawDataToServerBackup(data);
};
