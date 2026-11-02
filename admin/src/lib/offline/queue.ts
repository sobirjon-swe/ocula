export type PendingOperation = {
  key: string
  path: string
  body: Record<string, unknown>
  createdAt: string
  error?: string
}

type Store = Pick<Storage, 'getItem' | 'setItem' | 'removeItem'>

/** Durable, account-scoped FIFO. Tokens are deliberately never persisted here. */
export function createOutbox(storage: Store, owner: number) {
  const storageKey = `optika.driver.outbox.${owner}`
  let flushing = false
  function list(): PendingOperation[] {
    const raw = storage.getItem(storageKey)
    if (raw === null) return []
    // Corrupt storage must be surfaced, never silently erase a delivery.
    const parsed: unknown = JSON.parse(raw)
    if (!Array.isArray(parsed)) throw new Error('Saqlangan amallarni o‘qib bo‘lmadi.')
    return parsed as PendingOperation[]
  }
  function save(operations: PendingOperation[]) {
    storage.setItem(storageKey, JSON.stringify(operations))
  }
  function discard(key: string) { save(list().filter(op => op.key !== key)) }
  function retry(key: string) {
    save(list().map(op => {
      if (op.key !== key) return op
      const next = { ...op }
      delete next.error
      return next
    }))
  }
  return {
    list, discard, retry,
    enqueue(path: string, body: Record<string, unknown>): PendingOperation {
      const operation = { key: crypto.randomUUID(), path, body, createdAt: new Date().toISOString() }
      save([...list(), operation])
      return operation
    },
    async flush(currentOwner: () => number | undefined, send: (op: PendingOperation) => Promise<unknown>) {
      if (flushing || currentOwner() !== owner) return
      flushing = true
      try {
        while (currentOwner() === owner) {
          const operation = list()[0]
          if (!operation || operation.error) break
          try {
            await send(operation)
            discard(operation.key)
          } catch (error) {
            const failure = error as { status?: number; message?: string }
            if (failure.status && failure.status >= 400 && failure.status < 500) {
              save(list().map(op => op.key === operation.key ? { ...op, error: failure.message ?? 'So‘rovni tekshiring.' } : op))
            }
            break
          }
        }
      } finally { flushing = false }
    },
  }
}
