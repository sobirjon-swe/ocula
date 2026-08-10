# OPTIKA — Ko'zoynak do'konlari tarmog'ini avtomatlashtirish

> Bu hujjat loyihaning yagona haqiqat manbai (single source of truth).
> Har bir yangi sessiyada avval shu faylni o'qing, keyin kod yozing.
> Hujjatga zid qaror qabul qilish kerak bo'lsa — avval so'rang.

---

## 1. LOYIHA HAQIDA

### 1.1 Biznes

5 ta filialdan iborat optika (ko'zoynak) do'konlari tarmog'i:

- **A filial (MAIN)** — asosiy filial. Bir vaqtning o'zida:
  - savdo qiladi,
  - markaziy ombor vazifasini bajaradi,
  - boshqa filiallarga tovar tarqatadi,
  - shifokor va usta shu yerda o'tiradi,
  - direktor shu yerda o'tiradi.
- **B, C, D, E filiallari** — faqat savdo nuqtalari.

Ish vaqti: A — 09:00–22:00, qolganlari — 10:00–22:00.

### 1.2 Hozirgi holat (avtomatlashtirilmagan)

Hamma narsa qog'ozda: shifokorga yo'llanma, retsept, ustaga varaqa, haydovchiga
manzillar ro'yxati, kunlik savdo hisoboti, ustaning brak daftari, mijoz qarzlari.
Direktorga hisobot qo'lda ko'chirib beriladi.

**Asosiy muammolar:**
1. Filiallararo tovar harakati hujjatsiz — yo'qolgan tovar aybdori topilmaydi.
2. Haydovchidagi pul va tovar hisobga olinmaydi.
3. Qarzlarning ~10% umuman unutiladi (sof zarar).
4. Retseptlar qog'ozda — takroriy mijoz yana ko'rikka yuboriladi.
5. Usta brakni qog'ozga yozadi — vaqt yo'qotadi, statistika yo'q.
6. "Tovar yo'q" sababli yo'qotilgan savdo hech qayerda qayd etilmaydi.
7. Direktor faqat kassani ko'radi, sof foydani ko'rmaydi.

---

## 2. TEXNOLOGIYA STEKI

### Backend
- **Laravel 13** (PHP 8.3+)
- **PostgreSQL** (yoki MySQL 8) — asosiy baza
- **Redis** — cache, queue, session
- **Laravel Horizon** — queue monitoring
- **Laravel Sanctum** — SPA va mobil autentifikatsiya
- **spatie/laravel-permission** — rollar va ruxsatlar
- **spatie/laravel-activitylog** — audit log
- **spatie/laravel-query-builder** — filtr/sort/include
- **maatwebsite/excel** — hisobotlarni eksport qilish

### Frontend
- **Admin SPA**: React 18 + Vite + TypeScript + TanStack Query + Zustand +
  Tailwind CSS + shadcn/ui + React Router
- **Landing**: Next.js (SSR, SEO uchun)
- **Mijoz kabineti**: React PWA (Telegram Mini App sifatida ham ishlaydi)
- **Mobil**: React Native (Expo) — **faqat 3-bosqichda**, avval PWA

### Integratsiyalar
- **Telegram Bot API** — bildirishnomalar, Mini App
- **Yandex Maps API** — manzil tanlash, navigator
- **Eskiz.uz / Play Mobile** — SMS (zaxira kanal)
- **Payme / Click** — onlayn to'lov (keyingi bosqich)

### Muhim texnik qarorlar
- Backend faqat **API** beradi (Blade shablonlar ishlatilmaydi, admin panel SPA).
- **Modulli monolit** — bitta repo, bitta deploy, ichida aniq modullar.
- Pul **butun son** (tiyinda) yoki `decimal(15,2)`. **Hech qachon float emas.**
- Barcha vaqtlar bazada **UTC**, frontendda `Asia/Tashkent`.

---

## 3. REPOZITORIYA TUZILMASI

```
optika/
├── backend/                 # Laravel 13
│   ├── app/
│   │   ├── Modules/
│   │   │   ├── Core/        # filiallar, xodimlar, rollar, smenalar
│   │   │   ├── Catalog/     # tovarlar, linzalar, brendlar, xizmatlar
│   │   │   ├── Warehouse/   # kirim, transfer, inventarizatsiya, brak
│   │   │   ├── Sales/       # chek, buyurtma, to'lov, qaytarish
│   │   │   ├── Clinic/      # vizit, navbat, retsept
│   │   │   ├── Workshop/    # ustaxona buyurtmalari
│   │   │   ├── Delivery/    # yo'l varaqasi, haydovchi
│   │   │   ├── Finance/     # kassa, qarz, xarajat, inkassatsiya
│   │   │   ├── Customer/    # mijoz kabineti API
│   │   │   └── Analytics/   # hisobotlar
│   │   └── Support/         # umumiy helper, trait, enum
│   └── ...
├── admin/                   # React SPA (xodimlar uchun)
├── customer/                # React PWA (mijoz + Telegram Mini App)
├── landing/                 # Next.js (public sayt)
├── mobile/                  # React Native (3-bosqichda)
└── docs/
    └── PROJECT.md           # shu fayl
```

Har bir modul ichida: `Models/`, `Http/Controllers/`, `Http/Requests/`,
`Http/Resources/`, `Services/`, `Actions/`, `Events/`, `Listeners/`, `Policies/`.

---

## 4. ROLLAR

| Rol | Qurilma | Nima qiladi |
|---|---|---|
| `director` | Telefon + kompyuter | Hammasini ko'radi, chegirma/qarz limitini tasdiqlaydi |
| `branch_manager` | Kompyuter | Bitta filialni boshqaradi |
| `seller` | Kompyuter | Kassa, mijoz, buyurtma, tovar qidirish |
| `doctor` | Kompyuter/planshet | Vizit, retsept |
| `master` | Planshet | Ustaxona buyurtmalari, brak, o'z zaxirasi |
| `driver` | Telefon | Yo'l varaqasi, yetkazish, pul yig'ish |
| `warehouse` | Kompyuter | Kirim, transfer tayyorlash, inventarizatsiya |
| `accountant` | Kompyuter | Moliya, hisobotlar, yetkazib beruvchilar |
| `customer` | Telefon | O'z kabineti (alohida guard) |

### Muhim qoidalar
- **Har bir so'rov filialga qarab avtomatik cheklanadi** (global scope
  `BranchScope`). `director` va `accountant` — barcha filiallarni ko'radi.
