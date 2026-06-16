import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { type ReactElement, useCallback, useRef, useState } from 'react';

interface ConfirmOptions {
    title: string;
    description?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    destructive?: boolean;
}

/**
 * Promise-based confirmation dialog. Usage:
 *
 *   const [confirm, confirmDialog] = useConfirm();
 *   ...
 *   if (await confirm({ title: 'Delete?', destructive: true })) doIt();
 *   ...
 *   return (<>{confirmDialog}{/* page * /}</>);
 */
export function useConfirm(): [(options: ConfirmOptions) => Promise<boolean>, ReactElement] {
    const [open, setOpen] = useState(false);
    const [options, setOptions] = useState<ConfirmOptions>({ title: '' });
    const resolver = useRef<((value: boolean) => void) | null>(null);

    const confirm = useCallback((opts: ConfirmOptions) => {
        setOptions(opts);
        setOpen(true);
        return new Promise<boolean>((resolve) => {
            resolver.current = resolve;
        });
    }, []);

    const finish = (value: boolean) => {
        setOpen(false);
        resolver.current?.(value);
        resolver.current = null;
    };

    const confirmDialog = (
        <Dialog open={open} onOpenChange={(next) => !next && finish(false)}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{options.title}</DialogTitle>
                    {options.description && <DialogDescription>{options.description}</DialogDescription>}
                </DialogHeader>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={() => finish(false)}>
                        {options.cancelLabel ?? 'Cancel'}
                    </Button>
                    <Button type="button" variant={options.destructive ? 'destructive' : 'default'} onClick={() => finish(true)}>
                        {options.confirmLabel ?? 'Confirm'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );

    return [confirm, confirmDialog];
}
