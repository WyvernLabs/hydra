import { iconComponent } from '@/components/hydra/HydraIcon';

/**
 * Hydra: the icon an admin picked for a link in the sidebar (any Lucide icon
 * or "discord").
 */
export const linkIcon = (name: string) => iconComponent(name || 'file-text');
