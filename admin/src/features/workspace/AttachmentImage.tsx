import { useEffect, useState } from 'react'
import { fetchBlob } from '@/lib/api/client'
import { useWords } from './copy'
import { ErrorNotice } from './shared'
export function AttachmentImage({ path }: { path: string }) {
    const w = useWords(),
        [url, setUrl] = useState(''),
        [error, setError] = useState<unknown>(null),
        [open, setOpen] = useState(false)
    useEffect(() => {
        if (!open) return
        let active = true,
            objectUrl = ''
        void fetchBlob(path)
            .then((blob) => {
                objectUrl = URL.createObjectURL(blob)
                if (active) setUrl(objectUrl)
            })
            .catch(setError)
        return () => {
            active = false
            if (objectUrl) URL.revokeObjectURL(objectUrl)
        }
    }, [open, path])
    return (
        <details
            className="w-full"
            onToggle={(e) => setOpen(e.currentTarget.open)}
        >
            <summary className="cursor-pointer py-2 text-sm">
                {w(
                    'Taksi cheki rasmi',
                    'Фото чека такси',
                    'Taxi receipt photo',
                )}
            </summary>
            <ErrorNotice error={error} />
            {url && (
                <img
                    className="max-h-96 max-w-full rounded-lg object-contain"
                    src={url}
                    alt={w('Taksi cheki', 'Чек такси', 'Taxi receipt')}
                />
            )}
        </details>
    )
}
