import React from 'react';
import Input from '@/components/elements/Input';

interface Props {
    id: string;
    value: string;
    placeholder: string;
    onChange: (value: string) => void;
}

const valid = /^#[0-9a-fA-F]{6}$/;

/**
 * Hydra: color picker with a text field for the hex value.
 */
export default ({ id, value, placeholder, onChange }: Props) => (
    <div className={'hydra-color-input'}>
        <input
            type={'color'}
            aria-label={id}
            value={valid.test(value) ? value : placeholder}
            onChange={(e) => onChange(e.currentTarget.value)}
        />
        <Input
            id={id}
            type={'text'}
            maxLength={7}
            value={value}
            placeholder={placeholder}
            onChange={(e) => onChange(e.currentTarget.value)}
        />
    </div>
);
