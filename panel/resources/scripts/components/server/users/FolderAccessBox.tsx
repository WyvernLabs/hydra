import React from 'react';
import { useField } from 'formik';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faPlus, faTimes } from '@fortawesome/free-solid-svg-icons';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Input from '@/components/elements/Input';
import Button from '@/components/elements/Button';
import { useTranslation } from 'react-i18next';

interface Props {
    isEditable: boolean;
    // Folders the logged in user is limited to, empty for the whole server.
    ownPaths: string[];
    className?: string;
}

/**
 * Hydra: lets the server owner limit a subuser to certain folders, e.g. a
 * builder to "plugins/WorldEdit" and "world/build".
 */
const FolderAccessBox = ({ isEditable, ownPaths, className }: Props) => {
    const { t } = useTranslation('client/server');
    const [{ value: restricted }, , { setValue: setRestricted }] = useField<boolean>('restrictFolders');
    const [{ value: paths }, { error }, { setValue: setPaths }] = useField<string[]>('allowedPaths');

    const update = (index: number, path: string) => setPaths(paths.map((p, i) => (i === index ? path : p)));
    const remove = (index: number) => setPaths(paths.filter((_, i) => i !== index));

    return (
        <TitledGreyBox title={<p css={tw`text-sm uppercase`}>{t('users.folders')}</p>} className={className}>
            <p css={tw`text-sm text-neutral-400 mb-4`}>{t('users.folders_help')}</p>
            <div css={tw`flex flex-wrap gap-6 mb-4`}>
                <label css={tw`flex items-center gap-2 text-sm cursor-pointer`}>
                    <Input
                        type={'radio'}
                        name={'restrictFolders'}
                        checked={!restricted}
                        disabled={!isEditable || ownPaths.length > 0}
                        onChange={() => setRestricted(false)}
                    />
                    {t('users.folders_all')}
                </label>
                <label css={tw`flex items-center gap-2 text-sm cursor-pointer`}>
                    <Input
                        type={'radio'}
                        name={'restrictFolders'}
                        checked={restricted}
                        disabled={!isEditable}
                        onChange={() => {
                            setRestricted(true);
                            if (paths.length === 0) setPaths(['']);
                        }}
                    />
                    {t('users.folders_some')}
                </label>
            </div>
            {restricted && (
                <>
                    {ownPaths.length > 0 && (
                        <p css={tw`text-xs text-neutral-400 mb-3`}>
                            {t('users.folders_own_only')}{' '}
                            <span css={tw`font-mono text-neutral-200`}>{ownPaths.join(', ')}</span>
                        </p>
                    )}
                    {paths.map((path, index) => (
                        <div key={index} css={tw`flex items-center gap-2 mb-2`}>
                            <span css={tw`text-neutral-500 font-mono`}>/</span>
                            <Input
                                type={'text'}
                                value={path.replace(/^\/+/, '')}
                                placeholder={t('users.folders_placeholder')}
                                disabled={!isEditable}
                                onChange={(e) => update(index, e.currentTarget.value)}
                                css={tw`font-mono`}
                            />
                            {isEditable && (
                                <button
                                    type={'button'}
                                    aria-label={t('users.folders_remove')}
                                    css={tw`p-2 text-neutral-500 hover:text-red-400 transition-colors duration-150`}
                                    onClick={() => remove(index)}
                                >
                                    <FontAwesomeIcon icon={faTimes} />
                                </button>
                            )}
                        </div>
                    ))}
                    {error && <p css={tw`text-xs text-red-400 mb-2`}>{error}</p>}
                    {isEditable && (
                        <Button type={'button'} size={'xsmall'} isSecondary onClick={() => setPaths([...paths, ''])}>
                            <FontAwesomeIcon icon={faPlus} css={tw`mr-2`} />
                            {t('users.folders_add')}
                        </Button>
                    )}
                    <p css={tw`text-xs text-neutral-500 mt-4`}>{t('users.folders_backups')}</p>
                </>
            )}
        </TitledGreyBox>
    );
};

export default FolderAccessBox;
