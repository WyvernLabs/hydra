import React from 'react';
import { CellValue } from '@/api/server/databases/manager';

// Hydra: a value in a result grid. NULL is shown in italics, long values are cut.
export default ({ value, truncated }: { value: CellValue; truncated?: boolean }) => {
    if (value === null) {
        return <span className={'italic text-gray-500'}>NULL</span>;
    }

    const text = String(value);

    return (
        <span className={'whitespace-pre'} title={text.length > 80 ? text.slice(0, 500) : undefined}>
            {text.length > 80 ? `${text.slice(0, 80)}…` : text}
            {truncated && text.length <= 80 && '…'}
        </span>
    );
};
