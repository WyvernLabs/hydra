import React, { useState } from 'react';
import Modal from '@/components/elements/Modal';
import { Form, Formik, FormikHelpers } from 'formik';
import Field from '@/components/elements/Field';
import { object, string } from 'yup';
import createServerDatabase from '@/api/server/databases/createServerDatabase';
import { ServerContext } from '@/state/server';
import { httpErrorToHuman } from '@/api/http';
import FlashMessageRender from '@/components/FlashMessageRender';
import useFlash from '@/plugins/useFlash';
import Button from '@/components/elements/Button';
import tw from 'twin.macro';
import { useTranslation } from 'react-i18next';

interface Values {
    databaseName: string;
    connectionsFrom: string;
}

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { t } = useTranslation('client/server');
    const schema = object().shape({
        databaseName: string()
            .required(t('databases.name_required'))
            .min(3, t('databases.name_min'))
            .max(48, t('databases.name_max'))
            .matches(/^[\w\-.]{3,48}$/, t('databases.name_format')),
        connectionsFrom: string().matches(/^[\w\-/.%:]+$/, t('databases.host_invalid')),
    });
    const { addError, clearFlashes } = useFlash();
    const [visible, setVisible] = useState(false);

    const appendDatabase = ServerContext.useStoreActions((actions) => actions.databases.appendDatabase);

    const submit = (values: Values, { setSubmitting }: FormikHelpers<Values>) => {
        clearFlashes('database:create');
        createServerDatabase(uuid, {
            databaseName: values.databaseName,
            connectionsFrom: values.connectionsFrom || '%',
        })
            .then((database) => {
                appendDatabase(database);
                setVisible(false);
            })
            .catch((error) => {
                addError({ key: 'database:create', message: httpErrorToHuman(error) });
                setSubmitting(false);
            });
    };

    return (
        <>
            <Formik
                onSubmit={submit}
                initialValues={{ databaseName: '', connectionsFrom: '' }}
                validationSchema={schema}
            >
                {({ isSubmitting, resetForm }) => (
                    <Modal
                        visible={visible}
                        dismissable={!isSubmitting}
                        showSpinnerOverlay={isSubmitting}
                        onDismissed={() => {
                            resetForm();
                            setVisible(false);
                        }}
                    >
                        <FlashMessageRender byKey={'database:create'} css={tw`mb-6`} />
                        <h2 css={tw`text-2xl mb-6`}>{t('databases.create_title')}</h2>
                        <Form css={tw`m-0`}>
                            <Field
                                type={'string'}
                                id={'database_name'}
                                name={'databaseName'}
                                label={t('databases.name')}
                                description={t('databases.name_help')}
                            />
                            <div css={tw`mt-6`}>
                                <Field
                                    type={'string'}
                                    id={'connections_from'}
                                    name={'connectionsFrom'}
                                    label={t('databases.connections_from')}
                                    description={t('databases.connections_from_help')}
                                />
                            </div>
                            <div css={tw`flex flex-wrap justify-end mt-6`}>
                                <Button
                                    type={'button'}
                                    isSecondary
                                    css={tw`w-full sm:w-auto sm:mr-2`}
                                    onClick={() => setVisible(false)}
                                >
                                    {t('client/common:actions.cancel')}
                                </Button>
                                <Button css={tw`w-full mt-4 sm:w-auto sm:mt-0`} type={'submit'}>
                                    {t('databases.create')}
                                </Button>
                            </div>
                        </Form>
                    </Modal>
                )}
            </Formik>
            <Button onClick={() => setVisible(true)}>{t('databases.new')}</Button>
        </>
    );
};
