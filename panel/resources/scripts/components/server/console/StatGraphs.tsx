import React, { useEffect, useRef } from 'react';
import { ServerContext } from '@/state/server';
import { SocketEvent } from '@/components/server/events';
import useWebsocketEvent from '@/plugins/useWebsocketEvent';
import { Line } from 'react-chartjs-2';
import { useChart, useChartTickLabel } from '@/components/server/console/chart';
import { hexToRgba, hydraColor } from '@/lib/helpers';
import { bytesToString } from '@/lib/formatters';
import { CloudDownload as CloudDownloadIcon, CloudUpload as CloudUploadIcon } from 'lucide-react';
import { theme } from 'twin.macro';
import ChartBlock from '@/components/server/console/ChartBlock';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import { useTranslation } from 'react-i18next';

export default () => {
    const status = ServerContext.useStoreState((state) => state.status.value);
    const { t } = useTranslation('client/server');
    const limits = ServerContext.useStoreState((state) => state.server.data!.limits);
    const previous = useRef<Record<'tx' | 'rx', number>>({ tx: -1, rx: -1 });

    const cpu = useChartTickLabel(t('details.cpu'), limits.cpu, '%', 2);
    const memory = useChartTickLabel(t('details.memory'), limits.memory, 'MiB');
    const network = useChart(t('details.network'), {
        sets: 2,
        options: {
            scales: {
                y: {
                    ticks: {
                        callback(value) {
                            return bytesToString(typeof value === 'string' ? parseInt(value, 10) : value);
                        },
                    },
                },
            },
        },
        callback(opts, index) {
            return {
                ...opts,
                label: !index ? t('details.inbound') : t('details.outbound'),
                borderColor: !index ? hydraColor('accent-400') : theme('colors.yellow.400'),
                backgroundColor: !index ? hydraColor('accent-700', 0.5) : hexToRgba(theme('colors.yellow.700'), 0.5),
            };
        },
    });

    useEffect(() => {
        if (status === 'offline') {
            cpu.clear();
            memory.clear();
            network.clear();
        }
    }, [status]);

    useWebsocketEvent(SocketEvent.STATS, (data: string) => {
        let values: any = {};
        try {
            values = JSON.parse(data);
        } catch (e) {
            return;
        }
        cpu.push(values.cpu_absolute);
        memory.push(Math.floor(values.memory_bytes / 1024 / 1024));
        network.push([
            previous.current.tx < 0 ? 0 : Math.max(0, values.network.tx_bytes - previous.current.tx),
            previous.current.rx < 0 ? 0 : Math.max(0, values.network.rx_bytes - previous.current.rx),
        ]);

        previous.current = { tx: values.network.tx_bytes, rx: values.network.rx_bytes };
    });

    return (
        <>
            <ChartBlock title={t('details.cpu')}>
                <Line {...cpu.props} />
            </ChartBlock>
            <ChartBlock title={t('details.memory')}>
                <Line {...memory.props} />
            </ChartBlock>
            <ChartBlock
                title={t('details.network')}
                legend={
                    <>
                        <Tooltip arrow content={t('details.inbound')}>
                            <CloudDownloadIcon className={'mr-2 w-4 h-4 text-yellow-400'} />
                        </Tooltip>
                        <Tooltip arrow content={t('details.outbound')}>
                            <CloudUploadIcon className={'w-4 h-4 text-cyan-400'} />
                        </Tooltip>
                    </>
                }
            >
                <Line {...network.props} />
            </ChartBlock>
        </>
    );
};