- Rollar `spatie/laravel-permission` orqali, ruxsatlar (`permission`) nomi:
  `module.action` formatida — masalan `sales.discount.apply`,
  `finance.debt.approve`.
- Mijoz **alohida guard** (`customer`), xodimlar bilan bir jadvalda emas.

---

## 5. BIZNES JARAYONLAR

### 5.1 Mijoz arxetiplari

**Arxetip 1 — Tez savdo**
Tanladi → to'ladi → ketdi. Chek darrov yopiladi. Ombordan darrov chiqim.
Misol: quyoshdan saqlovchi ko'zoynak, salfetka, tayyor linza.

**Arxetip 2 — Buyurtma (retsept bilan)**
Ko'rik → retsept → linza tanlash → ramka tanlash → usta → 1–2 kun → topshirish.
**Buyurtma va sotuv bir narsa emas!** Buyurtma bugun ochiladi, daromad
tovar topshirilganda tan olinadi.

**Arxetip 3 — Buyurtma + tovar tanqisligi**
Arxetip 2 ustiga: kerakli linza/ramka boshqa filialda → ichki so'rov →
transfer → keyin ustaxona.

### 5.2 Kunlik oqim

```
09:00  A filial smenani ochadi (kassa qoldig'i kiritiladi)
10:00  B, C, D, E smenani ochadi
       ↓
       Savdo, buyurtmalar, ko'riklar, ustaxona ishi
       ↓
       Tovar kelishi → A ombori → filiallarga transfer (haydovchi)
       Haydovchi mijozlarga ham yetkazadi va naqd pul yig'adi
       ↓
22:00  Har bir filial smenani yopadi:
       - tizim hisoblagan summa ko'rsatiladi
       - sotuvchi seyfdagi haqiqiy naqdni kiritadi
       - farq (kamomad/ortiqcha) qayd etiladi
       ↓
       Direktorga 5 filialning yig'ma hisoboti Telegramga avtomatik ketadi
```

**Haydovchi pul aylanmasi:** mijozdan naqd oldi → pul **haydovchi balansida**
(kassaga tushmagan!) → ertasi kuni A ga tovar olgani kelganda topshiradi →
**inkassatsiya hujjati** → endi kassada.

---

## 6. MODULLAR VA FUNKSIONAL

