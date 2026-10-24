export type ServerStatus =
    | 'installing'
    | 'install_failed'
    | 'reinstall_failed'
    | 'suspended'
    | 'restoring_backup'
    | null;

export interface ServerBackup {
    uuid: string;
    isSuccessful: boolean;
    isLocked: boolean;
    name: string;
    ignoredFiles: string;
    checksum: string;
    bytes: number;
    createdAt: Date;
    completedAt: Date | null;
    // Hydra: where the backup is kept, and why it failed ("storage_full", "sftp: …").
    location: 'panel' | 'node_sftp' | 'own_sftp';
    error: string | null;
}

export interface ServerEggVariable {
    name: string;
    description: string;
    envVariable: string;
    defaultValue: string;
    serverValue: string | null;
    isEditable: boolean;
    rules: string[];
}
