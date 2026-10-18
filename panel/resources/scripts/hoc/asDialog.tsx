import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Dialog, DialogProps, DialogWrapperContext, WrapperProps } from '@/components/elements/dialog';

// "client/account:two_factor.enable_title" – a namespaced translation key.
const isTranslationKey = (value?: string): value is string => !!value && /^[\w/-]+:[\w.-]+$/.test(value);

function asDialog(
    initialProps?: WrapperProps
    // eslint-disable-next-line @typescript-eslint/ban-types
): <P extends {}>(C: React.ComponentType<P>) => React.FunctionComponent<P & DialogProps> {
    return function (Component) {
        return function ({ open, onClose, ...rest }) {
            const { t } = useTranslation();
            const [props, setProps] = useState<WrapperProps>(initialProps || {});

            // Hydra: title and description may be given as translation keys, because they are
            // defined when the module loads – before the translations are available.
            const translated = {
                ...props,
                title: isTranslationKey(props.title) ? t(props.title) : props.title,
                description: isTranslationKey(props.description) ? t(props.description) : props.description,
            };

            return (
                <DialogWrapperContext.Provider value={{ props, setProps, close: onClose }}>
                    <Dialog {...translated} open={open} onClose={onClose}>
                        <Component {...(rest as React.ComponentProps<typeof Component>)} />
                    </Dialog>
                </DialogWrapperContext.Provider>
            );
        };
    };
}

export default asDialog;
