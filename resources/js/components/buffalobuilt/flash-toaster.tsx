import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';

export function FlashToaster() {
    const { flash } = usePage<SharedData>().props;
    // Dedup by the server-generated flash id (unique per response), so two
    // consecutive actions with identical messages both toast, while
    // re-renders of the same response don't.
    const lastId = useRef<string | null>(null);

    useEffect(() => {
        if (!flash?.id || flash.id === lastId.current) {
            return;
        }
        lastId.current = flash.id;

        if (flash.success) {
            toast.success(flash.success);
        }
        if (flash.error) {
            toast.error(flash.error);
        }
    }, [flash?.id, flash?.success, flash?.error]);

    return null;
}
