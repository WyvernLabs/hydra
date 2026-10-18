import React from 'react';
import tw from 'twin.macro';
import { useTranslation } from 'react-i18next';

export default () => {
    const { t } = useTranslation('client/server');

    return (
        <>
            <div css={tw`md:w-1/2 h-full bg-neutral-800`}>
                <div css={tw`flex flex-col`}>
                    <h2 css={tw`py-4 px-6 font-bold`}>{t('schedules.cheatsheet.examples')}</h2>
                    <div css={tw`flex py-4 px-6 bg-neutral-700`}>
                        <div css={tw`w-1/2`}>*/5 * * * *</div>
                        <div css={tw`w-1/2`}>{t('schedules.cheatsheet.every_5_minutes')}</div>
                    </div>
                    <div css={tw`flex py-4 px-6`}>
                        <div css={tw`w-1/2`}>0 */1 * * *</div>
                        <div css={tw`w-1/2`}>{t('schedules.cheatsheet.every_hour')}</div>
                    </div>
                    <div css={tw`flex py-4 px-6 bg-neutral-700`}>
                        <div css={tw`w-1/2`}>0 8-12 * * *</div>
                        <div css={tw`w-1/2`}>{t('schedules.cheatsheet.hour_range')}</div>
                    </div>
                    <div css={tw`flex py-4 px-6`}>
                        <div css={tw`w-1/2`}>0 0 * * *</div>
                        <div css={tw`w-1/2`}>{t('schedules.cheatsheet.once_a_day')}</div>
                    </div>
                    <div css={tw`flex py-4 px-6 bg-neutral-700`}>
                        <div css={tw`w-1/2`}>0 0 * * MON</div>
                        <div css={tw`w-1/2`}>{t('schedules.cheatsheet.every_monday')}</div>
                    </div>
                </div>
            </div>
            <div css={tw`md:w-1/2 h-full bg-neutral-800`}>
                <h2 css={tw`py-4 px-6 font-bold`}>{t('schedules.cheatsheet.special')}</h2>
                <div css={tw`flex flex-col`}>
                    <div css={tw`flex py-4 px-6 bg-neutral-700`}>
                        <div css={tw`w-1/2`}>*</div>
                        <div css={tw`w-1/2`}>{t('schedules.cheatsheet.any')}</div>
                    </div>
                    <div css={tw`flex py-4 px-6`}>
                        <div css={tw`w-1/2`}>,</div>
                        <div css={tw`w-1/2`}>{t('schedules.cheatsheet.list')}</div>
                    </div>
                    <div css={tw`flex py-4 px-6 bg-neutral-700`}>
                        <div css={tw`w-1/2`}>-</div>
                        <div css={tw`w-1/2`}>{t('schedules.cheatsheet.range')}</div>
                    </div>
                    <div css={tw`flex py-4 px-6`}>
                        <div css={tw`w-1/2`}>/</div>
                        <div css={tw`w-1/2`}>{t('schedules.cheatsheet.step')}</div>
                    </div>
                </div>
            </div>
        </>
    );
};
