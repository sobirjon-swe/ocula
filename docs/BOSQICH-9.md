# BOSQICH 9 — MIJOZ KABINETI (Customer)

> Manba: `docs/PROJECT.md` §11 (Bosqich 9), §6.9, §9 ("Telegram initData"),
> §10 ("Mijoz (Mini App)"); `docs/PERMISSIONS.md` §12; `docs/SCHEMA.md`
> (`customers`); `docs/ANALIZ.md` §16 (SMS OTP ochiq savoli).
> Sana: 2026-08-17. Holat: **bajarilgan va yashil**.
>
> Qolip `BOSQICH-3…8` bilan bir xil.

---

## 1. Hajm

**Kiradi:** `customer` guard (Sanctum token, `customers` provider),
Telegram Mini App `initData` bilan kirish (**ro'yxatdan o'tish
formasisiz** — §10), mijozning o'z profili (ko'rish/yangilash), o'z
buyurtmalari, o'z retseptlari, o'z qarzi, yetkazishni tasdiqlash
("Qabul qildim"/"Yo'q" — Bosqich 8'dagi `ConfirmStopDelivery`/
`DisputeStopDelivery` ni qayta ishlatadi).

**Kirmaydi:** telefon+SMS OTP zaxira kanali (`ANALIZ.md` §16 — hali
ochiq savol, vendor tanlanmagan), katalog ko'rish va baholash (§11:
"keyin" — bu bosqichdan tashqarida), itemlashtirilgan chek ko'rinishi
(buyurtma darajasidagi summalar yetarli, satr-satr breakdown keyingi
ish), mijozlarni birlashtirish vositasi (quyida §5 #2 dagi ochiq
muammo).

---

## 2. Migratsiya

| Fayl | O'zgarish |
|---|---|
| `0001_01_01_000450_make_customers_phone_nullable.php` | `customers.phone` endi **nullable** |

