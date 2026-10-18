import React, { useState } from 'react';
import { Schedule } from '@/api/server/schedules/getServerSchedules';
import TaskDetailsModal from '@/components/server/schedules/TaskDetailsModal';
import { Button } from '@/components/elements/button/index';
import { useTranslation } from 'react-i18next';

interface Props {
    schedule: Schedule;
}

export default ({ schedule }: Props) => {
    const [visible, setVisible] = useState(false);
    const { t } = useTranslation('client/server');

    return (
        <>
            <TaskDetailsModal schedule={schedule} visible={visible} onModalDismissed={() => setVisible(false)} />
            <Button onClick={() => setVisible(true)} className={'flex-1'}>
                {t('schedules.new_task')}
            </Button>
        </>
    );
};
