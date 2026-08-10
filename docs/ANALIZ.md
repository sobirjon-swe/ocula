# OPTIKA — PROJECT.md tahlili va kod yozishga tayyorlov

> Manba: `docs/PROJECT.md`
> Tahlil sanasi: 2026-08-10
> Maqsad: hujjatni kod yoziladigan holatga keltirish — bo'shliqlarni, ziddiyatlarni
> va qaror talab qiladigan nuqtalarni aniqlash.

---

## 0. Xulosa (qisqacha)

Hujjat sifati **juda yuqori**. 7-bo'lim (biznes qoidalar) real optika biznesidan
chiqqan va odatda TZ larda uchramaydigan darajada aniq: ombor daftari, ikkita
alohida holat o'qi, retseptning filial bo'yicha cheklanishi, mukofot sharti,
katalogning "yurib" to'ldirilishi, tasdiqning savdoni to'smasligi. Bular
loyihaning eng qimmatli qismi va ularni o'zgartirmaslik kerak.

Lekin **kod yozishni hoziroq boshlab bo'lmaydi**. Uch sabab:

1. **Mavjud kod hujjatga zid** — repoda Inertia + Fortify starter kit turibdi,
   hujjatda esa API-only + alohida SPA + Sanctum yozilgan (1-bo'lim).
2. **4 ta bloker qaror ochiq** — arxitektura, tannarx metodikasi (COGS), pul tipi,
   DB. Bularsiz birinchi migratsiyani ham yozib bo'lmaydi (2-bo'lim).
3. **Sxemada ~16 ta aniq bo'shliq** bor — enum qiymatlari, `return_items`,
   `idempotency_keys`, transit location egasi va h.k. (3-bo'lim).

Tayyorlov ishi (Bosqich 0) taxminan **2–3 kunlik**, undan keyin Bosqich 1 ga
to'siqsiz kirish mumkin (5-bo'lim).

---

## 1. Mavjud kod bazasi vs hujjat

Repoda `laravel/react-starter-kit` o'rnatilgan (`composer.json:2`). Bu Inertia
asosidagi monolit — hujjatdagi arxitektura emas.

| Mavzu | PROJECT.md talabi | Repoda hozir | Holat |
|---|---|---|---|
| Repo tuzilmasi | `optika/{backend,admin,customer,landing,mobile}` | `ocula/` — bitta Laravel ilova, root'da | ❌ zid |
| Backend↔Frontend | API-only, Blade yo'q (§2) | Inertia 3 + Blade `app.blade.php` | ❌ zid |
| Admin frontend | Vite + React Router + TanStack Query + Zustand | Inertia React 19 + Wayfinder | ❌ zid |
| Auth | Sanctum (SPA + mobil) | Fortify (session) + passkeys + 2FA | ❌ zid |
| DB | PostgreSQL (yoki MySQL 8) | `DB_CONNECTION=sqlite` | ❌ zid |
| Cache / Queue | Redis + Horizon | `database` drayveri | ❌ zid |
| Modullar | `app/Modules/{Core,Catalog,...}` | standart `app/Models`, `app/Http` | ⚠️ yo'q |
| spatie/permission | Talab | O'rnatilmagan | ⚠️ yo'q |
| spatie/activitylog | Talab | O'rnatilmagan | ⚠️ yo'q |
| spatie/query-builder | Talab | O'rnatilmagan | ⚠️ yo'q |
| maatwebsite/excel | Talab | O'rnatilmagan | ⚠️ yo'q |
| Ko'p tillilik (4 til) | Birinchi kundan (§10) | i18n infratuzilmasi yo'q | ⚠️ yo'q |
| Git | — | **Repo umuman yo'q** | 🔴 kritik |
| Migratsiyalar | ~40 jadval | 5 ta (starter: users, cache, jobs, passkeys, 2FA) | — |

**Ijobiy tomoni:** starter kitda foydali narsalar bor va ularni saqlab qolish
mumkin — PHP 8.3, Laravel 13, Tailwind 4, shadcn/ui (`components.json`),
TypeScript, ESLint + Prettier, Pint, PHPStan (larastan), `composer ci:check`
skripti va tayyor GitHub Actions (`.github/workflows/tests.yml`). Ya'ni sifat
vositalari allaqachon sozlangan.

