import { useState } from 'react'
import { Navigate } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { Spinner } from '@/components/ui/feedback'
import { Input } from '@/components/ui/input'
import { ApiError } from '@/lib/api/client'
import { useT } from '@/lib/i18n'
import { useSession } from '@/lib/session'
import { useWords } from '@/features/workspace/copy'
import { useLogin, usePinLogin } from './api'
export function LoginPage() {
    const t = useT(),
        w = useWords(),
        token = useSession((s) => s.token),
        login = useLogin(),
        pinLogin = usePinLogin(),
        [mode, setMode] = useState<'password' | 'pin'>('password'),
        [phone, setPhone] = useState(''),
        [password, setPassword] = useState(''),
        [pin, setPin] = useState(''),
        [deviceId, setDeviceId] = useState(
            () => localStorage.getItem('optika.device.id') ?? '',
        ),
        [deviceToken, setDeviceToken] = useState(
            () => localStorage.getItem('optika.device.token') ?? '',
        )
    if (token !== null) return <Navigate to="/" replace />
    const mutation = mode === 'pin' ? pinLogin : login,
        error = mutation.error instanceof ApiError ? mutation.error : null
    return (
        <div className="flex min-h-screen items-center justify-center p-6">
            <div className="w-full max-w-sm">
                <div className="mb-8 text-center">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t('login.title')}
                    </h1>
                    <p className="mt-1 text-sm text-neutral-500">
                        {t('login.subtitle')}
                    </p>
                </div>
                <div className="mb-4 flex gap-2">
                    <button
                        className={
                            mode === 'password'
                                ? 'action-primary flex-1'
                                : 'action-secondary flex-1'
                        }
                        onClick={() => setMode('password')}
                    >
                        {w('Parol', 'Пароль', 'Password')}
                    </button>
                    <button
                        className={
                            mode === 'pin'
                                ? 'action-primary flex-1'
                                : 'action-secondary flex-1'
                        }
                        onClick={() => setMode('pin')}
                    >
                        PIN
                    </button>
                </div>
                <form
                    className="space-y-4 rounded-2xl bg-white p-6 ring-1 ring-neutral-200"
                    onSubmit={(event) => {
                        event.preventDefault()
                        if (mode === 'pin') {
                            localStorage.setItem('optika.device.id', deviceId)
                            localStorage.setItem(
                                'optika.device.token',
                                deviceToken,
                            )
                            pinLogin.mutate({
                                device_id: Number(deviceId),
                                device_token: deviceToken,
                                pin,
                            })
                        } else login.mutate({ phone, password })
                    }}
                >
                    {mode === 'password' ? (
                        <>
                            <Input
                                label={t('login.phone')}
                                type="tel"
                                autoComplete="username"
                                autoFocus
                                placeholder="+998 90 123 45 67"
                                value={phone}
                                onChange={(event) =>
                                    setPhone(event.target.value)
                                }
                                error={error?.fieldError('phone')}
                                required
                            />
                            <Input
                                label={t('login.password')}
                                type="password"
                                autoComplete="current-password"
                                value={password}
                                onChange={(event) =>
                                    setPassword(event.target.value)
                                }
                                error={error?.fieldError('password')}
                                required
                            />
                        </>
                    ) : (
                        <>
                            <Input
                                label="PIN"
                                type="password"
                                inputMode="numeric"
                                pattern="[0-9]{4}"
                                maxLength={4}
                                autoComplete="off"
                                autoFocus
                                value={pin}
                                onChange={(e) => setPin(e.target.value)}
                                error={error?.fieldError('pin')}
                                required
                            />
                            <details open={!deviceId || !deviceToken}>
                                <summary className="cursor-pointer text-sm">
                                    {w(
                                        'Planshetni ulash',
                                        'Подключить планшет',
                                        'Connect shared device',
                                    )}
                                </summary>
                                <div className="mt-3 space-y-3">
                                    <Input
                                        label={w(
                                            'Qurilma raqami',
                                            'Номер устройства',
                                            'Device ID',
                                        )}
                                        type="number"
                                        min="1"
                                        value={deviceId}
                                        onChange={(e) =>
                                            setDeviceId(e.target.value)
                                        }
                                        required
                                    />
                                    <Input
                                        label={w(
                                            'Qurilma tokeni',
                                            'Токен устройства',
                                            'Device token',
                                        )}
                                        type="password"
                                        value={deviceToken}
                                        onChange={(e) =>
                                            setDeviceToken(e.target.value)
                                        }
                                        required
                                    />
                                </div>
                            </details>
                        </>
                    )}
                    {error && (
                        <p
                            role="alert"
                            className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700"
                        >
                            {error.message}
                        </p>
                    )}
                    <Button
                        type="submit"
                        className="w-full"
                        disabled={mutation.isPending}
                    >
                        {mutation.isPending && <Spinner />}
                        {mutation.isPending
                            ? t('login.submitting')
                            : t('login.submit')}
                    </Button>
                </form>
            </div>
        </div>
    )
}