### 6.1 Core
- Filiallar (`branches`): turi `main` / `shop`, ish vaqti, manzil, koordinata
- Xodimlar, rollar, ruxsatlar
- Smenalar (`shifts`): ochilish/yopilish, kassir, boshlang'ich va yakuniy qoldiq
- Audit log (kim, qachon, nimani o'zgartirdi)

### 6.2 Catalog
- **Tovar turlari**: `frame` (ramka), `lens` (linza), `accessory` (aksessuar),
  `ready_glasses` (tayyor ko'zoynak), `service` (xizmat)
- Brendlar, kategoriyalar, birliklar
- **Linza modellari** va ularning parametrlari (pastda 7.2 ga qarang)
- Xizmatlar: ko'rik, linza o'rnatish, ta'mir, sozlash
- Shtrix-kodlar (bitta tovarga bir nechta bo'lishi mumkin)

### 6.3 Warehouse
- **Kirim** (`purchase`) — yetkazib beruvchidan, tannarx bilan
- **Transfer** — 2 bosqichli: `jo'natildi` → `yo'lda` → `qabul qilindi`
- **Inventarizatsiya** — sanash, farqni hisobdan chiqarish
- **Brak** — sabab bilan (7.5 ga qarang)
- **Ichki so'rov** — filial A dan tovar so'raydi
- **Qoldiq** — harakatlar daftaridan hisoblanadi (7.1)
- **Ustaning zaxirasi** — alohida "joy" (location) sifatida

### 6.4 Sales
- **Chek** (tez savdo) — ochish, tovar qo'shish, chegirma, to'lov, yopish
- **Buyurtma** — holatlar zanjiri bilan (7.3)
- **To'lovlar** — bir buyurtmaga bir nechta to'lov (avans, qoldiq)
- **To'lov turlari**: naqd, karta, Payme, Click, o'tkazma, qarz
- **Qaytarish** — tovar va pul qaytarish
- **Chegirma** — foizda yoki summada, limitdan oshsa direktor tasdig'i

### 6.5 Clinic
- **Vizit** — QR yoki sotuvchi orqali ochiladi, navbat raqami
- **Navbat** — shifokor ekranida jonli ro'yxat
- **Retsept** — OD/OS, SPH, CYL, AXIS, ADD, PD, prizma, tavsiya
- Retsept saqlanganda → **sotuvchida avtomatik tiket ochiladi**
- Retsept tarixi mijoz kartasida saqlanadi

### 6.6 Workshop
- Ustaxona buyurtmalari (kanban: Yangi → Ishda → Tayyor)
- Usta zaxirasidagi linzalar
- **Brak** — sabab tanlanadi, tizim qolganini o'zi qiladi
- Muddat, bajaruvchi usta, qayta ishlash

### 6.7 Delivery
- **Yo'l varaqasi** (`trip`) — bir nechta manzil, tartib bilan
- Manzil turi: filial yoki mijoz
- **Haydovchi balansi**: qo'lidagi tovar + qo'lidagi pul
- Yetkazish tasdig'i (7.4)
- **Inkassatsiya** — haydovchidan kassaga pul topshirish
- **Taksi bilan yetkazish** (Yandex Taxi / Uklon) — B↔C kabi yo'nalishlar uchun
- Yandex Maps: navigatorda ochish + manzilni xaritadan tanlash

### 6.8 Finance
- Kassa harakatlari (kirim/chiqim)
- Xarajatlar (ijara, oylik, kommunal, transport) — kategoriyalar bilan
- **Qarzlar** — mijoz balansi, muddat, eslatma, muddat bo'yicha guruhlash
- Yetkazib beruvchilar bilan hisob-kitob
- Smena yopilishi va kamomad

### 6.9 Customer (mijoz kabineti)
- Telegram Mini App + PWA (bir xil kod)
- Retseptlarim, buyurtma holati, xaridlar tarixi, qarzim
- Yetkazishni tasdiqlash
- Baholash (yulduz + izoh)
- Keyingi ko'rik eslatmasi

### 6.10 Payroll (mukofot va rejalar)
- Mukofot qoidalari: sotuv summasidan % / foydadan % / tovar turiga qarab
- **Shaxsiy foiz** — direktor har bir xodimga alohida biriktiradi (7.19)
- **Filial oylik rejalari** (7.18) — reyting va bonus uchun
- Hisoblash davri: oylik
- Sotuvchi, shifokor, usta uchun alohida qoidalar
- Qoida: mukofot **topshirilgan VA to'liq to'langan** buyurtmadan (7.12)

### 6.11 Analytics
- ABC tahlil (tovarlarning 20% i daromadning 80% ini beradi)
- O'lik zaxira (90+ kun sotilmagan)
- Aylanish tezligi
- **Yo'qotilgan savdo** — "tovar yo'q" holatlari (juda muhim!)
- Filial, sotuvchi, shifokor, usta bo'yicha ko'rsatkichlar
- Filiallar reytingi (reja bajarilishi bo'yicha, mutlaq summa bo'yicha emas)

---

## 7. MUHIM BIZNES QOIDALAR

> Bu bo'lim eng muhimi. Bu yerdagi qoidalarni buzsangiz, tizim noto'g'ri ishlaydi.

### 7.1 Ombor qoldig'i — harakatlar daftari

Qoldiq **hech qachon** jadvalda oddiy raqam bo'lib turmaydi.

Har bir harakat `stock_movements` jadvaliga yoziladi:
```
id, product_variant_id, location_id, quantity (+/-),
type (purchase|sale|transfer_out|transfer_in|adjustment|defect|return),
source_type, source_id (polymorphic — qaysi hujjatdan kelib chiqqan),
cost_price, created_by, created_at
```

Qoldiq = shu yozuvlar yig'indisi.

Tezlik uchun `stock_balances` jadvali keshda saqlanadi (location_id +
variant_id + quantity), lekin u **faqat hosila** — har doim
`stock_movements` dan qayta hisoblab olish mumkin bo'lishi kerak.

**`location` tushunchasi** filialdan kengroq:
- filial ombori
- filial savdo zali
- **yo'lda** (haydovchi zimmasida)
- **usta zaxirasi**

### 7.2 Linza parametrlari

Har bir diopter kombinatsiyasini alohida tovar qilmang (20 000+ yozuv chiqadi).

**To'g'ri model:**
- `products` — linza **modeli** (masalan "Hoya 1.61 HMC")
- `product_variants` — konkret kombinatsiya (SPH −2.25, CYL −0.75)
- Ombordagi faqat **haqiqatan turadigan** kombinatsiyalar variant sifatida
  yaratiladi (lazy creation — birinchi kirimda paydo bo'ladi)
- **Individual buyurtma linzalari** umuman ombordan chiqmaydi — ular
  to'g'ridan-to'g'ri buyurtmaga bog'lanadi

Variant atributlari: `sph`, `cyl`, `axis`, `add`, `index` (1.56/1.61/1.67/1.74),
`coating` (HMC/SHMC/blue/photochromic), `diameter`.

### 7.3 Buyurtma holatlari

**Ikkita alohida o'q bor, ularni aralashtirmang:**

**Bajarilish holati (`status`):**
```
new → awaiting_exam → prescription_ready
    → materials_reserved ─┬─ (bor) ────────────→ in_workshop
                          └─ (yo'q) → awaiting_transfer → in_workshop
    → ready → customer_notified → delivered → closed
```
Yon tarmoqlar: `cancelled`, `returned`, `rework` (usta xato qildi yoki
mijozga to'g'ri kelmadi — optikada tez-tez bo'ladi).

**To'lov holati (`payment_status`):**
```
unpaid → partial → paid
       └→ debt (muddat o'tgan)
```

Buyurtma `ready` bo'lishi mumkin, lekin to'lovi hali `partial` — bu normal.

### 7.4 Yetkazish tasdig'i — asimmetrik

| Kim | Roli | Majburiymi |
|---|---|---|
| Haydovchi | "Yetkazdim" + GPS + rasm | **Ha** |
| **Filial xodimi** | Haqiqiy miqdorni kiritadi | **Ha** |
| **Mijoz** | "Qabul qildim" | Yo'q, lekin kutiladi |

Oqim:
```
Haydovchi "Yetkazdim" bosadi
  → GPS koordinatasi olinadi (faqat shu paytda, kun bo'yi kuzatuv YO'Q)
  → ixtiyoriy: tovar rasmi
  → mijozga Telegram: "Buyurtmangiz yetkazildimi?" [Ha] [Yo'q]
  → adminga jonli lentaga tushadi

Mijoz "Ha"        → confirmed
Mijoz "Yo'q"      → adminga qizil signal, tekshiruv
Javob yo'q 24 soat → avto-yopiladi, lekin "unconfirmed" belgisi bilan
```

**Filialga transferda tasdiq majburiy** va xodim **haqiqiy miqdorni** kiritadi.
Jo'natilgandan kam kiritishi mumkin → `discrepancy` hujjati + direktorga signal.

GPS mijoz manzilidan 500 m dan uzoq bo'lsa — belgilanadi (ayblash uchun emas,
hujjat uchun).

### 7.5 Brak sabablari

Usta faqat sababni tanlaydi, qolganini tizim qiladi:

| Sabab | Kim to'laydi | Tizimda |
|---|---|---|
| `supplier_defect` | Yetkazib beruvchi | Qaytarish akti tayyorlanadi |
| `transport_damage` | Haydovchi/yetkazuvchi | Yo'l varaqasiga bog'lanadi |
| `master_error` | Do'kon zarari | Usta statistikasiga yoziladi |
| `customer_request` | Mijoz yoki do'kon | Buyurtma qayta ochiladi (`rework`) |

### 7.6 Qarz

- Har bir mijoz kartasida **qarz qoldig'i va muddati** ko'rinadi
- Sotuvchi mijozni tanlaganda darrov qizil ogohlantirish chiqadi
- **Qarz limiti**: `seller` — belgilangan summagacha, undan yuqorisi
  `director` tasdig'i bilan
- Avtomatik eslatma: muddatdan 1 kun oldin, muddat kuni, +3, +7 kun
- Hisobot **muddat bo'yicha guruhlangan**: 0–7, 8–30, 31–90, 90+ kun
- **90+ kun = shubhali qarz**, foyda hisobotidan chiqariladi

### 7.7 Avans — MAJBURIY EMAS, lekin o'lchanadi

**Biznes qarori: avans talab qilinmaydi.** `min_prepayment_percent = 0`
(sozlamalarda, direktor xohlagan payt o'zgartiradi).

Buning o'rniga tizim **zararni o'lchaydi**:
- `orders.abandoned_at` — mijoz olib ketmagan buyurtmalar
- Oylik hisobot: "N ta buyurtma olib ketilmadi, yo'qotilgan tannarx: X so'm"
  (ayniqsa individual linzalar — ular hech kimga sotilmaydi)
- Mijoz kartasida: `abandoned_orders_count`
- Sotuvchi ekranida ogohlantirish: "Bu mijoz 2 marta buyurtmani olmagan"

Direktor bu raqamni bir necha oy ko'rgach, avans foizini o'zi qo'yishi mumkin.
Mexanizm tayyor turadi.

### 7.8 Ikki xil hisobot — ular hech qachon teng bo'lmaydi

**A — Kassa (pul harakati)**: bugun qancha pul kirdi/chiqdi, seyfda qancha.
Buyurtma avansi ham, kechagi qarz to'lovi ham — hammasi bugungi pul.
*Direktor har kuni shuni ko'radi.*

**B — Savdo va foyda**: bugun qancha tovar topshirildi, tannarxi qancha,
sof foyda qancha. Kechagi buyurtma bugun topshirilsa — daromad **bugunga**.

Bosh ekranda majburiy qator:
**"Bajarilmagan buyurtmalar majburiyati: X so'm"**
(bu pul kassada turibdi, lekin u sizniki emas — majburiyat).

### 7.9 Yo'qotilgan savdo

Har safar sotuvchi tovar qidirib topolmaganda yoki qoldiq 0 bo'lganda —
`lost_sales` jadvaliga yoziladi (tovar, filial, sana, mijoz).
Oy oxirida: qaysi tovarlar doim yetishmaydi → ularni ko'proq olish kerak.
**Bu deyarli hech bir tizimda yo'q va juda qimmatli.**

### 7.10 Transfer yetkazish usullari

`transfers.delivery_method`:

| Usul | Qachon | "Yo'lda" qoldiq egasi | Xarajat |
|---|---|---|---|
| `own_driver` | A ↔ boshqa filiallar | Haydovchi (`driver_id`) | Yo'q |
| `taxi` | B ↔ C kabi, haydovchi uzoqda | **Transferning o'zi** | Ha, majburiy |
| `by_hand` | Xodim o'zi olib bordi | O'sha xodim | Ixtiyoriy |

**Taksi tanlanganda:**
- Xarajat summasi majburiy kiritiladi, chek rasmi so'raladi
- `expenses` yozuvi **avtomatik shu transferga bog'lanadi**
  (`source_type = Transfer`, kategoriya = `transport`)
- Hozir sotuvchilar taksi pulini bog'lanmagan xarajat qilib yozishadi —
  natijada "qaysi yo'nalishga qancha ketdi" ma'lum emas
- Hisobot: yo'nalish bo'yicha oylik transport xarajati
  (B↔C ga ko'p ketsa → C ga ko'proq zaxira berish arzonroq bo'lishi mumkin)

### 7.11 Retsept qamrovi — FILIAL BO'YICHA CHEKLANGAN

**Kritik qoida:** shifokor retsept saqlaganda ochiladigan **faol tiket
faqat vizit bo'lgan filial sotuvchilarida** ko'rinadi.

Sabab: A filialdagi shifokorning retsepti B filialga tushsa, B da
**keraksiz ko'zoynak yasalib qoladi** — sof brak va zarar.

| Ob'ekt | Kim ko'radi |
|---|---|
| **Faol tiket** (ish hujjati) | Faqat `visits.branch_id` filiali |
| **Retsept tarixi** (mijoz kartasida) | Barcha filiallar (o'qish uchun) |

- Mijoz boshqa filialga kelsa, sotuvchi eski retseptni **ko'radi**, lekin
  tiket **avtomatik ochilmaydi** — sotuvchi ataylab
  "Shu retsept bo'yicha buyurtma" tugmasini bosadi
- `prescriptions.valid_until` — amal qilish muddati (default 12 oy)
- Muddati o'tgan retsept bo'yicha buyurtma → ogohlantirish:
  "Retsept 14 oy oldingi, qayta ko'rik tavsiya etiladi"
- Tiketni boshqa filialga o'tkazish faqat sabab bilan va logga yozilgan holda

### 7.12 Mukofot hisoblash

**Mukofot buyurtma ochilganda EMAS, quyidagi ikkala shart bajarilganda:**
1. Buyurtma `delivered` holatida
2. To'lov `paid` holatida (qarz qolmagan)

Aks holda sotuvchi soxta buyurtma ochib foiz oladi.

- Qaytarish bo'lsa — mukofotdan ayiriladi
- Chegirma berilgan bo'lsa, mukofot **chegirmadan keyingi** summadan
- Foydadan foiz hisoblash tavsiya etiladi (sotuv summasidan emas) —
  bu keraksiz chegirma tarqatishga undamaydi
- Usta uchun: bajarilgan buyurtma soni − brak (`master_error`) jarimasi

### 7.13 Katalog "yurib" to'ldiriladi (inline quick-create)

**Boshlang'ich holat:** katalog bo'sh. Hech kim 2000 ta tovarni oldindan
kiritmaydi (bu loyihani boshidayoq o'ldiradi).

**Lekin erkin matn (free text) ham ISHLATILMAYDI.** Sabab: erkin matnda
"Ray Ban 3025", "рэйбан 3025", "RB-3025" — uchta har xil tovar bo'lib
qoladi va quyidagilar **butunlay ishlamaydi**: ombor qoldig'i, transfer,
inventarizatsiya, ABC tahlil, o'lik zaxira, yo'qotilgan savdo.

**To'g'ri yechim — sotuv paytida katalogga qo'shish:**

```
Sotuvchi nomni yozadi
  → topilsa: tanlaydi (tez)
  → topilmasa: kichik modal chiqadi
       Nomi:    [Ray Ban 3025 qora]
       Turi:    [Ramka ▾]
       Narx:    [____]   Tannarx: [____]
       [Qo'shish]  ← 5-7 soniya
  → tovar katalogda paydo bo'ladi, kirim yoziladi, chekka tushadi
```

Bir oydan keyin eng ko'p sotiladigan tovarlar allaqachon bazada bo'ladi
va sotuvchi ularni faqat tanlaydi — avvalgidan tezroq.

**Dublikatlarga qarshi:**
- Yozayotganda o'xshash nomlar taklif qilinadi (fuzzy search, trigram)
- Lotin/kirill farqi hisobga olinadi (transliteratsiya bilan qidiruv)
- Adminda **"Tovarlarni birlashtirish"** vositasi — ikkita dublikat
  bittaga qo'shiladi, `stock_movements` va tarix saqlanadi
- Haftalik hisobot: "Ehtimoliy dublikatlar: 7 ta"

**Bu barcha tovar turlariga tegishli:** ramka, linza, lupa, futlyar,
quyoshdan saqlovchi ko'zoynak, aksessuarlar.

**Yagona istisno — individual buyurtma linzalari.** Ular:
- ombordan chiqmaydi,
- katalogda turishi shart emas,
- to'g'ridan-to'g'ri buyurtmaga yoziladi (retsept parametrlari bilan).

`quick_created` bayrog'i qo'yiladi — admin keyin ularni tozalab, to'ldirib
chiqadi (brend, kategoriya, shtrix-kod).

### 7.14 Umumiy planshet — PIN bilan almashish

Shifokor va usta **umumiy planshetda** ishlashi mumkin (bitta planshetni
ikki usta yoki ikki shifokor ishlatadi).

Muammo: har safar login/parol kiritish noqulay → hamma bitta akkauntdan
ishlaydi → **kim nima qilgani noma'lum** → ustaning brak statistikasi va
shifokor ko'rsatkichlari yolg'on bo'ladi.

**Yechim:**
- Planshet **qurilma** sifatida bir marta ro'yxatdan o'tadi (device token)
- Xodim **4 xonali PIN** bilan kiradi/almashadi (2 soniya)
- Har bir amal aniq `user_id` ga yoziladi
- 15 daqiqa harakatsizlikdan keyin avtomatik PIN ekraniga qaytadi
- PIN faqat planshet/telefon uchun, kompyuterda oddiy parol

### 7.15 Yetkazib beruvchi bilan ikki tomonlama hisob

To'lov ikki xil bo'ladi: ba'zan tovar kelishidan **oldin**, ba'zan **keyin**.
Shuning uchun har bir yetkazib beruvchida **bitta balans** va u ikki
tomonga ham harakatlanadi:

| Holat | Ma'nosi | Balans |
|---|---|---|
| Oldindan to'landi | Yetkazib beruvchi **tovar qarzdor** | `+` (avans) |
| Tovar keldi, to'lanmadi | Biz **pul qarzdormiz** | `−` (qarz) |

- `supplier_transactions` — har bir kirim va to'lov yozuvi
- Balans = shu yozuvlar yig'indisi (qoldiq kabi, alohida raqam emas)
- To'lovni konkret kirim hujjatiga bog'lash mumkin (allocation)
- To'lov muddati (`due_date`) va muddati o'tganlar ro'yxati
- Direktor ekranida: "Hoya — 4 200 000 qarz · Luxottica — 1 800 000 avans"

### 7.16 Fiskal chek — HOZIRCHA YO'Q

Soliq uchun onlayn-kassa ishlatilmaydi. Chek faqat ichki hujjat.

**Lekin arxitektura kelajakka tayyor bo'lsin:** chek chiqarish alohida
interfeys ortida bo'lsin (`ReceiptPrinterInterface`). Hozir uning
amalga oshirilishi oddiy printer/PDF. Keyin fiskal apparat kerak bo'lsa —
faqat shu qismi almashtiriladi, `Sales` moduli o'zgarmaydi.

### 7.17 Katalogga tovar qo'shish — direktor tasdig'i bilan

**Oqim:**
```
Admin/omborchi tovarni kiritadi (yoki sotuvchi sotuv paytida qo'shadi)
  → status = pending
  → direktorga Telegram xabar: "Yangi tovar: Ray Ban 3025 qora"
  → direktor tasdiqlaydi / rad etadi / mavjud tovarga birlashtiradi
  → status = approved
```

**KRITIK QOIDA: tasdiqlash savdoni TO'SMAYDI.**

| Status | Sotish | Ombor qoldig'i | Analitika |
|---|---|---|---|
| `pending` | **Ha** | **Ha** | Yo'q — "tekshirilmagan" deb ajratiladi |
| `approved` | Ha | Ha | Ha |
| `rejected` | Yo'q | Asosiy tovarga birlashtiriladi | Asosiy tovar ostida |

Sabab: direktor telefonini ko'rmasa yoki dam olish kunida bo'lsa, savdo
to'xtamasligi kerak. Va ombor qoldig'i **har doim haqiqatga mos** bo'lishi
shart — "tovar bor, lekin tizimda yo'q" eng yomon holat.

- Direktor ekranida: **"Tasdiq kutilmoqda: 7 ta"** — birma-bir yoki ommaviy
- "Dublikat" tanlanganda: mavjud tovarga birlashtiriladi,
  `stock_movements` va sotuv tarixi saqlanadi (`merged_into_id`)
- Analitikada `pending` tovarlar alohida qatorda: "Tekshirilmagan: X so'm"

### 7.18 Filial rejalari

Har oy har bir filialga reja qo'yiladi (direktor belgilaydi).

- Reja turi: sotuv summasi / foyda / buyurtmalar soni
- Filiallar reytingi **reja bajarilishi (%) bo'yicha**, mutlaq summa bo'yicha
  emas — aks holda A filial har doim g'olib chiqadi va bu boshqalarni
  ruhdan tushiradi
- Direktor bosh ekranida: "Reja 68% bajarildi · oyning 71% i o'tdi"
- Reja mukofot hisoblashda ham ishlatilishi mumkin (bonus multiplikatori)

### 7.19 Mukofot foizi — shaxsiy

- Standart qoida rol bo'yicha (`bonus_rules.role`)
- **Shaxsiy qoida undan ustun** (`bonus_rules.user_id`) — direktor har bir
  xodimga alohida foiz biriktiradi
- O'zgartirish tarixi saqlanadi (`valid_from` / `valid_to`) — o'tgan oy
  hisoboti keyingi o'zgarishdan buzilmasligi uchun

---

## 8. MA'LUMOTLAR BAZASI — ASOSIY JADVALLAR

> To'liq migratsiyalar kod yozish paytida yaratiladi. Bu yerda skelet.

### Core
```
branches            id, name, code, type(main|shop), address, lat, lng,
                    open_time, close_time, is_active
users               id, name, phone, email, password, branch_id, is_active,
                    debt_limit (direktor belgilaydi, 0 = qarz bera olmaydi),
                    locale (uz-latn|uz-cyrl|ru|en),
                    pin_hash (planshetda tez almashish uchun — 7.14)
devices             id, branch_id, name, type(tablet|phone|pos), token,
                    allowed_roles, last_seen_at
shifts              id, branch_id, opened_by, closed_by, opened_at, closed_at,
                    opening_cash, expected_cash, actual_cash, difference, status
locations           id, branch_id, type(warehouse|floor|transit|master),
                    owner_id (haydovchi/usta uchun)
```

### Catalog
```
brands              id, name
categories          id, name, parent_id
products            id, type(frame|lens|accessory|ready|service), name,
                    brand_id, category_id, unit, is_active,
                    status(pending|approved|rejected) — 7.17,
                    created_by, approved_by, approved_at,
                    quick_created (sotuv paytida yaratilgan — tozalash kerak),
                    merged_into_id (dublikat birlashtirilganda)
product_variants    id, product_id, sku, barcode, attributes(json),
                    sph, cyl, axis, add, index, coating, color, size
prices              id, variant_id, branch_id(nullable), price, valid_from
services            id, name, price, duration_min, type(exam|assembly|repair)
```

### Warehouse
```
stock_movements     id, variant_id, location_id, quantity, type, source_type,
                    source_id, cost_price, created_by, created_at
stock_balances      location_id, variant_id, quantity, updated_at   (kesh)
purchases           id, supplier_id, branch_id, number, date, total, status
purchase_items      id, purchase_id, variant_id, quantity, cost_price
transfers           id, from_location_id, to_location_id, number, status,
                    delivery_method(own_driver|taxi|by_hand), driver_id,
                    taxi_cost, taxi_receipt_path,
                    sent_by, sent_at, received_by, received_at, trip_id
transfer_items      id, transfer_id, variant_id, qty_sent, qty_received
stock_requests      id, from_branch_id, to_branch_id, variant_id, quantity,
                    order_id(nullable), status
inventories         id, branch_id, status, started_at, finished_at
inventory_items     id, inventory_id, variant_id, expected_qty, actual_qty
defects             id, variant_id, location_id, quantity, reason,
                    order_id(nullable), reported_by, cost_impact
lost_sales          id, branch_id, variant_id, customer_id(nullable), created_at
```

### Sales
```
customers           id, name, phone, birth_date, telegram_id, notes,
                    debt_balance, first_visit_at, abandoned_orders_count,
                    locale
orders              id, number, branch_id, customer_id, type(quick|order),
                    status, payment_status, prescription_id(nullable),
                    total, discount, paid, debt, due_date, delivery_type,
                    created_by, delivered_at, abandoned_at
order_items         id, order_id, itemable_type, itemable_id (variant|service),
                    quantity, price, discount, cost_price,
                    custom_lens_params(json — individual linza uchun, 7.13)
payments            id, order_id, customer_id, amount, method, shift_id,
                    received_by, collected_by(driver), status, paid_at
returns             id, order_id, reason, amount, created_by
```

### Clinic
```
visits              id, branch_id, customer_id, queue_number, status,
                    source(qr|seller), doctor_id, started_at, finished_at
prescriptions       id, visit_id, customer_id, doctor_id,
                    od_sph, od_cyl, od_axis, od_add,
                    os_sph, os_cyl, os_axis, os_add,
                    pd, pd_near, prism, notes,
                    valid_until (default +12 oy — 7.11)
```

### Payroll va rejalar
```
bonus_rules         id, user_id(nullable), role(nullable), branch_id(nullable),
                    base(revenue|profit|count), percent,
                    valid_from, valid_to
                    -- user_id bo'lsa u rol qoidasidan ustun (7.19)
bonus_entries       id, user_id, order_id, period, amount, status,
                    calculated_at
branch_plans        id, branch_id, period (YYYY-MM),
                    type(revenue|profit|orders), target_amount,
                    created_by
```

### Workshop
```
work_orders         id, order_id, master_id, status, priority, due_at,
                    started_at, finished_at, rework_count
work_order_items    id, work_order_id, variant_id, quantity
```

### Delivery
```
trips               id, driver_id, date, status, started_at, finished_at
trip_stops          id, trip_id, sequence, type(branch|customer),
                    branch_id, customer_id, order_id, address, lat, lng,
                    cash_to_collect, status, delivered_at,
                    delivered_lat, delivered_lng, photo_path,
                    customer_confirmed_at, confirmation_status
driver_balances     driver_id, cash_amount, updated_at
collections         id, driver_id, shift_id, amount, received_by, created_at
```

### Finance
```
cash_movements      id, branch_id, shift_id, type(in|out), category,
                    amount, source_type, source_id, description, created_by
expenses            id, branch_id, category_id, amount, date, description
expense_categories  id, name
debts               id, customer_id, order_id, amount, due_date, status
debt_reminders      id, debt_id, sent_at, channel, response
suppliers           id, name, phone, payment_terms_days, notes
supplier_transactions id, supplier_id, type(purchase|payment|return|adjustment),
                    amount (+/-), purchase_id(nullable), due_date,
                    created_by, created_at
```
> Yetkazib beruvchi balansi = `supplier_transactions` yig'indisi.
> Musbat = avans (u bizga tovar qarzdor), manfiy = biz pul qarzdormiz (7.15).

---

## 9. API KONVENTSIYALARI

- Barcha endpointlar: `/api/v1/...`
- Autentifikatsiya: Sanctum token (xodimlar), Telegram initData (mijoz)
- Javob formati:
```json
{ "data": {...}, "meta": {...} }
{ "message": "...", "errors": {...} }   // xato
```
- Ro'yxatlar: `?filter[branch_id]=1&sort=-created_at&include=customer&page=1`
  (spatie/laravel-query-builder)
- Har bir yozuv amali `FormRequest` orqali validatsiya qilinadi
- Biznes mantiq **Controller'da emas**, `Action` yoki `Service` klassida
- Ombor va pul bilan bog'liq har bir amal **tranzaksiya ichida**
- Muhim hodisalar `Event` chiqaradi (`OrderReady`, `TransferReceived`,
  `DebtOverdue`) → `Listener` Telegram xabar yuboradi

### Idempotentlik
Chek yopish, to'lov, transfer qabul qilish — `Idempotency-Key` header
qo'llab-quvvatlansin. Internet uzilib qayta yuborilganda dublikat bo'lmaydi.

---

## 10. UI/UX QOIDALARI

### Ko'p tillilik (birinchi kundan!)

4 ta til: `uz-latn` (default), `uz-cyrl`, `ru`, `en`.

- **`uz-cyrl` qo'lda tarjima qilinmaydi** — `uz-latn` dan avtomatik
  transliteratsiya qilinadi. Ya'ni 3 ta tarjima yoziladi, 4-tasi hosila.
- Interfeys matnlari: `react-i18next` + JSON fayllar
- Backend xato xabarlari: Laravel `lang/` fayllari
- **Tovar nomlari tarjima qilinmaydi** — bitta nom (brendlar lotin,
  har bir tovarga 3 ta nom yozish real emas)
- **Tarjima qilinadi**: kategoriyalar, xizmatlar, brak sabablari,
  buyurtma holatlari, xarajat kategoriyalari (`json` ustun yoki
  `spatie/laravel-translatable`)
- Til foydalanuvchi profilida saqlanadi (`users.locale`, `customers.locale`)
- Telegram bot mijozning `language_code` ini o'zi oladi

### Umumiy
- Interfeys **do'kon tilida** yozilsin — "Narx", "To'lov", "Qoldiq"
  (kanselyar tilida emas: "Прайс", "Транзакция")
- Pul formati: `1 250 000 so'm` (bo'shliq bilan)
- Sana: `05.08.2026`, vaqt: `14:32`
- Bo'sh "Yuklanmoqda" ekran YO'Q — skeleton ko'rsatiladi
- Xato xabari aybdor qidirmaydi, **nima qilish kerakligini** aytadi:
  ✅ "Ombordan chiqarib bo'lmadi: bu tovardan 2 dona qoldi"
  ❌ "Xatolik yuz berdi"
- Ranglar: 1 asosiy + kulranglar + 3 signal (yashil/sariq/qizil)

### Sotuvchi (kassa) — eng muhim ekran
- **Bitta ekran, modal oynasiz.** Chapda qidiruv+tovar, o'ngda chek.
- Qidiruv maydoni sahifa ochilishi bilan **avtomatik faol**
- Klaviatura: `Enter` — qo'shish, `F2` — to'lov, `Esc` — bekor
- Shtrix-skaner = klaviatura, qidiruv maydoniga yozadi
- Qidiruv: nomi, shtrix-kod, artikul, brend — bitta maydondan.
  Lotin/kirill farqi bo'lmasin ("Рэй бан" ham "Ray Ban" ham topsin)
- Tovar qatorida **barcha filiallar qoldig'i**: `A:3 · B:1 · C:0 · D:2`
- Mijoz tanlanganda qarz **qizil** bilan darrov chiqadi
- Xavfli tugmalar ("Bekor qilish", "Qaytarish") yashil "To'lov" dan uzoqda

### Shifokor (planshet, 10", landshaft)
- **Ikki ustun: OD (o'ng) / OS (chap)** — qog'oz blanka ko'rinishida
- **Telefonda ham ishlashi shart** (planshet buzilsa ish to'xtamasin):
  OD va OS bir-birining ostida, kattaroq tugmalar bilan
- SPH/CYL **erkin yozilmaydi**, 0.25 qadamli ro'yxatdan tanlanadi
  (bu "−2.5 o'rniga −25" xatosini oldini oladi — real pul yo'qotish)
- AXIS faqat 0–180
- **"O'tgan retseptni ko'chirish"** tugmasi
- Yangi qiymat eskisidan 1.5+ farq qilsa — ogohlantirish
- Saqlash → sotuvchida avtomatik tiket

### Usta (planshet 10", stend/tutqichda)
- Kanban: Yangi → Ishda → Tayyor, kartochkani surib o'tkazish
- **Telefonda**: kanban o'rniga oddiy ro'yxat + holat filtri
- Kartochkada: mijoz, retsept raqamlari **yirik shrift**, ramka, muddat
- Tugmalar **kamida 60×60 px**
- Rang: kechikkan — qizil, bugun — sariq, vaqti bor — kulrang
- "Brak" → sabab tanlash (3 bosishda tugaydi)

### Haydovchi (telefon)
- Yirik shrift, yuqori kontrast (quyosh ostida ko'rinishi kerak)
- Manzil kartochkalari: kimga, manzil, **[Navigator]**, **[Qo'ng'iroq]**,
  nechta joy, olinadigan pul (qizil)
- Doimiy ko'rinadigan qator: `Mendagi: 12 dona · 1 840 000 so'm`
- **Offline majburiy**: yo'l varaqasi ertalab yuklanadi, amallar telefonda
  saqlanadi, internet kelganda yuboriladi. Ekranda: "3 ta amal yuborilmagan"

### Direktor (telefon)
- Yuqorida **4 ta yirik raqam**: bugungi kassa (↑/↓), sof foyda, qarzlar,
  kechikkan buyurtmalar
- Ostida 5 filial jadvali (kassa + reja %)
- Ostida **"E'tibor talab qiladi"** — eng qimmatli bo'lim:
  "B filialda kassa 40 000 kam", "3 ta transfer qabul qilinmagan",
  "Hoya 1.61 uch marta yo'q bo'ldi", "5 ta qarz muddati o'tgan"
- Grafiklar chuqurroq sahifalarda. Bosh ekranda **xulosa**, dashboard emas.

### Mijoz (Mini App)
- Bir ekranda: faol buyurtma holati → retseptim → qarzim → tarix
- Retsept — **saqlab olsa bo'ladigan chiroyli karta** (boshqa optikada
  ko'rsatadi = bepul reklama)
- Ro'yxatdan o'tish formasi YO'Q — Telegram kim ekanini o'zi biladi

---

## 11. BOSQICHLAR (ROADMAP)

Hammasini birdan qilmang. Har bosqich oxirida do'kon **haqiqatan
foydalana boshlashi** kerak.

### Bosqich 1 — Skelet
Auth, rollar, filiallar, xodimlar, ma'lumotnomalar, tovar kartochkasi,
admin SPA karkasi.

### Bosqich 2 — Ombor kirimi va qoldiq
`stock_movements`, kirim hujjati, qoldiqni ko'rish, shtrix-kod.
*Shu bosqichdan keyin do'kon allaqachon foyda ko'radi.*

### Bosqich 3 — Savdo va kassa
Chek, to'lov turlari, chegirma, qaytarish, smena ochish/yopish, kassa hisobi.

### Bosqich 4 — Transfer
Ikki bosqichli transfer, ichki so'rov, nomuvofiqlik hujjati.

### Bosqich 5 — Klinika
Vizit, QR, navbat, retsept, sotuvchiga avtomatik tiket.

### Bosqich 6 — Ustaxona
Kanban, usta zaxirasi, brak, qayta ishlash.

### Bosqich 7 — Telegram bot
Faqat bildirishnoma: buyurtma tayyor, qarz muddati, ko'rik eslatmasi.
*Bir haftalik ish, foydasi darrov ko'rinadi.*

### Bosqich 8 — Yetkazish
Yo'l varaqasi, haydovchi PWA, GPS tasdiq, inkassatsiya, Yandex Maps.

### Bosqich 9 — Mijoz kabineti (Mini App)
Retseptim, buyurtmam, qarzim → keyin katalog, baholash.

### Bosqich 10 — Moliya, mukofot va analitika
Xarajatlar, qarz hisobotlari, mukofot hisoblash, ABC, o'lik zaxira,
yo'qotilgan savdo, olib ketilmagan buyurtmalar zarari, filiallar reytingi.

### Bosqich 10.5 — Katalogni tozalash
`quick_created` tovarlarni to'ldirish (brend, kategoriya, shtrix-kod),
dublikatlarni birlashtirish. Bu doimiy jarayon, oyiga bir marta.

### Bosqich 11 — Landing
SEO, manzillar, narxlar, onlayn navbat.

### Bosqich 12 — React Native
**Faqat kerak bo'lsa.** PWA yetmay qolganda (fon rejimi, push).

---

## 12. NOMLASH KONVENTSIYALARI

- Jadvallar: `snake_case`, ko'plikda (`stock_movements`)
- Model: `StudlyCase`, birlikda (`StockMovement`)
- Enum'lar: PHP `enum` klass (`OrderStatus`, `PaymentMethod`, `DefectReason`)
- Action klasslari: `CreateOrderAction`, `ReceiveTransferAction`
- Frontend komponentlari: `PascalCase`, papkalar `kebab-case`
- API endpointlar: `kebab-case` (`/api/v1/work-orders`)
- Migratsiyalar: modul bo'yicha tartiblangan

---

## 13. NIMA QILMASLIK KERAK

❌ Ombor qoldig'ini `products.quantity` ustunida saqlash
❌ Pulni `float` da saqlash
❌ Har bir diopter kombinatsiyasini alohida tovar qilish
❌ Transferni bir bosqichda ("jo'natildi = yetkazildi") qilish
❌ Buyurtma avansini darrov "daromad" deb yozish
❌ Biznes mantiqni Controller ichiga yozish
❌ Direktorga bosh ekranda 12 ta grafik ko'rsatish
❌ Mijozni mobil ilova o'rnatishga majburlash
❌ Haydovchini kun bo'yi GPS bilan kuzatish (huquqiy + batareya + ishonch)
❌ Ombor va Sotuvni alohida ilova qilish (sinxronizatsiya do'zaxi)
❌ Usta ekranini murakkab qilish (u ish qilishi kerak, yozuvchi emas)
❌ **Retsept tiketini boshqa filialga tushirish** (keraksiz brak ko'zoynak!)
❌ Taksi xarajatini transferga bog'lamasdan yozish
❌ Mukofotni buyurtma ochilganda hisoblash (soxta buyurtmalar chiqadi)
❌ Ko'p tillilikni keyinga qoldirish (keyin qo'shish 3 barobar qimmat)
❌ **Tovarni erkin matn (free text) sifatida saqlash** — ombor, transfer va
   butun analitika ishlamay qoladi (7.13)
❌ Loyihani "avval 2000 ta tovarni kiriting" deb boshlash (hech kim qilmaydi)
❌ Umumiy planshetda hamma bitta akkauntdan ishlashi (statistika yolg'on bo'ladi)
❌ **Tasdiqlanmagan tovarni sotishga ruxsat bermaslik** — savdo to'xtaydi (7.17)
❌ Tasdiqlanmagan tovarni ombor qoldig'idan chiqarib tashlash
   ("tovar bor, tizimda yo'q" — eng yomon holat)
❌ Filiallar reytingini mutlaq summa bo'yicha qilish (A har doim g'olib)

---

## 14. TESTLAR

Majburiy test qamrovi:
- Ombor harakatlari: qoldiq har doim `stock_movements` yig'indisiga teng
- Transfer: jo'natish → yo'lda → qabul (miqdor farqi bilan)
- To'lov: qisman to'lov, ortiqcha to'lov, qaytarish
- Smena yopilishi: kamomad hisoblanishi
- Buyurtma holatlari: noto'g'ri o'tishga ruxsat berilmasligi
- Haydovchi balansi: yetkazish → inkassatsiya → nol

---

## 15. QABUL QILINGAN QARORLAR

| # | Savol | Qaror |
|---|---|---|
| 1 | Haydovchi | **1 ta.** B↔C kabi uzoq yo'nalishlar — taksi (7.10) |
| 2 | Avans | **Talab qilinmaydi.** Lekin zarar o'lchanadi (7.7) |
| 3 | Qarz limiti | Direktor **har bir sotuvchiga alohida** belgilaydi |
| 4 | Uyga yetkazish | Buyurtma bo'yicha, epizodik. Alohida modul shart emas |
| 5 | Katalog | **Bo'sh boshlanadi**, sotuv paytida to'ldiriladi (7.13). Erkin matn ISHLATILMAYDI |
| 6 | Retsept qamrovi | **Faqat o'z filiali** — bu kritik qoida (7.11) |
| 7 | Tillar | uz-latn, uz-cyrl (avto), ru, en — **birinchi kundan** |
| 8 | Mukofot | Bor. Topshirilgan + to'liq to'langan buyurtmadan (7.12) |
| 9 | Qurilmalar | Shifokor va usta — **planshet 10"**, PIN bilan almashish (7.14). Telefon fallback shart |
| 10 | Fiskal chek | **Yo'q.** Lekin arxitektura kelajakka tayyor (7.16) |
| 11 | Yetkazib beruvchi | To'lov oldindan ham, keyin ham. **Ikki tomonlama balans** (7.15) |
| 12 | Katalog tasdig'i | Admin kiritadi → **direktor tasdiqlaydi**. Lekin savdo to'smaydi (7.17) |
| 13 | Filial rejalari | Bor. Reyting **reja %** bo'yicha (7.18) |
| 14 | Mukofot foizi | Direktor **har bir xodimga alohida** biriktiradi (7.19) |

## 16. HALI OCHIQ

Barcha asosiy savollar javob oldi. Kod yozish jarayonida chiqadigan
savollar shu bo'limga qo'shib boriladi.

Ish jarayonida aniqlashtiriladi:
- Mukofot bazasi: sotuv summasidanmi yoki foydadanmi (tavsiya: foydadan)
- Reja turi: summa / foyda / buyurtmalar soni
- Retsept amal qilish muddati (default 12 oy) — shifokor bilan kelishilsin