**Eng kritik:** git repo yo'q. Birinchi qatordan oldin `git init` qilinishi shart —
aks holda starter kitni o'zgartirish paytida orqaga qaytish imkoni bo'lmaydi.

---

## 2. BLOKER qarorlar — kod yozishdan oldin yopilishi shart

### 2.1 🔴 Arxitektura: Inertia monolit vs API + alohida SPA

Hujjat API-only ni talab qiladi, repoda Inertia turibdi. Ikkalasining ham
o'z o'rni bor:

| | Inertia monolit | API + alohida SPA |
|---|---|---|
| Ishlab chiqish tezligi | ~2 barobar tez (CRUD uchun) | Sekinroq (har endpoint 2 marta yoziladi) |
| Auth | Tayyor (Fortify) | Sanctum sozlash kerak |
| Mijoz Mini App | ❌ Inertia yaramaydi | ✅ tabiiy |
| Haydovchi offline PWA | ❌ yaramaydi | ✅ tabiiy |
| React Native (Bosqich 12) | ❌ yaramaydi | ✅ tayyor |
| Deploy | Bitta | Bitta (lekin 3 build) |

**Tavsiya — gibrid** (hujjatning ruhiga ham, mavjud kodga ham mos):

- **Xodimlar paneli** (sotuvchi, omborchi, direktor, buxgalter) → **Inertia**.
  Bu ekranlar brauzerda, sessiya bilan ishlaydi, offline kerak emas.
  Mavjud starter kit to'liq ishlatiladi.
- **`/api/v1/*` (Sanctum)** → mijoz Mini App, haydovchi PWA, shifokor/usta
  planshet ilovasi (PIN auth), kelajakdagi RN.
- **Biznes mantiq `Action`/`Service` da** — Inertia controller ham, API
  controller ham bir xil Action ni chaqiradi. Kod dublikati yo'q.

Bu qaror hujjat §2 ga zid ("Blade shablonlar ishlatilmaydi"), shuning uchun
tasdiqlanishi va PROJECT.md ga yozilishi kerak.

**Agar sof API+SPA tanlansa:** starter kitning Inertia qismi olib tashlanadi
(`resources/js`, `inertiajs/*`, `wayfinder`), Sanctum qo'shiladi, `admin/`
papkasi alohida Vite ilova sifatida yaratiladi. Bu ~1 kunlik qo'shimcha ish
va keyingi har bir ekran uchun taxminan +40% vaqt.

### 2.2 🔴 Tannarx (COGS) metodikasi — hujjatda UMUMAN YO'Q

Bu eng katta bo'shliq. Hujjat quyidagilarni talab qiladi:
- §7.8 — "sof foyda" hisoboti
- §7.12 — "mukofot foydadan foiz" (tavsiya etilgan)
- §6.11 — ABC tahlil, o'lik zaxira
- §7.5 — brakning `cost_impact`

Bularning hammasi **sotilgan tovarning tannarxi** ga bog'liq. `stock_movements`
da `cost_price` bor, lekin **qaysi tannarx olinishi** aytilmagan. Bir xil
ramkani 100 000 ga ham, 120 000 ga ham olgan bo'lsangiz — sotilganda qaysi biri?

| Metod | Tavsif | Murakkablik | Optikaga mosligi |
|---|---|---|---|
| **O'rtacha og'irlikli (WAC)** | Har kirimda `(eski_qoldiq×eski_narx + yangi)/jami` | Past | ✅ Yaxshi |
| FIFO (qatlamli) | Har kirim alohida qatlam, birinchi kirgan birinchi chiqadi | Yuqori | Aniqroq, lekin ortiqcha |
| Oxirgi narx | Eng oxirgi kirim narxi | Eng past | ❌ Foydani buzadi |

**Tavsiya: WAC, `(variant_id, location_id)` bo'yicha.**
Har `stock_movement` yaratilganda joriy o'rtacha tannarx hisoblanadi va
`cost_price` ga muzlatib yoziladi. Bu:
- §7.1 "qoldiq = harakatlar yig'indisi" tamoyilini buzmaydi,
- FIFO qatlamlari kabi qo'shimcha jadval talab qilmaydi,
- optikada partiya-partiya (lot/serial) kuzatish kerak emas.

