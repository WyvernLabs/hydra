import React, { useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faDatabase } from '@fortawesome/free-solid-svg-icons';
import { Eye, Trash2 } from 'lucide-react';
import useSWR from 'swr';
import { Button as NewButton } from '@/components/elements/button/index';
import { getDatabaseStats } from '@/api/server/databases/manager';
import { bytesToString } from '@/lib/formatters';
import Modal from '@/components/elements/Modal';
import { Form, Formik, FormikHelpers } from 'formik';
import Field from '@/components/elements/Field';
import { object, string } from 'yup';
import FlashMessageRender from '@/components/FlashMessageRender';
import { ServerContext } from '@/state/server';
import deleteServerDatabase from '@/api/server/databases/deleteServerDatabase';
import { httpErrorToHuman } from '@/api/http';
import RotatePasswordButton from '@/components/server/databases/RotatePasswordButton';
import Can from '@/components/elements/Can';
import { ServerDatabase } from '@/api/server/databases/getServerDatabases';
import useFlash from '@/plugins/useFlash';
import tw from 'twin.macro';
import Button from '@/components/elements/Button';
import Label from '@/components/elements/Label';
import Input from '@/components/elements/Input';
import CopyOnClick from '@/components/elements/CopyOnClick';
import { Trans, useTranslation } from 'react-i18next';
import DatabaseTools from '@/components/server/databases/DatabaseTools';

interface Props {
    database: ServerDatabase;
    className?: string;
}

