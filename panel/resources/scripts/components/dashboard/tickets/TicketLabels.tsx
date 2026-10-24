import React from 'react';
import { useTranslation } from 'react-i18next';
import { TicketPriority, TicketStatus } from '@/api/account/tickets';

// Hydra: colored labels of the status and the priority of a ticket.

const statusColors: Record<TicketStatus, string> = {
    open: 'bg-yellow-500/20 text-yellow-300',
    answered: 'bg-primary-500/20 text-primary-300',
    closed: 'bg-gray-600 text-gray-300',
};

const priorityColors: Record<TicketPriority, string> = {
    low: 'bg-gray-600 text-gray-300',
    normal: 'bg-blue-500/20 text-blue-300',
    high: 'bg-orange-500/20 text-orange-300',
    urgent: 'bg-red-500/20 text-red-300',
};

const Label = ({ className, children }: { className: string; children: React.ReactNode }) => (
    <span className={`inline-block whitespace-nowrap rounded px-2 py-0.5 text-xs font-medium ${className}`}>
        {children}
    </span>
);

export const StatusLabel = ({ status }: { status: TicketStatus }) => {
    const { t } = useTranslation('client/tickets');

    return <Label className={statusColors[status]}>{t(`statuses.${status}`)}</Label>;
};

export const PriorityLabel = ({ priority }: { priority: TicketPriority }) => {
    const { t } = useTranslation('client/tickets');

    return <Label className={priorityColors[priority]}>{t(`priorities.${priority}`)}</Label>;
};