Qaror qabul qilinmaguncha `stock_movements`, `order_items`, `defects`,
`purchases` migratsiyalarini yozib bo'lmaydi.

### 2.3 🟡 Pul tipi — hujjat ikkitasini taklif qiladi

§2: "Pul butun son (tiyinda) **yoki** `decimal(15,2)`". Bittasi tanlanishi shart —
loyihaning 30+ ustuniga ta'sir qiladi va keyin o'zgartirish og'riqli.

**Tavsiya: `bigint`, tiyinda.**
- PHP tomonda hech qanday float xavfi yo'q (int arifmetika),
- JS/TypeScript tomonda ham xavfsiz (1 mlrd so'm = 100 mlrd tiyin,
  `Number.MAX_SAFE_INTEGER` dan ancha kichik),
- Laravel `Cast` orqali `Money` value object ga o'raladi,
- foiz/chegirma yaxlitlash qoidasi bitta joyda (`Money::percent()`).

Alternativa `decimal(15,2)` — DB darajasida to'g'ri, lekin PHP `decimal` ni
string qilib qaytaradi va har joyda `bcmath` kerak bo'ladi. Ko'proq intizom talab.

**Qo'shimcha qoida (hujjatda yo'q):** yaxlitlash. So'm tiyinsiz aylanadi.
Chegirma 33.3% bo'lsa nechaga yaxlitlanadi? Tavsiya: hisob-kitob tiyinda,
mijozga ko'rsatiladigan yakuniy summa **100 so'mga** yaxlitlanadi
(`round half up`), farq `rounding` qatoriga yoziladi.

### 2.4 🟡 Ma'lumotlar bazasi: PostgreSQL kerak