export default ({ database, className }: Props) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { t } = useTranslation('client/server');
    const { addError, clearFlashes } = useFlash();
    const [visible, setVisible] = useState(false);
    const [connectionVisible, setConnectionVisible] = useState(false);

    const { data: stats } = useSWR(['database-stats', uuid, database.id], () => getDatabaseStats(uuid, database.id), {
        revalidateOnFocus: false,
    });
    const ButtonText = NewButton.Text;

    const appendDatabase = ServerContext.useStoreActions((actions) => actions.databases.appendDatabase);
    const removeDatabase = ServerContext.useStoreActions((actions) => actions.databases.removeDatabase);

    const jdbcConnectionString = `jdbc:mysql://${database.username}${
        database.password ? `:${encodeURIComponent(database.password)}` : ''
    }@${database.connectionString}/${database.name}`;

    const schema = object().shape({
        confirm: string()
            .required(t('databases.confirm_required'))
            .oneOf([database.name.split('_', 2)[1], database.name], t('databases.confirm_required')),
    });

    const submit = (values: { confirm: string }, { setSubmitting }: FormikHelpers<{ confirm: string }>) => {
        clearFlashes();
        deleteServerDatabase(uuid, database.id)
            .then(() => {
                setVisible(false);
                setTimeout(() => removeDatabase(database.id), 150);
            })
            .catch((error) => {
                console.error(error);
                setSubmitting(false);
                addError({ key: 'database:delete', message: httpErrorToHuman(error) });
            });
    };

    return (
        <>
            <Formik onSubmit={submit} initialValues={{ confirm: '' }} validationSchema={schema} isInitialValid={false}>
                {({ isSubmitting, isValid, resetForm }) => (
                    <Modal
                        visible={visible}
                        dismissable={!isSubmitting}
                        showSpinnerOverlay={isSubmitting}
                        onDismissed={() => {
                            setVisible(false);
                            resetForm();
                        }}
                    >
                        <FlashMessageRender byKey={'database:delete'} css={tw`mb-6`} />
                        <h2 css={tw`text-2xl mb-6`}>{t('databases.delete_title')}</h2>
                        <p css={tw`text-sm`}>
                            <Trans
                                t={t}
                                i18nKey={'databases.delete_text'}
                                values={{ name: database.name }}
                                components={{ bold: <strong /> }}
                            />
                        </p>
                        <Form css={tw`m-0 mt-6`}>
                            <Field
                                type={'text'}
                                id={'confirm_name'}
                                name={'confirm'}
                                label={t('databases.confirm_name')}
                                description={t('databases.confirm_name_help')}
                            />
                            <div css={tw`mt-6 text-right`}>
                                <Button type={'button'} isSecondary css={tw`mr-2`} onClick={() => setVisible(false)}>
                                    {t('client/common:actions.cancel')}
                                </Button>
                                <Button type={'submit'} color={'red'} disabled={!isValid}>
                                    {t('databases.delete')}
                                </Button>
                            </div>
                        </Form>
                    </Modal>
                )}
            </Formik>
            <Modal visible={connectionVisible} onDismissed={() => setConnectionVisible(false)}>
                <FlashMessageRender byKey={'database-connection-modal'} css={tw`mb-6`} />
                <h3 css={tw`mb-6 text-2xl`}>{t('databases.details_title')}</h3>
                <div>
                    <Label>{t('databases.endpoint')}</Label>
                    <CopyOnClick text={database.connectionString}>
                        <Input type={'text'} readOnly value={database.connectionString} />
                    </CopyOnClick>
                </div>
                <div css={tw`mt-6`}>
                    <Label>{t('databases.connections_from')}</Label>
                    <Input type={'text'} readOnly value={database.allowConnectionsFrom} />
                </div>
                <div css={tw`mt-6`}>
                    <Label>{t('databases.username')}</Label>
                    <CopyOnClick text={database.username}>
                        <Input type={'text'} readOnly value={database.username} />
                    </CopyOnClick>
                </div>
                <Can action={'database.view_password'}>
                    <div css={tw`mt-6`}>
                        <Label>{t('databases.password')}</Label>
                        <CopyOnClick text={database.password} showInNotification={false}>
                            <Input type={'text'} readOnly value={database.password} />
                        </CopyOnClick>
                    </div>
                </Can>
                <div css={tw`mt-6`}>
                    <Label>{t('databases.jdbc')}</Label>
                    <CopyOnClick text={jdbcConnectionString} showInNotification={false}>
                        <Input type={'text'} readOnly value={jdbcConnectionString} />
                    </CopyOnClick>
                </div>
                <div css={tw`mt-6 text-right`}>
                    <Can action={'database.update'}>
                        <RotatePasswordButton databaseId={database.id} onUpdate={appendDatabase} />
                    </Can>
                    <Button isSecondary onClick={() => setConnectionVisible(false)}>
                        {t('client/common:actions.close')}
                    </Button>
                </div>
            </Modal>
            {/* Hydra: one card per database, the name in the middle, size and tables below. */}
            <div
                className={`flex flex-col items-center rounded-lg border border-gray-700 bg-gray-800/80 p-5 text-center backdrop-blur ${
                    className || ''
                }`}
            >
                <span className={'mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-900/70'}>
                    <FontAwesomeIcon icon={faDatabase} className={'text-primary-400'} size={'lg'} />
                </span>
                <CopyOnClick text={database.name}>
                    <p className={'max-w-full truncate text-lg font-semibold text-gray-50'}>{database.name}</p>
                </CopyOnClick>
                <p className={'mt-1 text-sm text-gray-400'}>
                    {!stats
                        ? '…'
                        : stats.size === null
                        ? t('databases.stats_unknown')
                        : `${bytesToString(stats.size)} · ${t('databases.tables_count', { count: stats.tables || 0 })}`}
                </p>
                <div className={'mt-4 grid w-full grid-cols-2 gap-2'}>
                    <ButtonText className={'w-full gap-1.5 px-3 py-2'} onClick={() => setConnectionVisible(true)}>
                        <Eye className={'h-4 w-4'} />
                        <span>{t('databases.details_button')}</span>
                    </ButtonText>
                    <DatabaseTools database={database} showManage flashKey={'databases'} labels />
                    <Can action={'database.delete'}>
                        <ButtonText
                            className={'w-full gap-1.5 px-3 py-2 !text-red-400 hover:!text-red-300'}
                            onClick={() => setVisible(true)}
                        >
                            <Trash2 className={'h-4 w-4'} />
                            <span>{t('databases.delete_button')}</span>
                        </ButtonText>
                    </Can>
                </div>
            </div>
        </>
    );
};
