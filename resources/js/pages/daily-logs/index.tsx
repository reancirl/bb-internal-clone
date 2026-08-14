import { useEffect, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useConfirm } from '@/components/buffalobuilt/confirm-dialog';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  AlertTriangle,
  ChevronLeft,
  ChevronRight,
  CloudSun,
  ImagePlus,
  NotebookPen,
  Pencil,
  Plus,
  Thermometer,
  Trash2,
  Users,
  X,
} from 'lucide-react';
import { cn } from '@/lib/utils';

interface LogEntry {
  id: number;
  project_id: number;
  project_name: string;
  log_date: string;
  notes: string;
  weather: string | null;
  temperature_f: number | null;
  crew_present: string | null;
  issues: string | null;
  author: string;
  created_at: string;
  editable: boolean;
  photos: Array<{ id: number }>;
}

interface Paginated<T> {
  data: T[];
  links: Array<{ url: string | null; label: string; active: boolean }>;
  total: number;
}

interface ProjectOption {
  id: number;
  name: string;
}

interface DailyLogsProps {
  logs: Paginated<LogEntry>;
  projects: ProjectOption[];
  filters: { project: number | null };
  weatherOptions: string[];
}

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
  { title: 'Daily Logs', href: '/daily-logs' },
];

function formatDate(date: string): string {
  const [y, m, d] = date.split('-').map(Number);
  return new Date(y, m - 1, d).toLocaleDateString('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

function todayISO(): string {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}

export default function DailyLogs({ logs, projects, filters, weatherOptions }: DailyLogsProps) {
  const [confirm, confirmDialog] = useConfirm();
  const [modal, setModal] = useState<'new' | LogEntry | null>(null);
  const [saving, setSaving] = useState(false);
  const [lightbox, setLightbox] = useState<{ photos: Array<{ id: number }>; index: number } | null>(null);

  // Keep the edit modal's log in sync with fresh server props (e.g. after a
  // photo upload/delete round-trip refreshes the page data).
  const modalState =
    modal !== null && modal !== 'new' ? (logs.data.find((l) => l.id === modal.id) ?? modal) : modal;

  const filterByProject = (value: string) => {
    router.get('/daily-logs', value === 'all' ? {} : { project: value }, { preserveState: true });
  };

  const deleteLog = async (log: LogEntry) => {
    const ok = await confirm({
      title: 'Delete this log?',
      description: `The ${formatDate(log.log_date)} log for ${log.project_name} will be permanently deleted.`,
      confirmLabel: 'Delete',
      destructive: true,
    });
    if (ok) router.delete(`/daily-logs/${log.id}`, { preserveScroll: true });
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Daily Logs" />
      {confirmDialog}

      <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
        {/* Header */}
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h1 className="text-foreground text-2xl font-semibold">Daily Logs</h1>
            <p className="text-muted-foreground text-sm">
              Field documentation — what happened on site, day by day.
            </p>
          </div>
          <div className="flex items-center gap-2">
            <Select value={filters.project?.toString() ?? 'all'} onValueChange={filterByProject}>
              <SelectTrigger className="w-52">
                <SelectValue placeholder="All projects" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All projects</SelectItem>
                {projects.map((p) => (
                  <SelectItem key={p.id} value={p.id.toString()}>
                    {p.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Button className="gap-2" onClick={() => setModal('new')}>
              <Plus className="h-4 w-4" />
              New Log
            </Button>
          </div>
        </div>

        {/* Feed */}
        {logs.data.length === 0 ? (
          <div className="flex min-h-64 flex-1 flex-col items-center justify-center gap-3 rounded-lg border border-dashed p-8 text-center">
            <NotebookPen className="text-muted-foreground/40 h-10 w-10" />
            <div>
              <p className="text-foreground font-medium">No logs yet</p>
              <p className="text-muted-foreground text-sm">
                Record the first daily log — progress, weather, crew, and any issues.
              </p>
            </div>
            <Button className="gap-2" onClick={() => setModal('new')}>
              <Plus className="h-4 w-4" />
              New Log
            </Button>
          </div>
        ) : (
          <div className="space-y-3">
            {logs.data.map((log) => (
              <Card key={log.id}>
                <CardContent className="space-y-2 p-4">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="font-semibold">{formatDate(log.log_date)}</span>
                      <Link
                        href={`/projects/${log.project_id}`}
                        className="text-blue-600 hover:underline dark:text-blue-400"
                      >
                        {log.project_name}
                      </Link>
                      {log.weather && (
                        <Badge variant="secondary" className="gap-1">
                          <CloudSun className="h-3 w-3" />
                          {log.weather}
                        </Badge>
                      )}
                      {log.temperature_f !== null && (
                        <Badge variant="secondary" className="gap-1 tabular-nums">
                          <Thermometer className="h-3 w-3" />
                          {log.temperature_f}°F
                        </Badge>
                      )}
                    </div>
                    {log.editable && (
                      <div className="flex gap-0.5">
                        <Button
                          variant="ghost"
                          size="icon"
                          className="text-muted-foreground h-7 w-7"
                          onClick={() => setModal(log)}
                        >
                          <Pencil className="h-3.5 w-3.5" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="icon"
                          className="text-muted-foreground hover:text-destructive h-7 w-7"
                          onClick={() => deleteLog(log)}
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                      </div>
                    )}
                  </div>

                  <p className="text-sm whitespace-pre-wrap">{log.notes}</p>

                  {log.photos.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                      {log.photos.map((photo, i) => (
                        <button
                          key={photo.id}
                          type="button"
                          onClick={() => setLightbox({ photos: log.photos, index: i })}
                          className="focus-visible:ring-ring overflow-hidden rounded-md border transition-opacity hover:opacity-80 focus-visible:ring-2 focus-visible:outline-none"
                        >
                          <img
                            src={`/daily-log-photos/${photo.id}/thumb`}
                            alt="Jobsite photo"
                            loading="lazy"
                            className="h-20 w-20 object-cover"
                          />
                        </button>
                      ))}
                    </div>
                  )}

                  {log.issues && (
                    <div className="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 p-2.5 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                      <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                      <span className="whitespace-pre-wrap">{log.issues}</span>
                    </div>
                  )}

                  <div className="text-muted-foreground flex flex-wrap items-center gap-3 text-xs">
                    <span>by {log.author}</span>
                    {log.crew_present && (
                      <span className="flex items-center gap-1">
                        <Users className="h-3 w-3" />
                        {log.crew_present}
                      </span>
                    )}
                  </div>
                </CardContent>
              </Card>
            ))}

            {/* Pagination */}
            {logs.links.length > 3 && (
              <div className="flex flex-wrap justify-center gap-1 pt-2">
                {logs.links.map((link, i) =>
                  link.url ? (
                    <Button
                      key={i}
                      variant={link.active ? 'default' : 'outline'}
                      size="sm"
                      onClick={() => router.get(link.url!, {}, { preserveState: true })}
                      dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                  ) : (
                    <Button
                      key={i}
                      variant="outline"
                      size="sm"
                      disabled
                      dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                  ),
                )}
              </div>
            )}
          </div>
        )}
      </div>

      <LogFormModal
        state={modalState}
        projects={projects}
        weatherOptions={weatherOptions}
        defaultProjectId={filters.project}
        saving={saving}
        setSaving={setSaving}
        onClose={() => setModal(null)}
      />

      {/* Lightbox */}
      <Dialog open={lightbox !== null} onOpenChange={(open) => !open && setLightbox(null)}>
        <DialogContent className="sm:max-w-3xl">
          {lightbox && (
            <>
              <DialogHeader>
                <DialogTitle>
                  Photo {lightbox.index + 1} of {lightbox.photos.length}
                </DialogTitle>
                <DialogDescription className="sr-only">Jobsite photo viewer</DialogDescription>
              </DialogHeader>
              <div className="relative flex items-center justify-center">
                <img
                  src={`/daily-log-photos/${lightbox.photos[lightbox.index].id}`}
                  alt="Jobsite photo"
                  className="max-h-[70vh] w-auto rounded-md object-contain"
                />
                {lightbox.photos.length > 1 && (
                  <>
                    <Button
                      variant="secondary"
                      size="icon"
                      className="absolute left-2"
                      onClick={() =>
                        setLightbox({
                          ...lightbox,
                          index: (lightbox.index - 1 + lightbox.photos.length) % lightbox.photos.length,
                        })
                      }
                    >
                      <ChevronLeft className="h-4 w-4" />
                    </Button>
                    <Button
                      variant="secondary"
                      size="icon"
                      className="absolute right-2"
                      onClick={() => setLightbox({ ...lightbox, index: (lightbox.index + 1) % lightbox.photos.length })}
                    >
                      <ChevronRight className="h-4 w-4" />
                    </Button>
                  </>
                )}
              </div>
            </>
          )}
        </DialogContent>
      </Dialog>
    </AppLayout>
  );
}

function FieldError({ message }: { message?: string }) {
  if (!message) return null;
  return <p className="text-destructive text-xs">{message}</p>;
}

function LogFormModal({
  state,
  projects,
  weatherOptions,
  defaultProjectId,
  saving,
  setSaving,
  onClose,
}: {
  state: 'new' | LogEntry | null;
  projects: ProjectOption[];
  weatherOptions: string[];
  defaultProjectId: number | null;
  saving: boolean;
  setSaving: (v: boolean) => void;
  onClose: () => void;
}) {
  const { errors } = usePage().props;
  const isEdit = state !== null && state !== 'new';

  const [projectId, setProjectId] = useState('');
  const [logDate, setLogDate] = useState(todayISO());
  const [notes, setNotes] = useState('');
  const [weather, setWeather] = useState('none');
  const [temperature, setTemperature] = useState('');
  const [crew, setCrew] = useState('');
  const [issues, setIssues] = useState('');
  // New-log mode: photos are held locally and submitted with the log.
  const [pendingPhotos, setPendingPhotos] = useState<File[]>([]);

  useEffect(() => {
    setPendingPhotos([]);
    if (state === 'new') {
      setProjectId(defaultProjectId?.toString() ?? '');
      setLogDate(todayISO());
      setNotes('');
      setWeather('none');
      setTemperature('');
      setCrew('');
      setIssues('');
    } else if (state) {
      setProjectId(state.project_id.toString());
      setLogDate(state.log_date);
      setNotes(state.notes);
      setWeather(state.weather ?? 'none');
      setTemperature(state.temperature_f?.toString() ?? '');
      setCrew(state.crew_present ?? '');
      setIssues(state.issues ?? '');
    }
  }, [state, defaultProjectId]);

  const submit = () => {
    if (!notes.trim() || (!isEdit && !projectId)) return;

    const payload = {
      log_date: logDate,
      notes: notes.trim(),
      weather: weather !== 'none' ? weather : null,
      temperature_f: temperature.trim() !== '' ? parseInt(temperature) : null,
      crew_present: crew.trim() || null,
      issues: issues.trim() || null,
    };

    const options = {
      preserveScroll: true,
      onStart: () => setSaving(true),
      onFinish: () => setSaving(false),
      onSuccess: onClose,
    };

    if (isEdit) {
      router.put(`/daily-logs/${state.id}`, payload, options);
    } else {
      // File objects make Inertia send multipart form data automatically.
      router.post(
        '/daily-logs',
        { ...payload, project_id: parseInt(projectId), photos: pendingPhotos },
        options,
      );
    }
  };

  const addFiles = (list: FileList | null) => {
    if (!list) return;
    const existing = isEdit ? state.photos.length : pendingPhotos.length;
    const room = 10 - existing;
    const files = Array.from(list).slice(0, Math.max(0, room));
    if (files.length === 0) return;

    if (isEdit) {
      // Edit mode: upload immediately against the existing log.
      router.post(`/daily-logs/${state.id}/photos`, { photos: files }, {
        preserveScroll: true,
        forceFormData: true,
        onStart: () => setSaving(true),
        onFinish: () => setSaving(false),
      });
    } else {
      setPendingPhotos((prev) => [...prev, ...files]);
    }
  };

  return (
    <Dialog open={state !== null} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{isEdit ? 'Edit Daily Log' : 'New Daily Log'}</DialogTitle>
          <DialogDescription>
            {isEdit ? 'Update this site log.' : "What happened on site today? Notes are the only required field."}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-2">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="log_project">Project *</Label>
              <Select value={projectId} onValueChange={setProjectId} disabled={isEdit}>
                <SelectTrigger id="log_project">
                  <SelectValue placeholder="Select project" />
                </SelectTrigger>
                <SelectContent>
                  {projects.map((p) => (
                    <SelectItem key={p.id} value={p.id.toString()}>
                      {p.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <FieldError message={errors.project_id} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="log_date">Date *</Label>
              <Input id="log_date" type="date" value={logDate} onChange={(e) => setLogDate(e.target.value)} />
              <FieldError message={errors.log_date} />
            </div>
          </div>

          <div className="space-y-2">
            <Label htmlFor="log_notes">Work completed / progress *</Label>
            <Textarea
              id="log_notes"
              placeholder="e.g. Finished framing west elevation. Truss delivery arrived at 10am, staged on north side."
              rows={4}
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              autoFocus
            />
            <FieldError message={errors.notes} />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="log_weather">Weather</Label>
              <Select value={weather} onValueChange={setWeather}>
                <SelectTrigger id="log_weather">
                  <SelectValue placeholder="—" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">Not recorded</SelectItem>
                  {weatherOptions.map((w) => (
                    <SelectItem key={w} value={w}>
                      {w}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="log_temp">Temperature (°F)</Label>
              <Input
                id="log_temp"
                type="number"
                min="-60"
                max="130"
                placeholder="e.g. 78"
                value={temperature}
                onChange={(e) => setTemperature(e.target.value)}
              />
              <FieldError message={errors.temperature_f} />
            </div>
          </div>

          <div className="space-y-2">
            <Label htmlFor="log_crew">Crew on site</Label>
            <Input
              id="log_crew"
              placeholder="e.g. Wyatt, Matt, Joe + concrete sub (3)"
              value={crew}
              onChange={(e) => setCrew(e.target.value)}
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="log_issues">Issues / delays</Label>
            <Textarea
              id="log_issues"
              placeholder="e.g. Windows backordered — reschedule install. Rain stopped exterior work at 2pm."
              rows={2}
              value={issues}
              onChange={(e) => setIssues(e.target.value)}
            />
          </div>

          {/* Photos */}
          <div className="space-y-2">
            <Label htmlFor="log_photos">Photos ({isEdit ? state.photos.length : pendingPhotos.length}/10)</Label>
            <div className="flex flex-wrap gap-2">
              {/* Existing photos (edit mode) — deleting is immediate */}
              {isEdit &&
                state.photos.map((photo) => (
                  <div key={photo.id} className="relative">
                    <img
                      src={`/daily-log-photos/${photo.id}/thumb`}
                      alt="Jobsite photo"
                      className="h-20 w-20 rounded-md border object-cover"
                    />
                    <button
                      type="button"
                      aria-label="Remove photo"
                      className="bg-destructive text-destructive-foreground absolute -top-1.5 -right-1.5 flex h-5 w-5 items-center justify-center rounded-full shadow"
                      onClick={() =>
                        router.delete(`/daily-log-photos/${photo.id}`, { preserveScroll: true })
                      }
                    >
                      <X className="h-3 w-3" />
                    </button>
                  </div>
                ))}

              {/* Pending photos (new mode) — local previews, removable before save */}
              {!isEdit &&
                pendingPhotos.map((file, i) => (
                  <div key={`${file.name}-${i}`} className="relative">
                    <img
                      src={URL.createObjectURL(file)}
                      alt={file.name}
                      className="h-20 w-20 rounded-md border object-cover"
                      onLoad={(e) => URL.revokeObjectURL((e.target as HTMLImageElement).src)}
                    />
                    <button
                      type="button"
                      aria-label="Remove photo"
                      className="bg-destructive text-destructive-foreground absolute -top-1.5 -right-1.5 flex h-5 w-5 items-center justify-center rounded-full shadow"
                      onClick={() => setPendingPhotos((prev) => prev.filter((_, j) => j !== i))}
                    >
                      <X className="h-3 w-3" />
                    </button>
                  </div>
                ))}

              {/* Add button */}
              {(isEdit ? state.photos.length : pendingPhotos.length) < 10 && (
                <label
                  htmlFor="log_photos"
                  className="border-muted-foreground/30 text-muted-foreground hover:border-muted-foreground/60 hover:text-foreground flex h-20 w-20 cursor-pointer flex-col items-center justify-center gap-1 rounded-md border border-dashed text-xs transition-colors"
                >
                  <ImagePlus className="h-5 w-5" />
                  Add
                </label>
              )}
            </div>
            <input
              id="log_photos"
              type="file"
              accept="image/jpeg,image/png,image/webp,image/gif"
              multiple
              className="hidden"
              onChange={(e) => {
                addFiles(e.target.files);
                e.target.value = '';
              }}
            />
            <FieldError message={errors.photos ?? errors['photos.0']} />
            <p className="text-muted-foreground text-xs">
              JPEG, PNG, or WebP up to 15MB each — automatically resized. On phones this opens the camera roll.
            </p>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button onClick={submit} disabled={saving || !notes.trim() || (!isEdit && !projectId)}>
            {saving ? 'Saving…' : isEdit ? 'Save Changes' : 'Add Log'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
