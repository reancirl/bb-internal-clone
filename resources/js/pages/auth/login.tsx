import { Head, useForm } from '@inertiajs/react';
import { Eye, EyeOff, LoaderCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

type LoginForm = {
    email: string;
    password: string;
    remember: boolean;
};

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
}

/** Shared field chrome: 44px touch target, quiet resting state, clear focus and error. */
const field =
    'h-11 rounded-lg border-border/80 bg-card px-3.5 text-base sm:text-[0.9375rem] shadow-xs transition-[color,box-shadow,border-color] duration-150 ' +
    'hover:border-border focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/15 focus-visible:ring-offset-0 ' +
    'aria-[invalid=true]:border-destructive aria-[invalid=true]:focus-visible:ring-destructive/15';

const labelClass = 'text-foreground/70 text-xs font-medium tracking-wide';

export default function Login({ status, canResetPassword }: LoginProps) {
    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
    });
    const [showPassword, setShowPassword] = useState(false);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout title="Welcome back" description="Sign in to manage projects, material orders and the week's jobs.">
            <Head title="Sign in" />

            {status && (
                <div role="status" className="border-border bg-card text-foreground mb-6 rounded-lg border px-4 py-3 text-sm shadow-xs">
                    {status}
                </div>
            )}

            <form className="flex flex-col gap-[1.125rem] sm:gap-5" onSubmit={submit}>
                <div className="space-y-2">
                    <Label htmlFor="email" className={labelClass}>
                        Email address
                    </Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        autoFocus
                        tabIndex={1}
                        autoComplete="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="you@buffalobuilt.com"
                        aria-invalid={!!errors.email}
                        className={field}
                    />
                    <InputError message={errors.email} />
                </div>

                <div className="space-y-2">
                    <div className="flex items-baseline justify-between gap-3">
                        <Label htmlFor="password" className={labelClass}>
                            Password
                        </Label>
                        {canResetPassword && (
                            <TextLink
                                href={route('password.request')}
                                className="text-muted-foreground hover:text-foreground text-xs font-normal no-underline"
                                tabIndex={5}
                            >
                                Forgot password?
                            </TextLink>
                        )}
                    </div>
                    <div className="relative">
                        <Input
                            id="password"
                            type={showPassword ? 'text' : 'password'}
                            required
                            tabIndex={2}
                            autoComplete="current-password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            placeholder="••••••••"
                            aria-invalid={!!errors.password}
                            className={`${field} pr-11`}
                        />
                        <button
                            type="button"
                            onClick={() => setShowPassword((s) => !s)}
                            aria-label={showPassword ? 'Hide password' : 'Show password'}
                            aria-pressed={showPassword}
                            tabIndex={-1}
                            className="text-muted-foreground hover:text-foreground hover:bg-accent focus-visible:ring-ring absolute inset-y-1.5 right-1.5 flex w-8 items-center justify-center rounded-md transition-colors focus-visible:ring-2 focus-visible:outline-hidden"
                        >
                            {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                        </button>
                    </div>
                    <InputError message={errors.password} />
                </div>

                <div className="flex items-center gap-2.5 pt-0.5">
                    <Checkbox
                        id="remember"
                        name="remember"
                        tabIndex={3}
                        checked={data.remember}
                        onCheckedChange={(checked) => setData('remember', checked === true)}
                        className="size-4 rounded-[5px]"
                    />
                    <Label htmlFor="remember" className="text-muted-foreground text-sm font-normal select-none">
                        Keep me signed in
                    </Label>
                </div>

                <Button
                    type="submit"
                    tabIndex={4}
                    disabled={processing}
                    className="mt-1 h-11 w-full rounded-lg text-sm font-medium shadow-sm transition-all duration-150 hover:shadow-md active:scale-[0.995] disabled:cursor-not-allowed"
                >
                    {processing && <LoaderCircle className="size-4 animate-spin" />}
                    {processing ? 'Signing in…' : 'Sign in'}
                </Button>
            </form>

            <p className="text-muted-foreground/70 mt-7 text-center text-xs sm:mt-8">Need access? Contact your administrator.</p>
        </AuthLayout>
    );
}
