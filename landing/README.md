# OPTIKA — Landing

Public sayt (SEO, manzillar, narxlar, onlayn navbat) — Next.js 16,
App Router, SSR. To'liq reja: `../docs/BOSQICH-11.md`.

## Ishga tushirish

```bash
cp .env.example .env.local   # BACKEND_API_URL ni to'g'rilang
npm install
npm run dev
```

Backend (`../backend`) ishga tushirilgan bo'lishi kerak
(`php artisan serve`), aks holda sahifalar bo'sh ro'yxat qaytaradi.

## Nega SSR

Barcha ma'lumot (filiallar, narxlar) **server tomonda** olinadi —
brauzer hech qachon backendga to'g'ridan-to'g'ri so'rov yubormaydi,
shuning uchun CORS sozlash shart emas. Onlayn navbat formasi ham
o'zining Route Handler'i (`app/api/appointments`) orqali backendga
uzatiladi — xuddi shu sabab bilan.

## Buyruqlar

| Buyruq | Vazifasi |
|---|---|
| `npm run dev` | Dev server, Turbopack |
| `npm run build` | Production build |
| `npm run lint` | ESLint |

## Struktura

```
app/
├── page.tsx              — bosh sahifa
├── branches/page.tsx      — filiallar (manzillar)
├── prices/page.tsx        — xizmat narxlari
├── booking/page.tsx       — onlayn navbat formasi
├── api/appointments/route.ts  — forma → backend proksi
├── sitemap.ts
└── robots.ts
lib/
├── api.ts     — backend bilan server-side fetch
├── types.ts   — backend Resource shakllariga mos tiplar
└── site.ts    — brend nomi, tavsif, URL
```
