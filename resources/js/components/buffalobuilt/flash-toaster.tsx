import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';

export function FlashToaster() {
    const { flash } = usePage<SharedData>().props;
    const lastShown = useRef<{ success?: string; error?: string }>({});

    useEffect(() => {
        if (flash?.success && flash.success !== lastShown.current.success) {
            toast.success(flash.success);
            lastShown.current.success = flash.success;
        }
        if (flash?.error && flash.error !== lastShown.current.error) {
            toast.error(flash.error);
            lastShown.current.error = flash.error;
        }
    }, [flash?.success, flash?.error]);

    return null;
}
