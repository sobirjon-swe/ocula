# OPTIKA — MA'LUMOTLAR BAZASI SXEMASI

> `docs/PROJECT.md` §8 ning to'ldirilgan varianti.
> Bo'shliqlar `docs/ANALIZ.md` §3 bo'yicha yopilgan. Enum qiymatlari —
> `docs/ENUMS.md`. Sana: 2026-08-10
>
> **DBMS:** PostgreSQL 16+ (`pg_trgm`, `unaccent` kengaytmalari majburiy).

---

## 0. Umumiy konventsiyalar

| Mavzu | Qoida |
|---|---|
| Jadval nomi | `snake_case`, ko'plik: `stock_movements` |
| Birlamchi kalit | `bigIncrements` (`id`) |
| Tashqi kalit | `{jadval_birlik}_id` → `restrictOnDelete()` (hujjatlar), `cascadeOnDelete()` (faqat `*_items`) |
| Pul | `decimal(15,2)` — §15 #18. PHP tomonda `Money` + `bcmath` |
| Miqdor | `integer` (dona). Kasr miqdor optikada yo'q |
| Foiz | `decimal(5,2)` (0.00–100.00) |
| Vaqt | `timestampTz`, bazada **UTC** |
| Enum | `string(N)` + ilova darajasida `Rule::enum()`. DB `CHECK` yo'q |
| JSON | `jsonb`, kerak bo'lganda GIN indeks |
| Yumshoq o'chirish | Faqat **ma'lumotnomalarda** (`products`, `users`, `customers`). Hujjatlarda **yo'q** |
| Audit | `created_by` (`users.id`), `created_at`. Moliyaviy jadvallarda `updated_at` **yo'q** (7.21) |

### Insert-only jadvallar (7.21)

`stock_movements` · `stock_layers` · `stock_layer_consumptions` ·
`payments` · `cash_movements` · `supplier_transactions` · `bonus_entries`

