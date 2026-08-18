# BOSQICH 11 — LANDING SAYT

> Manba: `docs/PROJECT.md` §11 (Bosqich 11): "SEO, manzillar, narxlar,
> onlayn navbat." §2 (repo tuzilmasi — `landing/` Next.js, public sayt),
> §3 (repo daraxti).
> Sana: 2026-08-18.

## Hajm

**Kiradi:**
- `landing/` — Next.js 15 (App Router, SSR — SEO uchun) ilova, repo
  ildizida, `backend/`/`admin/` bilan bir qatorda (PROJECT.md §3).
- Sahifalar: Bosh sahifa, Filiallar (manzillar), Narxlar (xizmat
  narxlari), Onlayn navbat (band qilish formasi).
- Backendda uchta yangi **ochiq** (hisobsiz, `auth:sanctum` siz)
  endpoint: filiallar ro'yxati, xizmat narxlari ro'yxati, onlayn
  navbat so'rovini yozish.
- `sitemap.xml`, `robots.txt`, har sahifada `metadata` (SEO).

**Kirmaydi:**
- To'liq mahsulot katalogi (ko'zoynak assortimenti) — "narxlar"
  bu yerda **xizmat** narxlarini anglatadi (ko'z tekshiruvi, montaj),
  butun katalog emas: u juda katta, ombor holatiga bog'liq va doimiy
  yangilanib turadi — landing uchun mos emas.
- Onlayn navbat **real** `visits` jadvaliga to'g'ridan-to'g'ri
  yozilmaydi (pastda izohlangan) — bu keyingi, alohida qaror talab
  qiladigan ish.

## Onlayn navbat — nega alohida jadval

`visits.queue_number`/`queue_date` **bugungi jismoniy navbat** uchun
mo'ljallangan — sotuvchi/QR orqali kelgan mijoz shu zahoti navbat
raqami oladi. Landing saytdan kelgan so'rov esa **kelajakdagi** kun
uchun bo'lishi mumkin va hali tasdiqlanmagan (mijoz javob
bermasligi mumkin). Ikkalasini bitta jadvalga aralashtirish bugungi
jismoniy navbatni buzardi (masalan queue_number ketma-ketligi
buziladi yoki "bo'sh" ticketlar paydo bo'ladi).

Shuning uchun yangi `appointment_requests` jadvali — LostSale (Bosqich
10c) bilan bir xil naqsh: **oddiy, o'zgarmas hodisa jurnali**, xodim
keyin ko'rib chiqib real navbatga (yoki qo'ng'iroq bilan) o'tkazadi.
`status` maydoni orqali kuzatiladi: `new → contacted → booked|declined`.

## Migratsiya

| Fayl | Jadval |
|---|---|
| `0001_01_01_000540_create_appointment_requests_table.php` | `appointment_requests` |

## Yangi ruxsatlar

`clinic.appointment_request.view_any`, `clinic.appointment_request.manage`
— direktor, filial menejeri, shifokor (RolePermissionSeeder'da qo'shildi).

## API (yangi)

```
GET    /api/v1/public/branches        — ochiq, auth yo'q
GET    /api/v1/public/services        — ochiq, auth yo'q
POST   /api/v1/public/appointments    — ochiq, auth yo'q (throttle: 10/daq)

GET    /api/v1/appointment-requests               — xodim (auth:sanctum)
PUT    /api/v1/appointment-requests/{id}/status    — xodim (auth:sanctum)
```

Ochiq endpointlar `throttle` middleware bilan cheklangan — CAPTCHA
yo'q (infratuzilma yo'q), bu MVP uchun yetarli himoya.

## Testlar

| Fayl | Kafolat |
|---|---|
| `Feature/Clinic/PublicApiTest.php` | Auth talab qilinmaydi; faqat faol filial/xizmat chiqadi; navbat so'rovi hisobsiz yoziladi; ruxsatlar to'g'ri ishlaydi |

## Frontend — `landing/`

Next.js 15, App Router, SSR (server komponentlar backend API'ni
server tomonda chaqiradi — brauzer CORS'iga umuman tegmaydi). Band
qilish formasi Next.js Route Handler orqali backendga yuboriladi
(shu sabab ham CORS kerak emas).

Struktura:
```
landing/
├── app/
│   ├── layout.tsx            — umumiy metadata, shrift
│   ├── page.tsx               — bosh sahifa
│   ├── branches/page.tsx      — filiallar (manzillar)
│   ├── prices/page.tsx        — xizmat narxlari
│   ├── booking/page.tsx       — onlayn navbat formasi
│   ├── api/appointments/route.ts  — forma backendga proksi
│   ├── sitemap.ts
│   └── robots.ts
├── lib/api.ts                 — backend bilan server-side fetch
└── components/                — Header, Footer, BranchCard, ...
```

Ko'p tillilik (PROJECT.md §10, 4 til) — bu bosqichda **kirmaydi**:
landing MVP uz-latn tilida, i18n keyingi qadam sifatida qoldirildi
(admin panelda ham i18n hali to'liq emas — ANALIZ.md holatiga mos).
