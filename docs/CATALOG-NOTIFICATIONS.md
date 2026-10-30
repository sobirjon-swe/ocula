# Catalog staff notifications

`TELEGRAM_DIRECTOR_CHAT_ID` selects the director chat. The first active director user's locale selects the message language; Uzbek Cyrillic is derived from the Uzbek Latin translation. Without a configured chat, catalog notification jobs are not dispatched.

A newly created pending product queues `NotifyPendingProduct` after its database transaction commits. The job verifies that approval is still pending. `staff_notifications` records one notification per `pending-product:{id}` with a unique dedupe key, locale, message, status, attempt count, and sent timestamp.

`GET /api/v1/products/duplicates` requires product approval or merge permission and returns up to 100 probable duplicate pairs. PostgreSQL trigram similarity uses normalized/transliterated search keys, a 0.45 threshold, matching product types, and excludes inactive, rejected, deleted, and merged source products. The result is a review aid; it never merges automatically.

`catalog:report-duplicates` queues a director report, scheduled Mondays at 08:30 Asia/Tashkent. Reports deduplicate by ISO week. Empty reports remain quiet. The message includes up to ten shortened pair previews; the endpoint provides the review data.

Jobs use the application's configured queue and retry delivery three times with a 60-second backoff. Concurrent delivery attempts serialize on the notification journal row. Failed provider calls store only `delivery_failed`; raw exceptions and bot-token URLs are not persisted or rethrown. Failed entries remain retryable; sent entries are skipped.

Tests use fake HTTP with stray requests forbidden and exercise queued after-commit metadata, deduplication, localized messages, sanitized failures, retry recovery, fuzzy matches, permissions, and weekly report identity.