`phone` — SCHEMA.md bo'yicha "yagona ishonchli identifikator"
(`CustomerRequest` docblock), lekin bu **xodim kiritgan** mijoz uchun
to'g'ri. Telegram orqali birinchi marta kirgan mijozda telefon **yo'q**
— faqat `telegram_id` bor. `customer.profile.update` ruxsati ("Telefon,
til o'zgartirish") aynan shuni ko'zda tutgan: mijoz keyinroq o'zi
kiritadi. Xodim tomonidan yaratishda `phone` hamon **majburiy**
(`CustomerRequest` o'zgarmadi) — bu faqat DB darajasidagi bo'shliq.

---

## 3. Autentifikatsiya — Telegram `initData`

```
Mini App ochiladi (Telegram.WebApp.initData bilan)
  → POST /api/v1/customer/auth/telegram { init_data }
  → InitDataValidator: HMAC-SHA256 imzoni tekshiradi (Telegram rasmiy
    algoritmi), auth_date muddatini tekshiradi
  → topilmasa/yaroqsiz bo'lsa → 401
  → customers.telegram_id bo'yicha find-or-create
  → Sanctum token (`customer` guard, `personal_access_tokens`)
  → { token, customer } qaytadi
```

Client keyingi so'rovlarda `Authorization: Bearer <token>` yuboradi.
**Cookie/CSRF emas** — `bootstrap/app.php` dagi eski izoh ("mijoz
kabineti cookie orqali") shu bosqichda **noto'g'ri chiqdi**: Mini App
Telegram webview ichida ishlaydi, boshqa origin'dan — cookie/CSRF
murakkablik qo'shardi, foyda bermasdan. Shuning uchun admin kabi cookie
emas, **mobil kabi Bearer token** (izoh yangilandi, §5 #3).

---

## 4. Yangi ilgak — bir guard, bitta rol

`customer` guard uchun **rollar matritsasi yo'q**: har bir mijoz bir
xil olti ruxsatga ega (`PERMISSIONS.md` §12). Shuning uchun
`CustomerPermissionSeeder` (yangi, `RolePermissionSeeder`ning o'zi
emas — u buni ataylab bo'sh qoldirgan edi, docblockida "ular `customers`
jadvali bilan birga Customer modulida keladi" deb yozilgan) bitta
`customer` rolini yaratadi va olti ruxsatni beradi. `AuthenticateViaTelegram`
yangi mijoz yaratganda shu rolni avtomatik biriktiradi.

---

## 5. Rejadan/hujjatdan farq qilgan qarorlar

1. **Policy klasslari ishlatilmadi, to'g'ridan-to'g'ri tekshiruv.**
   `ResourcePolicy`/`ApiController` qat'iy `App\Modules\Core\Models\User`
   ga bog'langan (staff). Ikkinchi guard uchun ularni ikki marta
   yozishning ma'nosi yo'q edi: har controllerda bitta savol —
   "bu yozuv shu mijoznikimi?" Shu sabab har amalda to'g'ridan-to'g'ri
   `$order->customer_id === $customer->id` tekshiruvi (yoki resurs
   umuman `where('customer_id', $customer->id)` bilan so'raladi —
   begona yozuv 404, 403 emas: mavjudligini ham bildirmaslik kerak).

2. **Telegram orqali birinchi marta kirgan mijoz va xodim yaratgan
   mijoz mos kelmasa — ikkita alohida yozuv bo'lib qoladi.** Agar
   mijoz avval do'konda telefon bilan ro'yxatdan o'tgan bo'lsa-yu,
   `telegram_id` biriktirilmagan bo'lsa (Bosqich 7'dagi `/start <kod>`
   orqali), Mini App uni **taniy olmaydi** — yangi, bo'sh mijoz
   yozuvi ochiladi. Telegram `initData` telefon raqamini bermaydi,
   shuning uchun avtomatik moslashtirish xavfli (noto'g'ri odamga
   ulanib qolish xatari). **Bu qarz** — "mijozlarni birlashtirish"
   vositasi (7.13 dagi tovar birlashtirish kabi) keyingi bosqich ishi.

3. **`bootstrap/app.php` dagi eski izoh yangilandi.** U cookie
   sessiyani mo'ljallagan edi; haqiqiy amalga oshirish Bearer token
   (§3).

4. **`customer.can()` ishlaydi — `Authorizable` qo'shildi.** `Customer`
   modeli avvalgidan (`Illuminate\Database\Eloquent\Model`) ustiga
   `Illuminate\Auth\Authenticatable`, `Illuminate\Foundation\Auth\Access\Authorizable`,
   `Laravel\Sanctum\HasApiTokens`, `Spatie\Permission\Traits\HasRoles`
   qo'shildi. Ruxsatlar bir xil bo'lsa ham (§4), controller'lar
   baribir `$customer->can('customer.order.view_own')` deb tekshiradi
   — kelajakda mijozni faollikdan chetlatish kerak bo'lsa (masalan,
   firibgarlik gumoni), rolni olib tashlash yetarli bo'ladi.

---

## 6. API

```
POST   /customer/auth/telegram         — kirish (ochiq)

GET    /customer/profile               PUT /customer/profile
GET    /customer/orders                GET /customer/orders/{id}
GET    /customer/prescriptions
GET    /customer/debt

POST   /customer/trip-stops/{id}/confirm   — "Qabul qildim"
POST   /customer/trip-stops/{id}/dispute   — "Yo'q"
```

Hammasi (auth yo'lidan tashqari) `auth:customer` ostida.

---

## 7. Testlar

| Fayl | Kafolat |
|---|---|
| `Feature/Customer/TelegramAuthTest.php` | To'g'ri imzo bilan kirish token beradi va yangi mijoz yaratadi; noto'g'ri imzo 401; ikkinchi kirishda **yangi mijoz yaratilmaydi** |
| `Feature/Customer/ProfileTest.php` | O'z profilini ko'radi va telefon/tilni yangilaydi |
| `Feature/Customer/OrderApiTest.php` | Faqat o'z buyurtmalarini ko'radi, begonasini 404 |
| `Feature/Customer/PrescriptionApiTest.php` | Faqat o'z retseptlarini ko'radi, barcha filiallardan |
| `Feature/Customer/DeliveryConfirmationTest.php` | O'z to'xtashini tasdiqlaydi/rad etadi; begonasini qila olmaydi |

---

## 8. Frontend — `customer/` (Telegram Mini App)

> Sana: 2026-08-18. Backend §1-7 dan keyin, alohida bosqich sifatida
> emas — Bosqich 12 (React Native) PROJECT.md'da "faqat PWA yetmay
> qolganda" deb shartlangani uchun, avval shu PWA qurildi.

Stack `admin/`ning aynan o'zi (React 19 + Vite 6 + TS strict + Zustand
+ TanStack Query + Tailwind v4), lekin mobile-first: sidebar o'rniga
pastki tab-bar (`AppLayout.tsx`), 44px+ teginish nishonlari, `safe-area-
inset-bottom`. Port `5174` (admin — `5173`).

**Kirish:** rasmiy `telegram-web-app.js` skript tegi orqali (npm SDK
yo'q — ortiqcha bog'liqlik). `Telegram.WebApp.initData` xom holda
`POST /customer/auth/telegram` ga yuboriladi — client HMAC'ni o'zi
tekshirmaydi, faqat backend (`InitDataValidator`, §3).

**"Bir ekranda" tartib (PROJECT.md §10):** `HomePage.tsx` — faol
buyurtma → retseptlarim → qarzim → tarix, alohida tablar emas, bitta
scroll'da `<Section>` bloklari.

**Yetkazishni tasdiqlash — chuqur havola:** mijozga "pending
tasdiqlashlar" ro'yxatini beradigan endpoint yo'q (§1 "kirmaydi"),
shuning uchun bot `t.me/bot/app?startapp=stop_123` havolasi bilan
ochiladi → `Telegram.WebApp.initDataUnsafe.start_param` → ilova ochilishi
bilan `/delivery/123` ga yo'naltiradi (`routes.tsx`,
`useStartParamRedirect`), pastki navigatsiyadan tashqarida.

**Retsept — "saqlab olsa bo'ladigan karta":** gradient karta +
`navigator.share()` (Web Share API) — rasm eksport kutubxonasi
qo'shilmadi, brauzer o'zi qila oladigan ish uchun.

**i18n:** `admin/`ning haqiqiy konvensiyasi qaytarildi — hujjatlashtirilgan
`react-i18next` (PROJECT.md §10) emas, hand-rolled `Record<Locale,
Record<MessageKey,string>>` + `useT()`. `uz-cyrl` mijoz UI matnlari
uchun alohida tarjima qilinmaydi — `sourceLocale()` uni `uz-latn`ga
tenglashtiradi (admin bilan bir xil soddalashtirish; ma'lumot darajasida
`uz-cyrl` baribir backend'dagi `Transliterator` orqali keladi).

**Standalone login yo'q:** telefon+SMS OTP kanali hali yo'q (§1
"kirmaydi", `ANALIZ.md` §16) — Mini App'dan tashqarida ochilsa,
`auth.outsideTelegram` xabari ko'rsatiladi.

---

## 9. Bosqich 9 dan keyin

1. Bosqich 10 — Moliya: to'liq qarz registri, `debt_balance` keshi
   endi haqiqiy manbaga ega bo'ladi.
2. Mijozlarni birlashtirish vositasi (§5 #2 dagi qarz).
3. Telefon + SMS OTP — agar direktor talab qilsa (`ANALIZ.md` §16).
4. Bosqich 12 (React Native) — endi PWA (§8) mavjud; kerakligini
   qayta ko'rib chiqish kerak (PROJECT.md: "faqat kerak bo'lsa").