- `updated_at` ustuni **yo'q**
- `DELETE` yo'q, `deleted_at` yo'q
- Tuzatish faqat **storno**: `reverses_id` (nullable, o'ziga FK) + `reason`
- Model darajasida `static::updating()` va `static::deleting()` hodisalarida
  `ImmutableRecordException` otiladi (`app/Support/Concerns/Immutable.php`)

---

## 1. Core

### `branches`
```
id                bigIncrements
name              string(120)
code              string(8)          unique   -- hujjat raqamida ishlatiladi (A, B, C…)
type              string(16)                  -- BranchType: main|shop
address           string(255) null
lat               decimal(10,7) null
lng               decimal(10,7) null
open_time         time null                   -- default 09:00
close_time        time null                   -- default 22:00
phone             string(32) null
is_active         boolean default true
created_at, updated_at
```
**Indeks:** `unique(code)`, `index(is_active)`

### `users` (xodimlar)
```
id                bigIncrements
name              string(120)
phone             string(32)         unique   -- asosiy login identifikatori
email             string(160) null   unique
password          string
branch_id         foreignId → branches null   -- ASOSIY filial (3.11)
debt_limit        decimal(15,2) default 0     -- 0 = qarz bera olmaydi (7.6)
locale            string(8) default 'uz-latn'
pin_hash          string null                 -- 4 xonali PIN, bcrypt (7.14)
pin_set_at        timestampTz null
is_active         boolean default true
last_login_at     timestampTz null
remember_token, created_at, updated_at, deleted_at
```
**Indeks:** `unique(phone)`, `unique(email)`, `index(branch_id, is_active)`

> `email` nullable — sotuvchi/haydovchida email bo'lmasligi mumkin. Login telefon bo'yicha.

### `branch_user` (pivot — 3.11)
```
branch_id         foreignId → branches   cascadeOnDelete
user_id           foreignId → users      cascadeOnDelete
primary key (branch_id, user_id)
```
> Xodim **qo'shimcha** kirish huquqiga ega filiallar. `users.branch_id` —
> asosiy filial, u ham `BranchScope` da hisobga olinadi.
> Bosqich 1 da bu jadval bo'sh bo'lishi mumkin.

### `locations`
```
id                bigIncrements
branch_id         foreignId → branches
type              string(16)                  -- LocationType
name              string(80)
owner_type        string(64) null             -- polimorf (3.2): User|Transfer
owner_id          bigInteger null
is_active         boolean default true
created_at, updated_at
```
**Indeks:** `index(branch_id, type)`, `index(owner_type, owner_id)`
```sql
-- Har filialda warehouse/floor bittadan (§15 #22)
CREATE UNIQUE INDEX locations_branch_singleton
  ON locations (branch_id, type)
  WHERE type IN ('warehouse','floor');
```

> **Transit egasi (3.2):** `own_driver` → `User`, `by_hand` → `User`,
> `taxi` → `Transfer` (7.10). Shuning uchun polimorf.
>
> **1-versiya (§15 #22):** har filialda **bitta `warehouse`**. Sotuv shundan
> chiqadi. `floor` yaratilmaydi.

### `devices`
```
id                bigIncrements
branch_id         foreignId → branches
name              string(80)
type              string(16)                  -- DeviceType
token             string(128)        unique   -- hashed
allowed_roles     jsonb                       -- ["doctor","master"]
last_seen_at      timestampTz null
is_active         boolean default true
created_at, updated_at
```

### `shifts`
```
id                bigIncrements
branch_id         foreignId → branches
opened_by         foreignId → users
closed_by         foreignId → users null
opened_at         timestampTz
closed_at         timestampTz null
opening_cash      decimal(15,2) default 0
expected_cash     decimal(15,2) null          -- hisoblangan
actual_cash       decimal(15,2) null          -- sanalgan
difference        decimal(15,2) null          -- actual − expected
status            string(16)                  -- ShiftStatus
note              text null
created_at, updated_at
```
**Indeks:** `index(branch_id, status)`, `index(branch_id, opened_at)`
```sql
-- Bitta filialda bir vaqtda faqat bitta ochiq smena
CREATE UNIQUE INDEX shifts_one_open_per_branch
  ON shifts (branch_id) WHERE status IN ('open','closing');
```

> Kunlik hisobot **shu jadval bo'yicha**, `orders.created_at` bo'yicha emas (§15 #24).

### `document_sequences` (3.12)
```
id                bigIncrements
branch_id         foreignId → branches
type              string(32)                  -- order|transfer|purchase|inventory|return
period            string(4)                   -- YYMM, masalan '2608'
last_number       integer default 0
created_at, updated_at
unique (branch_id, type, period)
```
> Format: `{BRANCH_CODE}-{YY}{MM}-{NNNNN}` → `A-2608-00147`.
> Generatsiya `SELECT ... FOR UPDATE` tranzaksiya ichida.

### `idempotency_keys` (3.7, §9)
```
id                bigIncrements
key               string(64)         unique   -- Idempotency-Key header
user_id           foreignId → users null
endpoint          string(160)
request_hash      string(64)                  -- sha256(body) — konflikt aniqlash
response_body     jsonb null
status_code       smallInteger null
locked_at         timestampTz null            -- parallel so'rovni to'sish
created_at        timestampTz
expires_at        timestampTz
```
**Indeks:** `unique(key)`, `index(expires_at)`

> Bir xil `key` + boshqa `request_hash` → **409 Conflict**.
> Amal qilish muddati: 24 soat, `model:prune` kunda bir marta.

### `settings`
```
id                bigIncrements
key               string(80)         unique
value             jsonb
updated_by        foreignId → users null
updated_at
```
> `min_prepayment_percent` (7.7), `money_rounding_step` (§15 #19),
> `prescription_validity_months` (default 12, 7.11),
> `debt_reminder_days` (`[-1, 0, 3, 7]`, 7.6).

---

## 2. Catalog

### `brands`
```
id, name string(120) unique, is_active boolean default true, timestamps
```

### `categories`
```
id, name string(120), parent_id foreignId → categories null,
sort integer default 0, is_active boolean default true, timestamps
unique (parent_id, name)
```

### `products`
```
id                bigIncrements
type              string(16)                  -- ProductType
name              string(200)
search_key        string(255)                 -- HOSILA (3.14) — quyida
brand_id          foreignId → brands null
category_id       foreignId → categories null
unit              string(16) default 'pcs'
status            string(16) default 'pending'-- ProductStatus (7.17)
quick_created     boolean default false       -- sotuv paytida yaratilgan (7.13)
merged_into_id    foreignId → products null   -- dublikat birlashtirilganda
created_by        foreignId → users
approved_by       foreignId → users null
approved_at       timestampTz null
is_active         boolean default true
timestamps, deleted_at
```
**Indeks:**
```sql
CREATE INDEX products_search_key_trgm ON products USING GIN (search_key gin_trgm_ops);
CREATE INDEX products_pending_idx     ON products (status) WHERE status = 'pending';
CREATE INDEX products_type_active_idx ON products (type, is_active);
```

> **`search_key` (3.14):** `name` → kichik harf → kirill→lotin transliteratsiya
> → diakritikasiz → bo'shliq/tire/nuqtasiz.
> `"Рэй Бан 3025"` → `reyban3025`, `"Ray-Ban 3025"` → `rayban3025`.
> Transliteratsiya qoidasi **bitta manbadan** — `app/Support/Transliterator.php`
> (frontend `uz-cyrl` bilan bir xil, §10).
> `search_key` model `saving` hodisasida yoziladi, DB trigger emas.

### `product_variants`
```
id                bigIncrements
product_id        foreignId → products   cascadeOnDelete
sku               string(64) null    unique
barcode           string(64) null    unique
attributes        jsonb default '{}'
sph               decimal(4,2) null
cyl               decimal(4,2) null
axis              smallInteger null           -- 0..180
add               decimal(4,2) null
index             decimal(3,2) null           -- LensIndex
coating           string(16) null             -- LensCoating
diameter          smallInteger null
color             string(40) null
size              string(40) null
is_active         boolean default true
timestamps
```
**Indeks:**
```sql
CREATE INDEX variants_product_idx ON product_variants (product_id);
CREATE INDEX variants_attrs_gin   ON product_variants USING GIN (attributes);
-- Linza kombinatsiyasi takrorlanmasin (7.2)
CREATE UNIQUE INDEX variants_lens_combo ON product_variants
  (product_id, sph, cyl, axis, "add", index, coating)
  WHERE sph IS NOT NULL;
```

### `prices`
```
id                bigIncrements
variant_id        foreignId → product_variants   cascadeOnDelete
branch_id         foreignId → branches null      -- null = global narx
price             decimal(15,2)
valid_from        timestampTz
valid_to          timestampTz null               -- 3.16: narx tarixi uchun
created_by        foreignId → users
created_at
```
**Indeks:** `index(variant_id, branch_id, valid_from)`

> **Qaysi narx yutadi (3.16):**
> 1. `branch_id = :branch`, `valid_from <= now()`, (`valid_to` null yoki `> now()`)
> 2. Topilmasa — `branch_id IS NULL` bilan xuddi shu shart
> 3. Ikkalasidan ham eng katta `valid_from`
>
> Yangi narx qo'yilganda avvalgisining `valid_to` avtomatik yopiladi.

### `services`
```
id, name string(120), price decimal(15,2), duration_min smallInteger null,
type string(16), is_active boolean default true, timestamps
```

---

## 3. Warehouse

### `stock_movements` — harakatlar daftari (7.1)
```
id                bigIncrements
branch_id         foreignId → branches        -- DENORMALIZATSIYA (3.10)
location_id       foreignId → locations
variant_id        foreignId → product_variants
type              string(20)                  -- MovementType
quantity          integer                     -- +/− (ishora `type` ga mos)
cost_total        decimal(15,2) default 0     -- FIFO dan hisoblangan (7.20)
cost_incomplete   boolean default false       -- qatlam yetmadi (7.20)
source_type       string(64) null             -- polimorf hujjat
source_id         bigInteger null
reverses_id       foreignId → stock_movements null   -- STORNO (7.21)
reason            string(255) null            -- storno va adjustment uchun majburiy
created_by        foreignId → users
created_at        timestampTz
```
**`updated_at` YO'Q. `deleted_at` YO'Q.**

**Indeks:**
```sql
CREATE INDEX sm_variant_location_idx ON stock_movements (variant_id, location_id, created_at);
CREATE INDEX sm_branch_created_idx   ON stock_movements (branch_id, created_at);
CREATE INDEX sm_source_idx           ON stock_movements (source_type, source_id);
CREATE INDEX sm_type_idx             ON stock_movements (type, created_at);
```

> **`branch_id` nega bor (3.10):** `BranchScope` har so'rovda `join locations`
> qilmasligi uchun. `transit` harakatida **jo'natuvchi filial** yoziladi.

### `stock_balances` — kesh (7.1)
```
location_id       foreignId → locations
variant_id        foreignId → product_variants
branch_id         foreignId → branches
quantity          integer default 0
updated_at        timestampTz
primary key (location_id, variant_id)
```
**Indeks:** `index(branch_id, variant_id)`, `index(variant_id) WHERE quantity > 0`

> **Faqat hosila.** `stock_movements` dan har doim qayta hisoblanadi:
> `php artisan stock:rebuild-balances`. Test majburiy (§14).

### `stock_layers` — FIFO qatlamlari (7.20) 🆕
```
id                bigIncrements
branch_id         foreignId → branches
location_id       foreignId → locations
variant_id        foreignId → product_variants
source_type       string(32)                  -- LayerSource
source_id         bigInteger null             -- purchase_item_id, transfer_item_id…
movement_id       foreignId → stock_movements -- qatlamni ochgan kirim
unit_cost         decimal(15,2)               -- birlik tannarxi
quantity_in       integer                     -- kelgan miqdor
quantity_remaining integer                    -- qolgan miqdor
received_at       timestampTz                 -- FIFO tartibi
created_at        timestampTz
```
**Indeks:**
```sql
-- FIFO tanlash uchun asosiy indeks
CREATE INDEX layers_fifo_idx ON stock_layers
  (variant_id, location_id, received_at, id) WHERE quantity_remaining > 0;
CREATE INDEX layers_movement_idx ON stock_layers (movement_id);
```
**Cheklov:** `CHECK (quantity_remaining >= 0 AND quantity_remaining <= quantity_in)`

> `quantity_remaining` — yagona o'zgaradigan ustun (bu jadval uchun 7.21 dagi
> "tahrirlanmaydi" qoidasidan istisno; sarflash tarixi
> `stock_layer_consumptions` da to'liq saqlanadi).
> Sarflash `SELECT ... FOR UPDATE` bilan, tranzaksiya ichida, `received_at, id`
> tartibida.

### `stock_layer_consumptions` — qatlam sarflashlari (7.20) 🆕
```
id                bigIncrements
layer_id          foreignId → stock_layers
movement_id       foreignId → stock_movements -- qatlamni sarflagan chiqim
quantity          integer                     -- + sarflandi, − storno qaytardi
unit_cost         decimal(15,2)               -- qatlamdan muzlatib olindi
total_cost        decimal(15,2)               -- quantity × unit_cost
created_at        timestampTz
```
**Indeks:** `index(movement_id)`, `index(layer_id)`

> `stock_movements.cost_total` = shu jadvaldagi `total_cost` yig'indisi.
> Storno qilinganda **manfiy** yozuv qo'shiladi va `layers.quantity_remaining`
> qaytariladi — asl yozuv o'chirilmaydi (7.21).

### `suppliers`
```
id, name string(160), phone string(32) null, payment_terms_days smallInteger default 0,
notes text null, is_active boolean default true, timestamps
```

### `purchases`
```
id                bigIncrements
supplier_id       foreignId → suppliers
branch_id         foreignId → branches
location_id       foreignId → locations       -- qaysi omborga kirdi
number            string(32)         unique
date              date
total             decimal(15,2) default 0
status            string(16)                  -- PurchaseStatus
received_at       timestampTz null
received_by       foreignId → users null
note              text null
created_by        foreignId → users
timestamps
```
**Indeks:** `unique(number)`, `index(supplier_id, date)`, `index(branch_id, status)`

### `purchase_items`
```
id                bigIncrements
purchase_id       foreignId → purchases   cascadeOnDelete
variant_id        foreignId → product_variants
quantity          integer
cost_price        decimal(15,2)               -- birlik tannarxi → qatlam `unit_cost`
total             decimal(15,2)
```

### `transfers`
```
id                bigIncrements
number            string(32)         unique
from_location_id  foreignId → locations
to_location_id    foreignId → locations
transit_location_id foreignId → locations null -- yo'ldagi joy (3.2)
status            string(24)                  -- TransferStatus
delivery_method   string(16)                  -- DeliveryMethod (7.10)
driver_id         foreignId → users null
carrier_user_id   foreignId → users null      -- by_hand uchun
taxi_cost         decimal(15,2) null          -- taxi da MAJBURIY
taxi_receipt_path string(255) null
trip_id           foreignId → trips null
sent_by           foreignId → users null
sent_at           timestampTz null
received_by       foreignId → users null
received_at       timestampTz null
has_discrepancy   boolean default false       -- 7.4
note              text null
created_by        foreignId → users
timestamps
```
**Indeks:** `unique(number)`, `index(status)`, `index(from_location_id, status)`,
`index(to_location_id, status)`, `index(trip_id)`

### `transfer_items`
```
id, transfer_id foreignId → transfers cascadeOnDelete,
variant_id foreignId → product_variants,
qty_sent integer, qty_received integer null,
unit_cost decimal(15,2) null                  -- chiqimda FIFO dan hisoblangan
```

### `stock_requests`
```
id                bigIncrements
from_branch_id    foreignId → branches        -- so'ragan filial
to_branch_id      foreignId → branches        -- so'ralayotgan filial
variant_id        foreignId → product_variants
quantity          integer
order_id          foreignId → orders null
status            string(16)                  -- StockRequestStatus
transfer_id       foreignId → transfers null
requested_by      foreignId → users
approved_by       foreignId → users null
timestamps
```

### `inventories`
```
id, branch_id foreignId → branches, location_id foreignId → locations,
number string(32) unique, status string(16),
started_at timestampTz null, finished_at timestampTz null,
created_by foreignId → users, approved_by foreignId → users null, timestamps
```

### `inventory_items`
```
id, inventory_id foreignId → inventories cascadeOnDelete,
variant_id foreignId → product_variants,
expected_qty integer, actual_qty integer null,
difference integer null, cost_impact decimal(15,2) null,
counted_by foreignId → users null, counted_at timestampTz null
unique (inventory_id, variant_id)
```

### `defects`
```
id                bigIncrements
branch_id         foreignId → branches
location_id       foreignId → locations
variant_id        foreignId → product_variants
quantity          integer
reason            string(24)                  -- DefectReason (7.5)
order_id          foreignId → orders null
work_order_id     foreignId → work_orders null
transfer_id       foreignId → transfers null  -- transport_damage uchun
cost_impact       decimal(15,2) default 0     -- FIFO dan (7.20)
movement_id       foreignId → stock_movements null
photo_path        string(255) null
note              text null
reported_by       foreignId → users
approved_by       foreignId → users null
timestamps
```
**Indeks:** `index(branch_id, reason, created_at)`, `index(reported_by)`

### `lost_sales` (7.9, 3.8)
```
id                bigIncrements
branch_id         foreignId → branches
variant_id        foreignId → product_variants null   -- NULLABLE (3.8)
search_term       string(160) null            -- katalogda umuman yo'q bo'lsa
customer_id       foreignId → customers null
reason            string(24)                  -- out_of_stock | not_in_catalog | price
created_by        foreignId → users
created_at        timestampTz
```
**Cheklov:** `CHECK (variant_id IS NOT NULL OR search_term IS NOT NULL)`

> `search_term` — **analitik log**, ombor yozuvi emas. Shuning uchun 7.13 dagi
> "erkin matn ishlatilmaydi" qoidasiga zid emas (3.8).

---

## 4. Sales

### `customers`
```
id                bigIncrements
name              string(160)
phone             string(32)         unique
birth_date        date null
telegram_id       bigInteger null    unique
locale            string(8) default 'uz-latn'
notes             text null
debt_balance      decimal(15,2) default 0     -- kesh, `debts` dan hisoblanadi
first_visit_at    timestampTz null
abandoned_orders_count integer default 0      -- 7.7
branch_id         foreignId → branches null   -- birinchi kelgan filial
created_by        foreignId → users null
timestamps, deleted_at
```
**Indeks:** `unique(phone)`, `index(telegram_id)`, GIN trgm `(name)`

### `orders`
```
id                bigIncrements
number            string(32)         unique
branch_id         foreignId → branches
customer_id       foreignId → customers null  -- tez savdoda bo'lmasligi mumkin
shift_id          foreignId → shifts null
type              string(8)                   -- OrderType
status            string(24)                  -- OrderStatus
payment_status    string(16)                  -- PaymentStatus
prescription_id   foreignId → prescriptions null
subtotal          decimal(15,2) default 0
discount          decimal(15,2) default 0
rounding          decimal(15,2) default 0     -- §15 #19
total             decimal(15,2) default 0
paid              decimal(15,2) default 0
debt              decimal(15,2) default 0
cost_total        decimal(15,2) default 0     -- COGS, FIFO dan (7.20)
due_date          date null
delivery_type     string(16) default 'pickup'
status_changed_at timestampTz null
revenue_recognized_at timestampTz null        -- 3.5 — DAROMAD SANASI
delivered_at      timestampTz null
abandoned_at      timestampTz null            -- 7.7
cancelled_at      timestampTz null
discount_approved_by foreignId → users null
created_by        foreignId → users
timestamps
```
**Indeks:**
```sql
CREATE UNIQUE INDEX orders_number_idx  ON orders (number);
CREATE INDEX orders_branch_status_idx  ON orders (branch_id, status);
CREATE INDEX orders_obligation_idx     ON orders (status, payment_status);  -- 3.5
CREATE INDEX orders_revenue_idx        ON orders (revenue_recognized_at);
CREATE INDEX orders_customer_idx       ON orders (customer_id, created_at);
CREATE INDEX orders_shift_idx          ON orders (shift_id);
```

> **`revenue_recognized_at` (3.5):**
> - `type = quick` → chek yopilgan payt
> - `type = order` → `delivered` ga o'tgan payt
>
> Foyda hisoboti (7.8-B) **shu ustun** bo'yicha, `created_at` bo'yicha emas.
>
> **"Bajarilmagan buyurtmalar majburiyati"** (7.8):
> `SUM(paid) WHERE status NOT IN ('delivered','closed','cancelled')` —
> hosila, jadval kerak emas, `orders_obligation_idx` yetarli.

### `order_items`
```
id                bigIncrements
order_id          foreignId → orders   cascadeOnDelete
itemable_type     string(64)                  -- ProductVariant | Service
itemable_id       bigInteger
quantity          integer
price             decimal(15,2)               -- birlik narxi
discount          decimal(15,2) default 0
total             decimal(15,2)
cost_total        decimal(15,2) default 0     -- COGS (7.20)
cost_source       string(16) default 'fifo'   -- fifo | manual | none
purchase_item_id  foreignId → purchase_items null   -- 3.9
custom_lens_params jsonb null                 -- individual linza (7.13)
movement_id       foreignId → stock_movements null
created_at
```
**Indeks:** `index(order_id)`, `index(itemable_type, itemable_id)`

> **Individual linza tannarxi (3.9):** ombordan o'tmaydi → `cost_source = manual`
> va `cost_total` qo'lda kiritiladi, yoki `purchase_item_id` orqali bog'lanadi.
> `cost_source = none` bo'lsa hisobotda **"tannarxsiz sotuv"** deb ajratiladi —
> aks holda foyda soxta yuqori, mukofot ham noto'g'ri chiqadi (7.12).

### `payments`
```
id                bigIncrements
order_id          foreignId → orders null
customer_id       foreignId → customers null
branch_id         foreignId → branches
shift_id          foreignId → shifts null
amount            decimal(15,2)               -- +/− (qaytarishda manfiy)
method            string(16)                  -- PaymentMethod
status            string(16)                  -- PaymentTxStatus
reverses_id       foreignId → payments null   -- STORNO (7.21)
reason            string(255) null
received_by       foreignId → users
collected_by      foreignId → users null      -- haydovchi (7.4)
paid_at           timestampTz
created_at        timestampTz
```
**`updated_at` YO'Q.**
**Indeks:** `index(order_id)`, `index(customer_id, paid_at)`, `index(shift_id)`,
`index(branch_id, paid_at)`

### `returns`
```
id                bigIncrements
number            string(32)         unique
order_id          foreignId → orders
branch_id         foreignId → branches
shift_id          foreignId → shifts null
reason            string(32)                  -- ReturnReason
amount            decimal(15,2)               -- qaytarilgan pul
cost_total        decimal(15,2) default 0     -- qaytgan tovar tannarxi
approved_by       foreignId → users null
created_by        foreignId → users
timestamps
```

### `return_items` (3.6) 🆕
```
id                bigIncrements
return_id         foreignId → returns   cascadeOnDelete
order_item_id     foreignId → order_items
variant_id        foreignId → product_variants null   -- xizmat qaytsa null
quantity          integer
amount            decimal(15,2)
cost_total        decimal(15,2) default 0
restock           boolean default true        -- omborga qaytadimi yoki brakka
movement_id       foreignId → stock_movements null
```
> `restock = true` → `stock_movements(type=return)` + yangi FIFO qatlami
> (sotilgan tannarx bilan). `false` → `defects` yoziladi.

---

## 5. Clinic

### `visits`
```
id                bigIncrements
branch_id         foreignId → branches
customer_id       foreignId → customers
doctor_id         foreignId → users null
queue_number      smallInteger
status            string(16)                  -- VisitStatus
source            string(16)                  -- VisitSource
started_at        timestampTz null
finished_at       timestampTz null
created_by        foreignId → users null
timestamps
```
**Indeks:** `index(branch_id, status)`, `index(customer_id)`,
`unique(branch_id, queue_date, queue_number)` (`queue_date` — hosila `date` ustuni)

### `prescriptions`
```
id                bigIncrements
visit_id          foreignId → visits null
customer_id       foreignId → customers
branch_id         foreignId → branches        -- yozilgan filial
doctor_id         foreignId → users
od_sph, od_cyl    decimal(4,2) null
od_axis           smallInteger null
od_add            decimal(4,2) null
os_sph, os_cyl    decimal(4,2) null
os_axis           smallInteger null
os_add            decimal(4,2) null
pd                decimal(4,1) null
pd_near           decimal(4,1) null
prism             string(40) null
notes             text null
valid_until       date                        -- default +12 oy (7.11)
ticket_active     boolean default true        -- faol tiket (7.11)
ticket_branch_id  foreignId → branches        -- tiket qaysi filialda ochiq
timestamps
```
**Indeks:** `index(customer_id, created_at)`,
`index(ticket_branch_id, ticket_active) WHERE ticket_active`

> **7.11 kritik qoida:** faol tiket faqat `ticket_branch_id` filialida ko'rinadi.
> Retsept **tarixi** (`customer_id` bo'yicha) barcha filiallarda o'qiladi.

### `prescription_transfers` 🆕 (7.11)
```
id, prescription_id foreignId → prescriptions, from_branch_id, to_branch_id,
reason string(255) NOT NULL, created_by foreignId → users, created_at
```

---

## 6. Workshop

### `work_orders`
```
id, order_id foreignId → orders, branch_id foreignId → branches,
master_id foreignId → users null, status string(16),
priority string(8) default 'normal',
due_at timestampTz null, started_at timestampTz null, finished_at timestampTz null,
rework_count smallInteger default 0, note text null,
created_by foreignId → users, timestamps
```
**Indeks:** `index(branch_id, status, priority)`, `index(master_id, status)`

### `work_order_items`
```
id, work_order_id foreignId → work_orders cascadeOnDelete,
variant_id foreignId → product_variants, quantity integer,
consumed_movement_id foreignId → stock_movements null   -- `consume` (3.4)
```

---

## 7. Delivery

### `trips`
```
id, driver_id foreignId → users, date date, status string(16),
started_at timestampTz null, finished_at timestampTz null,
cash_collected decimal(15,2) default 0,
created_by foreignId → users, timestamps
unique (driver_id, date)
```

### `trip_stops`
```
id                bigIncrements
trip_id           foreignId → trips   cascadeOnDelete
sequence          smallInteger
type              string(16)                  -- TripStopType
branch_id         foreignId → branches null
customer_id       foreignId → customers null
order_id          foreignId → orders null
transfer_id       foreignId → transfers null
address           string(255) null
lat, lng          decimal(10,7) null
cash_to_collect   decimal(15,2) default 0
status            string(16)                  -- TripStopStatus
delivered_at      timestampTz null
delivered_lat, delivered_lng decimal(10,7) null
distance_m        integer null                -- manzildan farq (7.4, 500 m)
photo_path        string(255) null
customer_confirmed_at timestampTz null
confirmation_status string(16) null           -- ConfirmationStatus
fail_reason       string(255) null
timestamps
```

### `driver_balances`
```
driver_id         foreignId → users   primary
cash_amount       decimal(15,2) default 0
updated_at
```
> Hosila: `SUM(collected) − SUM(handed over)`. Test majburiy (§14).

### `collections`
```
id, driver_id foreignId → users, branch_id foreignId → branches,
shift_id foreignId → shifts null, amount decimal(15,2),
received_by foreignId → users, note text null, created_at
```

---

## 8. Finance

### `cash_movements`
```
id                bigIncrements
branch_id         foreignId → branches
shift_id          foreignId → shifts null
type              string(4)                   -- CashDirection: in|out
category          string(24)                  -- CashCategory
amount            decimal(15,2)               -- har doim musbat, yo'nalish `type` da
source_type       string(64) null
source_id         bigInteger null
reverses_id       foreignId → cash_movements null   -- STORNO (7.21)
reason            string(255) null
description       string(255) null
created_by        foreignId → users
created_at        timestampTz
```
**`updated_at` YO'Q.**
**Indeks:** `index(branch_id, created_at)`, `index(shift_id)`,
`index(category, created_at)`, `index(source_type, source_id)`

### `expense_categories`
```
id, name string(120) unique, code string(32) unique,
is_active boolean default true, timestamps
```

### `expenses`
```
id, branch_id foreignId → branches, category_id foreignId → expense_categories,
amount decimal(15,2), date date, description string(255) null,
source_type string(64) null, source_id bigInteger null,   -- 7.10: Transfer
receipt_path string(255) null,
approved_by foreignId → users null, created_by foreignId → users, timestamps
```
**Indeks:** `index(branch_id, date)`, `index(source_type, source_id)`

> **7.10:** taksi xarajati **avtomatik** `source_type = Transfer` bilan
> yoziladi, kategoriya `transport`. Bog'lanmagan taksi xarajati yozilmaydi.

### `debts`
```
id, customer_id foreignId → customers, order_id foreignId → orders null,
branch_id foreignId → branches,
amount decimal(15,2), paid decimal(15,2) default 0,
due_date date, status string(16), closed_at timestampTz null,
approved_by foreignId → users null, created_by foreignId → users, timestamps
```
**Indeks:** `index(customer_id, status)`, `index(due_date, status)`,
`index(branch_id, status)`

### `debt_reminders`
```
id, debt_id foreignId → debts cascadeOnDelete, sent_at timestampTz,
channel string(16), response string(16) default 'none',
responded_at timestampTz null, note text null
```

### `supplier_transactions` (7.15)
```
id                bigIncrements
supplier_id       foreignId → suppliers
type              string(16)                  -- SupplierTxType
amount            decimal(15,2)               -- +/− (7.15)
purchase_id       foreignId → purchases null
payment_id        bigInteger null
due_date          date null
reverses_id       foreignId → supplier_transactions null
reason            string(255) null
created_by        foreignId → users
created_at        timestampTz
```
**`updated_at` YO'Q.**
**Indeks:** `index(supplier_id, created_at)`, `index(due_date)`

> Balans = `SUM(amount)`. `+` = avans, `−` = biz qarzdormiz.

---

## 9. Payroll

### `bonus_rules`
```
id, user_id foreignId → users null, role string(32) null,
branch_id foreignId → branches null,
base string(16), percent decimal(5,2),
valid_from date, valid_to date null,
created_by foreignId → users, timestamps
```
**Cheklov:** `CHECK (user_id IS NOT NULL OR role IS NOT NULL)`
> `user_id` bo'lgan qoida rol qoidasidan **ustun** (7.19).

### `bonus_entries`
```
id, user_id foreignId → users, order_id foreignId → orders null,
rule_id foreignId → bonus_rules null,
period string(7),                             -- YYYY-MM
base_amount decimal(15,2), percent decimal(5,2), amount decimal(15,2),
status string(16), reverses_id foreignId → bonus_entries null,
calculated_at timestampTz, created_at timestampTz
```
**`updated_at` YO'Q** (insert-only).
**Indeks:** `index(user_id, period)`, `index(order_id)`,
`unique(order_id, user_id, rule_id) WHERE reverses_id IS NULL`

### `branch_plans`
```
id, branch_id foreignId → branches, period string(7),
type string(16), target_amount decimal(15,2),
created_by foreignId → users, timestamps
unique (branch_id, period, type)
```

---

## 10. Tashqi paketlar jadvallari

| Jadval | Manba |
|---|---|
| `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | `spatie/laravel-permission` |
| `activity_log` | `spatie/laravel-activitylog` |
| `personal_access_tokens` | `laravel/sanctum` |
| `cache`, `cache_locks` | Laravel |
| `jobs`, `job_batches`, `failed_jobs` | Laravel queue |
| `sessions` | Laravel |

---

## 11. PostgreSQL kengaytmalari

Birinchi migratsiyada:
```sql
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS unaccent;
```
> `pg_trgm` — fuzzy qidiruv (7.13, 3.14). `unaccent` — diakritikani olib
> tashlash. Ikkalasi ham `search_key` GIN indeksida ishlatiladi.

---

## 12. Migratsiya tartibi (Bosqich 1)

```
0001  create_extensions                     -- pg_trgm, unaccent
0002  create_branches_table
0003  update_users_table                    -- branch_id, debt_limit, locale, pin_hash…
0004  create_branch_user_table
0005  create_locations_table
0006  create_devices_table
0007  create_shifts_table
0008  create_document_sequences_table
0009  create_idempotency_keys_table
0010  create_settings_table
0011  create_permission_tables              -- spatie
0012  create_activity_log_table             -- spatie
0013  create_personal_access_tokens_table   -- sanctum
0014  create_brands_table
0015  create_categories_table
0016  create_products_table                 -- + GIN trgm indeks
0017  create_product_variants_table
0018  create_prices_table
0019  create_services_table
```

Bosqich 2 (ombor) dan boshlab `stock_*` jadvallari, keyin §11 roadmap bo'yicha.

---

## 13. Yopilgan bo'shliqlar — `ANALIZ.md` §3 xaritasi

| ANALIZ | Yechim |
|---|---|
| 3.1 Enumlar | `docs/ENUMS.md` |
| 3.2 Transit egasi | `locations.owner_type` + `owner_id` (polimorf) |
| 3.3 Sotuv location'i | Har filialda bitta `warehouse` (partial unique index) |
| 3.4 `movement.type` | `consume`, `write_off`, `merge` qo'shildi — `ENUMS.md` §3 |
| 3.5 Daromad sanasi | `orders.revenue_recognized_at` + `orders_obligation_idx` |
| 3.6 `return_items` | Yaratildi |
| 3.7 `idempotency_keys` | Yaratildi (`request_hash`, `locked_at` bilan) |
| 3.8 `lost_sales` | `variant_id` nullable + `search_term` + CHECK |
| 3.9 Individual linza tannarxi | `order_items.cost_source` + `purchase_item_id` |
| 3.10 BranchScope | `stock_movements.branch_id` denormalizatsiya |
| 3.11 Ko'p filialli xodim | `branch_user` pivot |
| 3.12 Hujjat raqami | `document_sequences` |
| 3.13 O'zgarmaslik | `reverses_id` + `updated_at` yo'q + §0 dagi ro'yxat |
| 3.14 Fuzzy qidiruv | `products.search_key` + GIN trgm + `Transliterator` |
| 3.15 Ruxsatlar | `docs/PERMISSIONS.md` |
| 3.16 Vaqt zonasi | Hisobot `shifts` bo'yicha (§15 #24) |
| 3.16 Narx tanlash | `prices.valid_to` + tanlash qoidasi (§2) |
| COGS (2.2) | `stock_layers` + `stock_layer_consumptions` (FIFO) |