Hozir `sqlite`. §7.13 fuzzy qidiruvni (o'xshash nom taklifi, lotin/kirill)
talab qiladi — bu **`pg_trgm`** kengaytmasini talab qiladi. Shuningdek
`product_variants.attributes(json)` bo'yicha `GIN` indeks ham Postgresda tabiiy.

MySQL 8 da trigram yo'q — `SOUNDEX`/`LIKE` bilan sifat pastroq bo'ladi.
SQLite butunlay yaramaydi (parallel yozuv, JSON operatorlari, trigram).

**Tavsiya: PostgreSQL 16 + Redis, Docker Compose orqali** (Windowsda eng oson yo'l).
`laravel/sail` allaqachon dev-dependency da bor, lekin Windowsda WSL2 ustidagi
fayl I/O sekin — sof `docker-compose.yml` (faqat DB va Redis) tezroq.

---

## 3. Sxemadagi bo'shliqlar — migratsiya yozishdan oldin to'ldirish

§8 dagi jadvallar skeleti yaxshi, lekin quyidagilar yetishmaydi:

### 3.1 Enum qiymatlari aniqlanmagan
`status` ustuni 10+ joyda bor, lekin qiymatlari faqat `orders` uchun berilgan (§7.3).
Aniqlanishi kerak: `transfers.status`, `purchases.status`, `inventories.status`,
`stock_requests.status`, `work_orders.status`, `visits.status`, `trips.status`,
`trip_stops.status`, `payments.status`, `debts.status`, `bonus_entries.status`,
`shifts.status`, `cash_movements.category`.
→ Kod yozishdan oldin `docs/ENUMS.md` da to'liq ro'yxat tuzilishi kerak.

### 3.2 `locations` — transit egasi noaniq
§8 da `locations.owner_id` bor, lekin `owner_type` yo'q. §7.10 ga ko'ra "yo'lda"
qoldiq egasi uch xil bo'ladi: haydovchi (`own_driver`), **transferning o'zi**
(`taxi`), xodim (`by_hand`). Ya'ni polimorf bog'lanish kerak:
`owner_type` (`User` | `Transfer`) + `owner_id`.

### 3.3 Sotuv qaysi location dan chiqadi?
§7.1 location turlari: `warehouse`, `floor`, `transit`, `master`. Lekin `orders`
va `order_items` da faqat `branch_id` bor. Chek yopilganda tovar `floor` dan
chiqadimi yoki `warehouse` dan?
→ **Tavsiya:** 1-versiyada har filialda **bitta** `warehouse` location, `floor`
ishlatilmaydi (ortiqcha murakkablik — sotuvchi ikki joy o'rtasida ko'chirish
hujjati yozishi kerak bo'lib qoladi). Kerak bo'lsa keyin qo'shiladi, sxema
buni qo'llab-quvvatlaydi.

### 3.4 `stock_movements.type` to'liq emas
Bor: `purchase|sale|transfer_out|transfer_in|adjustment|defect|return`.
Yetishmaydi:
- `consume` — usta o'z zaxirasidagi linzani buyurtmaga ishlatdi (sotuv emas,
  lekin COGS ga tushadi)
- `write_off` — inventarizatsiya kamomadi (`adjustment` dan ajratilsa
  hisobotda toza ko'rinadi)
- `merge` — dublikat tovar birlashtirilganda (§7.17) qoldiqni ko'chirish

### 3.5 Daromad tan olinishi ustuni yo'q
§7.8 talabi: "kechagi buyurtma bugun topshirilsa — daromad **bugunga**".
`orders` da `delivered_at` bor, lekin bu bajarilish sanasi. Foyda hisoboti uchun
`revenue_recognized_at` (tez savdoda = chek yopilgan payt, buyurtmada = topshirilgan
payt) alohida ustun bo'lgani ma'qul.

Shuningdek §7.8 dagi **"Bajarilmagan buyurtmalar majburiyati"** —
`SUM(paid) WHERE status != delivered`. Bu hosila, jadval kerak emas, lekin
indeks kerak: `orders(status, payment_status)`.

### 3.6 `returns` da tovar qatorlari yo'q
`returns(id, order_id, reason, amount, created_by)` — faqat summa. Lekin tovar
ham qaytadi va `stock_movements(type=return)` yozilishi kerak. Qaysi variant,
nechta? → `return_items(return_id, order_item_id, variant_id, quantity, amount)`
kerak.

### 3.7 `idempotency_keys` jadvali yo'q
§9 `Idempotency-Key` header ni talab qiladi, lekin uni saqlaydigan joy yo'q.
Kerak: `idempotency_keys(key, user_id, endpoint, request_hash, response_body,
status_code, created_at, expires_at)` + `unique(key)`.

### 3.8 `lost_sales` — katalogda yo'q tovarni qanday yozamiz?
`lost_sales(variant_id, ...)` — lekin §7.9 "sotuvchi tovar qidirib topolmaganda"
deydi. Agar tovar katalogda umuman bo'lmasa, `variant_id` yo'q.
→ `variant_id` nullable + `search_term` (matn) ustuni. Bu §7.13 dagi
"erkin matn ishlatilmaydi" qoidasiga zid emas — bu analitik log, ombor emas.

### 3.9 Individual linza tannarxi COGS ga qanday tushadi?
§7.13: individual buyurtma linzalari ombordan o'tmaydi, to'g'ridan-to'g'ri
buyurtmaga yoziladi. Lekin ular yetkazib beruvchidan sotib olinadi va **tannarxi
bor**. `order_items.cost_price` bor — kim to'ldiradi?
→ Qoida kerak: sotuvchi/omborchi individual linza qo'shganda tannarxni qo'lda
kiritadi, yoki `purchases` bilan bog'lanadi (`order_items.purchase_item_id`).
Aks holda bu buyurtmalar bo'yicha foyda **soxta yuqori** ko'rinadi — va §7.12
bo'yicha mukofot ham noto'g'ri hisoblanadi.

### 3.10 BranchScope `stock_movements` da ishlamaydi
§4: "har bir so'rov filialga qarab avtomatik cheklanadi". Lekin `stock_movements`
da `branch_id` yo'q — faqat `location_id`. Har so'rovda `join locations` qilish
qimmat va global scope ichida noqulay.
→ **Tavsiya:** `stock_movements.branch_id` denormalizatsiya qilinadi
(`transit` uchun jo'natuvchi filial yoziladi).

### 3.11 `users.branch_id` bitta — ko'p filialli xodimlar
Direktor, buxgalter, haydovchi, omborchi bir filialga bog'lanmaydi. Hozir
`director`/`accountant` uchun "hammasini ko'radi" istisnosi bor, lekin haydovchi
va filiallar orasida ko'chib yuruvchi sotuvchi uchun yechim yo'q.
→ Tavsiya: `users.branch_id` ni **asosiy filial** deb qoldirish + `branch_user`
pivot (kirish huquqi bo'lgan filiallar). 1-versiyada pivot bo'sh bo'lishi mumkin.

### 3.12 Hujjat raqamlari (`number`) generatsiyasi
`orders.number`, `transfers.number`, `purchases.number` bor, lekin format va
parallel yaratishda takrorlanmaslik kafolati yo'q.
→ Format: `{BRANCH_CODE}-{YY}{MM}-{NNNNN}` (masalan `A-2608-00147`).
Generatsiya: `document_sequences(branch_id, type, period, last_number)` jadvali +
`SELECT ... FOR UPDATE` tranzaksiya ichida.

### 3.13 Moliyaviy hujjatlarning o'zgarmasligi
Hujjatda aytilmagan, lekin muhim: to'lov, chek, ombor harakati **o'chirilmasligi
va tahrirlanmasligi** kerak. Xato bo'lsa — **storno** (teskari yozuv).
Aks holda §7.1 "qoldiq = yig'indi" tamoyili va audit log ma'nosini yo'qotadi.
→ Qoida: `stock_movements`, `payments`, `cash_movements` — insert-only
(`updated_at` ham yo'q).

### 3.14 Fuzzy qidiruv dizayni
§7.13 va §10 ("Рэй бан" ham "Ray Ban" ham topsin) talab qiladi, lekin qanday
amalga oshirilishi yozilmagan.
→ `products.search_key` — hosila ustun: nom → kichik harf → transliteratsiya
(kirill→lotin) → bo'shliq va tirelarsiz. Ustiga `GIN (search_key gin_trgm_ops)`.
Transliteratsiya jadvali frontend va backendda **bitta manbadan** bo'lishi kerak
(§10 dagi `uz-cyrl` avto-transliteratsiya bilan bir xil qoida).

### 3.15 Ruxsatlar (`permission`) ro'yxati enumeratsiya qilinmagan
§4 formatni beradi (`module.action`), lekin to'liq ro'yxat yo'q. 9 ta rol × ~10
modul = ~80–120 ta permission. Bosqich 1 da faqat kerakli qismi yaratiladi,
lekin ro'yxat `docs/PERMISSIONS.md` da yuritilishi kerak — aks holda har modulda
nom uslubi og'ib ketadi.

### 3.16 Boshqa kichik bo'shliqlar
- **Vaqt zonasi va "bugun"**: baza UTC (§2), lekin smena 09:00–22:00 Toshkent.
  "Bugungi kassa" — kalendar kunimi yoki smenami? → **Tavsiya: smena bo'yicha.**
  Kunlik hisobot = shu kun yopilgan smenalar yig'indisi. Aks holda 22:00 dan
  keyingi yozuv ertangi kunga tushib qoladi.
- **Fayl saqlash**: `photo_path`, `taxi_receipt_path` — disk (`local`/S3),
  maksimal hajm, rasm siqish qoidasi aytilmagan.
- **Mijoz autentifikatsiyasi**: `customers` jadvalida parol yo'q. Telegram orqali
  ishlaydi, lekin Telegramsiz PWA uchun telefon + SMS OTP kerakmi? (Eskiz.uz
  "zaxira kanal" deb aytilgan, lekin auth uchunmi — noaniq.)
- **Offline sinxronizatsiya** (§10, haydovchi): mijozda navbat, qayta yuborish,
  ziddiyat (conflict) qoidalari tavsiflanmagan. Bosqich 8 gacha vaqt bor, lekin
  `Idempotency-Key` bugundan to'g'ri qo'yilsa — o'sha paytda oson bo'ladi.
- **`prices`**: `branch_id` nullable + `valid_from` bor, lekin "qaysi narx yutadi"
  qoidasi yo'q. → Filial narxi > global narx; `valid_from <= now()` ichidan eng
  oxirgisi. `valid_to` yo'qligi narx tarixini tiklashni qiyinlashtiradi.

---

## 4. Risklar

| Risk | Ta'sir | Yumshatish |
|---|---|---|
| **spatie paketlari Laravel 13 ni qo'llamasligi mumkin** | Bosqich 1 to'xtaydi | Birinchi kuni `composer require` bilan tekshirish; permission uchun zaxira — o'z jadvallari |
| **Laravel 13 + Inertia 3 juda yangi** | Kam hujjat, ko'p qirralar | Starter kit sinovdan o'tgan; undan katta og'ish qilmaslik |
| **12 bosqich, katta hajm** | Muddat cho'zilishi | Har bosqich mustaqil foydali (hujjat to'g'ri qilgan); Bosqich 1–3 ni MVP deb belgilash |
| **Windowsda Postgres + Redis** | Dev muhit og'riqli | Docker Desktop + faqat DB/Redis uchun `docker-compose.yml` |
| **4 til birinchi kundan** | Har ekran +20% vaqt | To'g'ri qaror; `uz-cyrl` avtomatik — amalda 3 ta fayl yuritiladi |
| **Bitta dasturchi, 9 rol × ~40 ekran** | Charchash, yarim tugagan modullar | Bosqich 1 da faqat `director` + `seller` rollari to'liq ishlansin |

---

## 5. Bosqich 0 — tayyorlov ishlari (2–3 kun)

Bosqich 1 ni boshlashdan oldin bajarilishi kerak:

### 0.1 Versiya nazorati (30 daq) 🔴
```
git init
# .gitignore tekshiriladi (.env, vendor, node_modules, storage, *.sqlite)
git add . && git commit -m "chore: laravel react starter kit baseline"
```

### 0.2 Qarorlarni yopish va yozib qo'yish (2 soat)
2-bo'limdagi 4 ta bloker qaror + 3-bo'limdagi bo'shliqlar bo'yicha javoblar
`docs/PROJECT.md` §15 jadvaliga (15–25 qatorlar) qo'shiladi.

### 0.3 Hujjatlarni to'ldirish (4 soat)
- `docs/ENUMS.md` — barcha status/type qiymatlari (3.1)
- `docs/PERMISSIONS.md` — `module.action` ro'yxati (3.15)
- `docs/SCHEMA.md` — §8 ning to'ldirilgan varianti (3.2–3.12)

### 0.4 Dev muhit (3 soat)
- `docker-compose.yml`: PostgreSQL 16 (`pg_trgm`, `unaccent` bilan), Redis 7
- `.env` va `.env.example` yangilanadi
- `php artisan migrate` ishlashini tekshirish

### 0.5 Paketlar (2 soat)
```
composer require laravel/sanctum spatie/laravel-permission \
    spatie/laravel-activitylog spatie/laravel-query-builder \
    laravel/horizon predis/predis
```
Har birining Laravel 13 mosligi shu yerda ma'lum bo'ladi (Risk 1).

### 0.6 Modul skeleti (3 soat)
```
app/Modules/{Core,Catalog,Warehouse,Sales,Clinic,Workshop,Delivery,Finance,Customer,Analytics}
app/Support/
```
`composer.json` PSR-4 ga qo'shimcha kerak emas (`App\` allaqachon `app/` ga
ishora qiladi). Har modul uchun `Models/`, `Actions/`, `Services/`,
`Http/{Controllers,Requests,Resources}`, `Policies/`, `Events/`, `Listeners/`,
`Enums/` papkalari + `ModuleServiceProvider` (migratsiya va route yuklash).

### 0.7 `app/Support/` poydevori (1 kun) — eng muhim qism
Bularsiz keyingi har bir modul takroriy kod yozadi:

| Komponent | Vazifasi | Hujjat bandi |
|---|---|---|
| `Money` value object + `MoneyCast` | Tiyin arifmetikasi, yaxlitlash, formatlash | §2, 2.3 |
| `BranchScope` + `BelongsToBranch` trait | Avtomatik filial cheklovi | §4, 3.10 |
| `StockLedger` service | `stock_movements` yozish + `stock_balances` yangilash + WAC tannarx | §7.1, 2.2 |
| `DocumentNumber` service | Raqam generatsiyasi | 3.12 |
| `IdempotencyMiddleware` + jadval | Takroriy so'rovdan himoya | §9, 3.7 |
| `ApiResponse` / `BaseResource` | `{data, meta}` formati | §9 |
| `Transliterator` | kirill↔lotin (qidiruv + `uz-cyrl`) | §10, 3.14 |
| `ReceiptPrinterInterface` | Fiskal kelajak uchun | §7.16 |
| Bazaviy enumlar | `OrderStatus`, `PaymentMethod`, `MovementType`, `DefectReason` | §12, 3.1 |

### 0.8 Sifat darvozasi (1 soat)
`composer ci:check` allaqachon bor (pint + phpstan + eslint + prettier + test),
GitHub Actions ham (`.github/workflows/tests.yml`) — Postgres servisi qo'shiladi,
`phpstan.neon` darajasi 6+ ga ko'tariladi.

---

## 6. Bosqich 1 — aniq ish ro'yxati

Bosqich 0 tugagach, hujjat §11 bo'yicha:

**Migratsiyalar:** `branches`, `locations`, `users` kengaytmasi (`branch_id`,
`debt_limit`, `locale`, `pin_hash`, `is_active`), `branch_user`, `devices`,
`shifts`, `document_sequences`, `idempotency_keys`, spatie permission jadvallari,
spatie activitylog jadvali, `brands`, `categories`, `products`,
`product_variants`, `prices`, `services`.

**Modellar + Policy:** yuqoridagilarning har biri, `BelongsToBranch` bilan.

**Seeder:** 5 filial (A=`main` + B,C,D,E=`shop`), 9 rol + permissionlar, demo
xodimlar, har filialga bitta `warehouse` location.

**Ekranlar (2.1 qaroriga qarab Inertia yoki SPA):**
login → dashboard karkas → filiallar CRUD → xodimlar CRUD (rol biriktirish) →
ma'lumotnomalar (brend, kategoriya, xizmat) → tovar kartochkasi (variantlar bilan)
→ smena ochish/yopish.

**i18n:** `uz-latn` (asosiy) + `ru` + `en` JSON fayllari, `uz-cyrl` avtomatik
transliteratsiya; backend `lang/` fayllari.

**Testlar (§14 ga tayyorgarlik):** BranchScope izolyatsiyasi, permission
tekshiruvi, smena yopilishida kamomad hisobi, `Money` arifmetikasi va yaxlitlash,
`DocumentNumber` parallel generatsiyasi.

---

## 7. `docs/PROJECT.md` ga kiritilishi tavsiya etilgan o'zgarishlar

1. §2 — arxitektura qarori (2.1) aniq yozilsin, "Blade ishlatilmaydi" bandi
   qayta ifodalansin.
2. §2 — pul tipi bitta qilib tanlansin (2.3) + yaxlitlash qoidasi qo'shilsin.
3. §7 ga **yangi band 7.20 — Tannarx va COGS** (2.2). Bu eng muhim yetishmayotgan
   qoida.
4. §7.1 — `location` egasi polimorf ekani, sotuv qaysi locationdan chiqishi (3.3),
   `movement type` ning to'liq ro'yxati (3.4).
5. §7 ga **yangi band 7.21 — Moliyaviy hujjatlar o'zgarmas (storno qoidasi)** (3.13).
6. §8 — 3-bo'limdagi barcha yetishmayotgan ustun va jadvallar.
7. §13 ("nima qilmaslik kerak") ga qo'shimcha:
   - ❌ Moliyaviy hujjatni o'chirish yoki tahrirlash (storno o'rniga)
   - ❌ Tannarxni sotuv paytida "hozirgi kirim narxi" deb olish
   - ❌ Hisobot kunini kalendar sanasi bo'yicha olish (smena bo'yicha bo'lsin)
8. §16 ("hali ochiq") — 3.16 dagi hal qilinmagan savollar ko'chiriladi.
