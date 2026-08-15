# BOSQICH 7 — TELEGRAM BOT (bildirishnoma)

> Manba: `docs/PROJECT.md` §11 (Bosqich 7), §6.7 (Telegram Bot API), 7.6
> (qarz eslatmasi), 7.11 (retsept muddati), §9 ("Muhim hodisalar Event
> chiqaradi... → Listener Telegram xabar yuboradi").
> Sana: 2026-08-15. Holat: **bajarilgan va yashil**.
>
> Qolip `BOSQICH-3…6` bilan bir xil. Ziddiyat chiqsa —
> `PROJECT.md`/`SCHEMA.md` yutadi, keyin §5 (farq qilgan qarorlar).

---

## 1. Hajm

**Kiradi:** Telegram Bot API bilan bir tomonlama bog'lanish (chiquvchi
xabar), mijozni botga bog'lash (`/start <kod>`), uchta bildirishnoma —
buyurtma tayyor (7.3), qarz eslatmasi (7.6), ko'rik/retsept eslatmasi
(7.11) — va shu loyihadagi **birinchi** Event/Listener juftligi hamda
**birinchi** scheduler yozuvi (`routes/console.php`).

**Kirmaydi:** Mijoz kabineti / Mini App (Bosqich 9) — u interaktiv,
Telegram `initData` orqali autentifikatsiya qiladi va o'z modulida
keladi. Bu bosqichda bot **faqat push-bildirishnoma** yuboradi va bitta
oddiy komanda (`/start`) bilan bog'lanadi. SMS zaxira kanali
(Eskiz.uz/Play Mobile) ham keyingi bosqich. To'liq `debts` /
`debt_reminders` moliyaviy registri (Finance/Bosqich 10) — bu yerda
mavjud `orders.debt` / `orders.due_date` ustunlaridan foydalanildi (§5
#2).

---

## 2. Migratsiyalar

| Fayl | Jadvallar |
|---|---|
| `0001_01_01_000410_create_telegram_tables.php` | `telegram_link_codes`, `telegram_notifications` |

`telegram_link_codes` — mijozni Telegramga bog'lash uchun bir martalik
kod (pastda §3). `telegram_notifications` — yuborilgan har bir xabar
jurnali: audit uchun va **dublikatga qarshi** (`dedupe_key`, unique).

---

## 3. Mijozni botga bog'lash

Bosqich 9 (Mini App) hali yo'q, shuning uchun `customers.telegram_id`
(u allaqachon Bosqich 3 sxemasida bor edi) **qo'lda, oddiy oqim** bilan
to'ldiriladi:

```
Sotuvchi mijoz kartochkasida "Telegram ulash" bosadi
  → POST /customers/{id}/telegram-link-code
  → 6 xonali kod, 15 daqiqa amal qiladi (telegram_link_codes)
  → mijoz botga /start <kod> yuboradi
  → webhook kodni tekshiradi, customers.telegram_id = chat_id
  → bot tasdiq xabari yuboradi
```

Kod muddati o'tgan yoki ishlatilgan bo'lsa — bot xato xabari yuboradi,
mijoz sotuvchidan yangi kod so'raydi. Ruxsat alohida ixtiro qilinmadi:
kod yaratish `sales.customer.update` ostida — bu mijoz kartochkasini
tahrirlashning bir qismi (§5 #1).

---

## 4. Bildirishnomalar

### 4.1 Buyurtma tayyor — `OrderReady` (7.3)

`ChangeOrderStatus::handle()` da holat `ready` ga o'tganda **domen
hodisasi** otiladi (loyihada birinchi marta):

```
ChangeOrderStatus(→ ready)  →  event(OrderReady)  →  NotifyCustomerOrderReady (queued)
```

Listener navbatga tushadi (`ShouldQueue`) — HTTP so'rov mijozning
javobini kutib turmaydi. Mijozda `telegram_id` yo'q bo'lsa — jimgina
o'tkazib yuboriladi (sotuvchi qo'lda qo'ng'iroq qiladi, xato emas).

Bu yerda **dedupe kaliti ishlatilmaydi** — kerak emas: `ChangeOrderStatus`
bir xil holatga qaytadan o'tishni allaqachon jimgina rad etadi
(`$order->status === $target` bo'lsa, hodisa umuman otilmaydi), shuning
uchun takroriy so'rov ikkinchi xabar yubormaydi. `rework` dan qayta
`ready` ga qaytish esa qonuniy, **yangi** o'tish (ENUMS.md §4) — har
safar chinakam qayta tayyor bo'lganda xabar to'g'ri ravishda qayta ketadi.

### 4.2 Qarz eslatmasi — `telegram:remind-debts` (7.6)

Alohida jadval (`debts`) hali yo'q (§5 #2), shuning uchun buyruq
to'g'ridan-to'g'ri `orders` dan o'qiydi: `debt > 0` va `due_date`
sozlamadagi eslatma kunlariga (`SettingKey::DebtReminderDays`, standart
`[-1, 0, 3, 7]`) mos kelgan buyurtmalarni topadi. Har (buyurtma, kun)
juftligi uchun bitta xabar — `dedupe_key` shuni ta'minlaydi, ertasi kuni
buyruq qayta ishga tushsa eski kunlar qayta yuborilmaydi.

Xodim qo'lda ham yubora oladi: `POST /orders/{order}/debt-reminder`
(`finance.debt.remind` — bu ruxsat `RolePermissionSeeder`da allaqachon
bor edi, hech kim ishlatmagan edi, §5 #3). Qo'lda yuborish dedupe'ni
chetlab o'tadi — xodim ataylab qayta eslatmoqchi.

### 4.3 Ko'rik eslatmasi — `telegram:remind-checkups` (7.11)

`prescriptions.valid_until` yaqinlashganda (`SettingKey::CheckupReminderDaysBefore`,
standart 14 kun) mijozga "Retseptingiz muddati tugayapti, qayta ko'rikka
kelishingiz mumkin" xabari. Bitta retseptga bitta xabar (`dedupe_key`).

---

## 5. Rejadan/hujjatdan farq qilgan qarorlar

1. **Bog'lash kodi `sales.customer.update` ostida.** Alohida
   `sales.customer.telegram_link` ruxsati o'ylab topilmadi — bu mijoz
   kartochkasini boshqarishning bir qismi, xuddi Bosqich 6 da zaxiraga
   berish `adjustment.create` ostida bo'lgani kabi.

2. **`debts`/`debt_reminders` o'rniga mavjud `orders.debt`/`orders.due_date`.**
   SCHEMA.md bu ikki jadvalni Finance registrining bir qismi sifatida
   yozadi (`debt_id` tashqi kalit bilan), lekin `debts` entity'si hali
   qurilmagan (Bosqich 10, §11). Uni endi, faqat eslatma yuborish uchun,
   qisman qurish keyinroq ikki marta ish qilishga olib kelardi. Buning
   o'rniga `telegram_notifications` — **umumiy** (faqat qarz emas)
   jurnal, `source_type`/`source_id` orqali istalgan hujjatga ishora
   qiladi (`stock_movements` uslubida). Bosqich 10 to'liq `debts`
   registrini qurganda, shu jurnal o'zgarishsiz qoladi — u faqat
   "yuborildimi" savoliga javob beradi, qarz hisobini emas.

3. **`finance.debt.remind` birinchi marta ishlatildi.** Ruxsat
   `RolePermissionSeeder`da Bosqich 3 dan beri bor edi (director,
   accountant), lekin uni ishlatuvchi endpoint yo'q edi — endi bor.

4. **`telegram_notifications` "moliyaviy hujjat" emas** (7.21) — u
   oddiy jurnal, `Immutable` trait qo'llanmadi. Xato bo'lib qolsa,
   qayta yozish emas, tizim shunchaki qayta urinadi (dedupe kaliti
   band bo'lmasa).

5. **Webhook ochiq (`auth:sanctum` yo'q).** Telegram serveri Sanctum
   token bilan kelmaydi — o'rniga `X-Telegram-Bot-Api-Secret-Token`
   sarlavhasi `TELEGRAM_WEBHOOK_SECRET` bilan solishtiriladi
   (Telegram'ning o'zi shu mexanizmni taklif qiladi — `setWebhook`
   so'rovida `secret_token`).

6. **Chiquvchi so'rov `App\Modules\Telegram\Services\TelegramClient`
   orqali, interfeyssiz.** `ReceiptPrinter` (7.16) dagi kabi almashtirish
   ehtimoli yo'q — Telegram provayder emas, mahsulot qarori. Testlarda
   `Http::fake()` yetarli.

7. **Admin SPA ekrani yo'q.** Oldingi bosqichlar kabi (Bosqich 3–6)
   backend API tayyor, ekran keyingi ish.

---

## 6. API

```
POST   /customers/{id}/telegram-link-code   — bog'lash kodi yaratish (15 daqiqa)
POST   /telegram/webhook                    — Telegram yangiliklari (ochiq, secret token bilan)

POST   /orders/{order}/debt-reminder        — qarz eslatmasini qo'lda yuborish
```

`telegram/webhook` `idempotency` ostida emas — Telegram o'zi qayta
urinishni kam qiladi va bu yerda pul/ombor harakati yo'q.

## 7. Konsol

```
telegram:remind-debts      — kunlik, ertalab (schedule)
telegram:remind-checkups   — kunlik, ertalab (schedule)
telegram:webhook:set       — webhook URL o'rnatish (deploy vaqtida qo'lda)
```

`routes/console.php` da birinchi marta `Schedule::command(...)`
ishlatildi.

---

## 8. Testlar

| Fayl | Kafolat |
|---|---|
| `Feature/Telegram/OrderReadyNotificationTest.php` | Buyurtma `ready` ga o'tganda bog'langan mijozga xabar ketadi; bog'lanmagan mijozda HTTP so'rov umuman yuborilmaydi; qayta `ready` ga qaytganda xabar yana ketadi |
| `Feature/Telegram/TelegramLinkTest.php` | To'g'ri kod mijozni bog'laydi va bir martalik bo'lib qoladi; muddati o'tgan/noto'g'ri kod rad etiladi; noto'g'ri webhook sekret 403 qaytaradi |
| `Feature/Telegram/DebtReminderCommandTest.php` | Eslatma kunlariga mos buyurtmaga xabar ketadi; mos kelmagan kunga ketmaydi; ikkinchi ishga tushirishda dublikat ketmaydi (`dedupe_key`) |
| `Feature/Telegram/DebtReminderApiTest.php` | Qo'lda yuborish `finance.debt.remind` bilan ishlaydi, sotuvchida yo'q |
| `Feature/Telegram/CheckupReminderCommandTest.php` | Muddati yaqinlashgan retseptga xabar ketadi, uzoq muddatlisiga ketmaydi |

Barcha testlarda chiquvchi HTTP `Http::fake()` bilan ushlanadi — hech
qanday test haqiqiy Telegram serveriga chiqmaydi.

---

## 9. Bosqich 7 dan keyin

1. Bosqich 8 — Yetkazish: `trips`, haydovchi PWA, GPS tasdiq — mijozga
   "yetkazildimi?" savoli ham shu bot orqali ketishi mumkin (7.4), lekin
   bu alohida ish (Delivery moduli tayyor bo'lgach).
2. Bosqich 9 — Mijoz kabineti (Mini App): shu bosqichda qurilgan
   `customers.telegram_id` va bog'lash tushunchasi asos bo'ladi, lekin
   autentifikatsiya Telegram `initData` bilan, kod bilan emas.
3. Bosqich 10 — Finance: `debts`/`debt_reminders` to'liq registri
   qurilganda, `telegram_notifications` jurnali o'zgarishsiz qoladi
   (§5 #2).
