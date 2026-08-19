import { useConfirm } from '@/components/buffalobuilt/confirm-dialog';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Camera, ChevronLeft, ChevronRight, ExternalLink, Trash2, Upload, X } from 'lucide-react';
import { ChangeEventHandler, useCallback, useEffect, useMemo, useRef, useState } from 'react';

interface Photo {
    id: number;
    thumb_url: string;
    full_url: string;
    taken_on: string; // YYYY-MM-DD
    month: string; // YYYY-MM
    month_label: string; // "August 2026"
    uploader: string | null;
    log_id: number;
    original_name: string;
    can_delete: boolean;
}

interface PageProps {
    project: { id: number; name: string; client_name: string | null };
    photos: Photo[];
}

function formatDate(date: string): string {
    const [y, m, d] = date.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
}

export default function ProjectPhotos({ project, photos }: PageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Projects', href: '/projects' },
        { title: project.name, href: `/projects/${project.id}` },
        { title: 'Photos', href: `/projects/${project.id}/photos` },
    ];

    const [month, setMonth] = useState('all');
    const [uploader, setUploader] = useState('all');
    const [viewing, setViewing] = useState<number | null>(null); // index into filtered list
    const [uploading, setUploading] = useState(false);
    const fileInput = useRef<HTMLInputElement>(null);
    const [confirm, confirmDialog] = useConfirm();

    const months = useMemo(() => {
        const seen = new Map<string, string>();
        for (const p of photos) if (!seen.has(p.month)) seen.set(p.month, p.month_label);
        return [...seen.entries()]; // already newest-first from the server ordering
    }, [photos]);

    const uploaders = useMemo(() => [...new Set(photos.map((p) => p.uploader).filter((u): u is string => u !== null))].sort(), [photos]);

    const filtered = useMemo(
        () => photos.filter((p) => (month === 'all' || p.month === month) && (uploader === 'all' || p.uploader === uploader)),
        [photos, month, uploader],
    );

    // Filtered photos regrouped for display: one section per month.
    const sections = useMemo(() => {
        const bucket = new Map<string, { label: string; items: { photo: Photo; index: number }[] }>();
        filtered.forEach((photo, index) => {
            const group = bucket.get(photo.month) ?? { label: photo.month_label, items: [] };
            group.items.push({ photo, index });
            bucket.set(photo.month, group);
        });
        return [...bucket.values()];
    }, [filtered]);

    const close = useCallback(() => setViewing(null), []);
    const step = useCallback(
        (delta: number) => {
            setViewing((v) => {
                if (v === null || filtered.length === 0) return v;
                return (v + delta + filtered.length) % filtered.length;
            });
        },
        [filtered.length],
    );

    useEffect(() => {
        if (viewing === null) return;
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') close();
            if (e.key === 'ArrowLeft') step(-1);
            if (e.key === 'ArrowRight') step(1);
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [viewing, close, step]);

    const upload: ChangeEventHandler<HTMLInputElement> = (e) => {
        const files = e.target.files;
        if (!files || files.length === 0) return;
        setUploading(true);
        router.post(
            `/projects/${project.id}/photos`,
            { photos: [...files] },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => {
                    setUploading(false);
                    if (fileInput.current) fileInput.current.value = '';
                },
            },
        );
    };

    const removePhoto = async (photo: Photo) => {
        const ok = await confirm({
            title: 'Delete photo?',
            description: 'The photo is removed from this project and its daily log. This cannot be undone.',
            confirmLabel: 'Delete',
            destructive: true,
        });
        if (ok) {
            close();
            router.delete(`/daily-log-photos/${photo.id}`, { preserveScroll: true });
        }
    };

    const current = viewing !== null ? filtered[viewing] : null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Photos — ${project.name}`} />
            {confirmDialog}

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-foreground text-2xl font-semibold">Photos</h1>
                        <p className="text-muted-foreground text-sm">
                            {project.name}
                            {project.client_name ? ` · ${project.client_name}` : ''} · {photos.length} {photos.length === 1 ? 'photo' : 'photos'}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {months.length > 1 && (
                            <Select value={month} onValueChange={setMonth}>
                                <SelectTrigger className="w-40">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All months</SelectItem>
                                    {months.map(([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                        {uploaders.length > 1 && (
                            <Select value={uploader} onValueChange={setUploader}>
                                <SelectTrigger className="w-40">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Everyone</SelectItem>
                                    {uploaders.map((u) => (
                                        <SelectItem key={u} value={u}>
                                            {u}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                        <input ref={fileInput} type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple hidden onChange={upload} />
                        <Button className="gap-2" disabled={uploading} onClick={() => fileInput.current?.click()}>
                            <Upload className="h-4 w-4" />
                            {uploading ? 'Uploading…' : 'Add photos'}
                        </Button>
                    </div>
                </div>

                {filtered.length === 0 ? (
                    <Card className="text-muted-foreground flex flex-col items-center gap-2 p-10 text-center text-sm">
                        <Camera className="h-8 w-8 opacity-40" />
                        {photos.length === 0
                            ? 'No photos yet. Photos added to daily logs appear here automatically — or use Add photos.'
                            : 'No photos match the current filters.'}
                    </Card>
                ) : (
                    sections.map((section) => (
                        <section key={section.label} className="flex flex-col gap-2">
                            <h2 className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                {section.label}
                                <span className="ml-2 font-normal normal-case">
                                    {section.items.length} {section.items.length === 1 ? 'photo' : 'photos'}
                                </span>
                            </h2>
                            <div className="grid grid-cols-[repeat(auto-fill,minmax(9rem,1fr))] gap-2">
                                {section.items.map(({ photo, index }) => (
                                    <button
                                        key={photo.id}
                                        type="button"
                                        onClick={() => setViewing(index)}
                                        className="focus-visible:ring-ring group relative aspect-square overflow-hidden rounded-md border focus-visible:ring-2 focus-visible:outline-none"
                                        title={`${formatDate(photo.taken_on)}${photo.uploader ? ` · ${photo.uploader}` : ''}`}
                                    >
                                        <img
                                            src={photo.thumb_url}
                                            alt={photo.original_name}
                                            loading="lazy"
                                            className="h-full w-full object-cover transition-transform group-hover:scale-105"
                                        />
                                    </button>
                                ))}
                            </div>
                        </section>
                    ))
                )}
            </div>

            {current && (
                <div
                    className="fixed inset-0 z-50 flex flex-col bg-black/90"
                    role="dialog"
                    aria-modal="true"
                    aria-label="Photo viewer"
                    onClick={close}
                >
                    <div className="flex items-center justify-between gap-3 p-3 text-white" onClick={(e) => e.stopPropagation()}>
                        <p className="min-w-0 truncate text-sm">
                            {formatDate(current.taken_on)}
                            {current.uploader ? ` · ${current.uploader}` : ''}
                            <span className="ml-2 opacity-60">
                                {viewing! + 1} / {filtered.length}
                            </span>
                        </p>
                        <div className="flex items-center gap-1">
                            <Button variant="ghost" size="sm" className="gap-1.5 text-white hover:bg-white/15 hover:text-white" asChild>
                                <a href={`/daily-logs?project=${project.id}`}>
                                    <ExternalLink className="h-4 w-4" />
                                    View log
                                </a>
                            </Button>
                            {current.can_delete && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="gap-1.5 text-white hover:bg-white/15 hover:text-white"
                                    onClick={() => removePhoto(current)}
                                >
                                    <Trash2 className="h-4 w-4" />
                                    Delete
                                </Button>
                            )}
                            <Button variant="ghost" size="icon" className="text-white hover:bg-white/15 hover:text-white" onClick={close}>
                                <X className="h-5 w-5" />
                            </Button>
                        </div>
                    </div>

                    <div className="relative flex min-h-0 flex-1 items-center justify-center p-3">
                        {filtered.length > 1 && (
                            <button
                                type="button"
                                className="absolute left-3 z-10 rounded-full bg-white/10 p-2 text-white hover:bg-white/25"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    step(-1);
                                }}
                                aria-label="Previous photo"
                            >
                                <ChevronLeft className="h-6 w-6" />
                            </button>
                        )}
                        <img
                            src={current.full_url}
                            alt={current.original_name}
                            className="max-h-full max-w-full rounded object-contain"
                            onClick={(e) => e.stopPropagation()}
                        />
                        {filtered.length > 1 && (
                            <button
                                type="button"
                                className="absolute right-3 z-10 rounded-full bg-white/10 p-2 text-white hover:bg-white/25"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    step(1);
                                }}
                                aria-label="Next photo"
                            >
                                <ChevronRight className="h-6 w-6" />
                            </button>
                        )}
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
