# BOSQICH 10 — MOLIYA, MUKOFOT VA ANALITIKA

> Manba: `docs/PROJECT.md` §11 (Bosqich 10), §6.8, §6.10, §6.11, 7.6, 7.7,
> 7.8, 7.9, 7.12, 7.15, 7.18, 7.19; `docs/SCHEMA.md` (`expenses`,
> `debts`, `debt_reminders`, `supplier_transactions`, `bonus_rules`,
> `bonus_entries`, `branch_plans`); `docs/ENUMS.md` §8, §9;
> `docs/PERMISSIONS.md` §8-10; `docs/BOSQICH-4.md` §5 #4 (taksi
> xarajati qarzi), `docs/BOSQICH-6.md` §5 #6 (`supplier_defect` qarzi),
> `docs/BOSQICH-7.md` §5 #2 (`telegram_notifications` vs `debt_reminders`).
> Sana: 2026-08-17.
>
> **Bu bosqich uch qismga bo'lib qurildi** (10a Moliya, 10b Mukofot,
> 10c Analitika) — har biri alohida commit, chunki birgalikda bitta
> bosqich sifatida hujjatlashtirilgan bo'lsa ham hajmi Warehouse yoki
> Sales darajasida katta.

---

## 10a — Moliya (Finance): xarajatlar, qarz registri, yetkazib beruvchi

**Holat: bajarilgan va yashil.**

### Hajm

**Kiradi:** xarajat kategoriyalari + xarajatlar (kassaga avtomatik
tegadi), to'liq `debts`/`debt_reminders` registri (Order'dan avtomatik
sinxronlanadi), yetkazib beruvchi ikki tomonlama hisobi
(`supplier_transactions`), taksi xarajatining `expenses`ga avtomatik
bog'lanishi (BOSQICH-4.md qarzi — **yopildi**, faqat yangi transferlar
uchun, tarixiy backfill yo'q — loyihada hali production ma'lumoti yo'q).

**Kirmaydi:** `supplier_defect` qaytarish aktining avtomatik bog'lanishi
(BOSQICH-6.md qarzi) — `defects` jadvalida qaysi yetkazib beruvchi/kirim
hujjatidan kelib chiqqanini bildiruvchi ustun yo'q (`purchase_id` yoki
`supplier_id`), buni qo'shish Warehouse sxemasiga yana bir o'zgarish
talab qiladi. **Bu hamon qarz** — endi aniq sabab bilan: bog'lash uchun
ma'lumot yetishmayapti, funksiya emas.

`sales.customer.debt.grant`/`finance.debt.approve` — sotuv paytida
qarz limitini **majburiy tekshirish** ham kirmaydi: bu `Sales\CreateOrder`
ni o'zgartirishni talab qiladi (Bosqich 3'dan beri sinovdan o'tgan,
katta amal). Ruxsatlar tayyor turibdi, tekshiruv keyingi ish.

### Migratsiyalar

| Fayl | Jadvallar |
|---|---|
| `0001_01_01_000460_create_expenses_tables.php` | `expense_categories`, `expenses` |
| `0001_01_01_000470_create_debts_table.php` | `debts`, `debt_reminders` |
| `0001_01_01_000480_create_supplier_transactions_table.php` | `supplier_transactions` |
| `0001_01_01_000490_add_write_off_reason_to_debts_table.php` | `debts.write_off_reason` — hisobdan chiqarish sababi (`POST /debts/{id}/write-off` uchun) |

### Qarz registrining avtomatik sinxronlanishi

`Debt` qo'lda ochilmaydi — `DebtRegistry::syncForOrder()` `OrderBalance::refresh()`
oxirida chaqiriladi (huddi ish buyrug'i buyurtma holatidan tug'ilgani
kabi, Bosqich 6):

```
RecordPayment / DeliverOrder / CreateReturn
  → OrderBalance::refresh()  (paid/debt/payment_status qayta hisoblanadi)
  → DebtRegistry::syncForOrder($order)
       order.debt > 0  → Debt qatori yaratiladi/yangilanadi
       order.debt == 0 → ochiq Debt bo'lsa → paid + closed_at
```

Holat (`open`/`overdue`/`doubtful`) **vaqtga bog'liq** — to'lov
hodisasisiz ham o'zgarishi kerak (bugun ochiq bo'lgan qarz ertaga muddati
o'tgan bo'lib qolishi mumkin). Shuning uchun kunlik
`finance:refresh-debt-statuses` buyrug'i qo'shildi — bu bosqichdagi
ikkinchi scheduler yozuvi (birinchisi Bosqich 7'da).

### Telegram eslatmasi bilan yarashish (BOSQICH-7.md §5 #2)

`telegram:remind-debts` endi `orders.debt` o'rniga **`debts`** jadvalidan
o'qiydi — dedupe mantig'i o'zgarmadi (`telegram_notifications.dedupe_key`),
lekin yuborilgach endi **`debt_reminders`** ga ham yozadi
(`channel=telegram`) — bu CRM jurnali, xodim keyin qo'lda ham
eslatma qo'shishi mumkin (`POST /debts/{id}/remind`, javobni
belgilash bilan: va'da qildi/rad etdi/bahslashdi/to'ladi).

`telegram_notifications` **o'zgarishsiz qoladi** — u hamon "yuborildimi"
degan texnik savolga javob beradi. `debt_reminders` esa "nima bo'ldi"
degan CRM savoliga.

### Yetkazib beruvchi — ikki tomonlama hisob (7.15)

`SupplierLedger` — `CashRegister`/`StockLedger` bilan bir xil naqsh:
yagona yozuvchi joy, balans yig'indidan hisoblanadi.

```
ReceivePurchase (Warehouse) → SupplierLedger::record(purchase, −total)
   "Tovar keldi, to'lanmadi" → biz qarzdormiz (7.15 jadvali)

Finance: POST /suppliers/{id}/payments → SupplierLedger::record(payment, +amount)
   Kassadan chiqim ham yoziladi (CashCategory::SupplierPayment)
```

Balans musbat = yetkazib beruvchi tovar qarzdor (avans), manfiy = biz
pul qarzdormiz — 7.15 jadvaliga mos.

### Taksi xarajati (BOSQICH-4.md §5 #4 — yopildi)

`SendTransfer` (Warehouse) endi `delivery_method = taxi` bo'lganda
`CreateExpense` ni ham chaqiradi: `category = transport`,
`source = Transfer`, filial — **jo'natuvchi** (u taksi chaqirgan).
Kassadan avtomatik chiqim yoziladi (`CashCategory::Expense`).

### API (yangi)

```
GET    /expense-categories            POST /expense-categories
GET    /expenses                      POST /expenses
POST   /expenses/{id}/approve

GET    /debts                         GET  /debts/{id}
POST   /debts/{id}/write-off
POST   /debts/{id}/remind             — qo'lda eslatma (CRM)

GET    /suppliers/{id}/balance
GET    /supplier-transactions
POST   /supplier-transactions         — qo'lda to'lov/tuzatish
```

### Testlar

| Fayl | Kafolat |
|---|---|
| `Feature/Finance/ExpenseApiTest.php` | Xarajat yaratilganda kassadan chiqim yoziladi; tasdiqlash `approved_by` ni belgilaydi |
| `Feature/Finance/DebtRegistryTest.php` | To'lov qarzni kamaytiradi/yopadi; muddat o'tgach `overdue`, 90+ kunda `doubtful` bo'ladi (`finance:refresh-debt-statuses`) |
| `Feature/Finance/SupplierLedgerTest.php` | Kirim balansni kamaytiradi (biz qarzdor), to'lov balansni oshiradi; kassadan chiqim yoziladi |
| `Feature/Warehouse/TransferApiTest.php` (qo'shimcha) | Taksi bilan jo'natish avtomatik xarajat yaratadi |
| `Feature/Telegram/DebtReminderCommandTest.php` (yangilangan) | Endi `debts`dan o'qiydi, `debt_reminders` ga ham yozadi |

---

## 10b — Mukofot (Payroll)

**Holat: bajarilgan va yashil.**

### Hajm

**Kiradi:** `bonus_rules` (shaxsiy foiz rol qoidasidan ustun, 7.19),
`bonus_entries` (buyurtma yopilganda avtomatik hisoblanadi, 7.12),
`branch_plans` (oylik filial rejasi, 7.18), qaytarishda mukofotning
storno qilinishi, mukofotni tasdiqlash/to'lash (kassaga chiqim bilan).

**Kirmaydi:** filial reytingini ko'rsatadigan API (bu Bosqich 10c
Analitika ishi — reja shu yerda faqat yoziladi/o'qiladi, hisobot
alohida bosqichda).

### Migratsiyalar

| Fayl | Jadvallar |
|---|---|
| `0001_01_01_000500_create_bonus_rules_table.php` | `bonus_rules` |
| `0001_01_01_000510_create_bonus_entries_table.php` | `bonus_entries` |
| `0001_01_01_000520_create_branch_plans_table.php` | `branch_plans` |

### Mukofot hisoblash — qachon va kim uchun (7.12)

`OrderBalance::refresh()` buyurtmani `closed` ga o'tkazganda (Bosqich 6
ish buyrug'i tug'ilishi bilan bir xil naqsh) `BonusAccrual::accrueForOrder()`
chaqiriladi:

- **Sotuvchi** — `orders.created_by` (rol qoidasi yoki shaxsiy qoida,
  `base=revenue|profit`).
- **Shifokor** — `orders.prescription_id → prescriptions.doctor_id`
  (bor bo'lsa).
- **Usta** — buyurtmaga tegishli `work_orders.master_id` (bor va
  `status=done` bo'lsa), `base=count`.

Har biri uchun `BonusRuleResolver` mos qoidani topadi: avval
`bonus_rules.user_id` (shaxsiy), topilmasa `bonus_rules.role` +
filialga mos (`branch_id` = xodim filiali yoki `NULL` — filialsiz
qoida tarmoq bo'yicha umumiy, filialli qoida ustun turadi), faqat
`valid_from..valid_to` oralig'ida joriy bo'lgani.

Yozuv **idempotent**: `unique(order_id, user_id, rule_id) WHERE reverses_id
IS NULL` — buyurtma ikki marta "yopilib" qolsa ham mukofot ikki marta
yozilmaydi (bazaviy cheklov, ilova darajasida ham `firstOrCreate`
mantig'i bilan qo'llab-quvvatlanadi).

**`base=count` (usta) haqida izoh:** `bonus_rules.percent` ustuni
`decimal(5,2)` — haqiqiy foiz sifatida saqlanadi, lekin usta uchun
"foiz"ning tabiiy pul ma'nosi yo'q (ish soni pul emas). Shuning uchun
`count` bazasida `base_amount` = davr ichida usta yakunlagan (`done`)
ish buyruqlari soni **minus** o'sha davrda unga bog'langan
`master_error` braklari soni, `amount = percent% × base_amount` —
natija **koeffitsient**, aniq to'lov summasi emas. Direktor
`payroll.bonus.approve`/`pay` bosqichida yakuniy summani real oylik
siyosatga qarab belgilaydi (tizim faqat hisoblash asosini beradi —
PROJECT.md bu yerda pul stavkasini belgilamaydi).

### Qaytarish — mukofot storno qilinadi (7.12)

`CreateReturn` (Sales) qaytarish yozgandan keyin `BonusAccrual::reverseForOrder()`
ni chaqiradi: shu buyurtma bo'yicha **hali to'lanmagan** (`accrued`/`approved`,
`paid` emas) barcha faol yozuvlar uchun teskari yozuv qo'yiladi
(`status=reversed`, `reverses_id`). **Soddalashtirish:** qisman
qaytarishda ham butun yozuv storno qilinadi (proportsional emas) —
buyurtmaning qaysi qismi qaytarilgani bo'yicha mukofotni qisman
qaytarish keyingi ish, hozircha "qaytarish bo'lsa — ayiriladi" talabi
to'liq storno bilan qondiriladi. Mukofot **allaqachon to'langan**
(`status=paid`) bo'lsa, storno qilinmaydi — bu moliyaviy operatsiya
qaytarilmaydi, hisobot ustida qo'lda tuzatiladi.

### Filial rejalari (7.18)

`branch_plans` qo'lda kiritiladi (`payroll.plan.manage`, faqat
direktor) — reyting/progress hisoboti Bosqich 10c'da. Bu yerda faqat
CRUD.

### API (yangi)

```
GET    /bonus-rules                   POST /bonus-rules
GET    /bonus-entries                 — filtrlash: user_id, period, status
POST   /bonus-entries/{id}/approve
POST   /bonus-entries/{id}/pay        — kassadan chiqim (bonus_payout)

GET    /branch-plans                  POST /branch-plans
```

### Testlar

| Fayl | Kafolat |
|---|---|
| `Feature/Payroll/BonusRuleResolverTest.php` | Shaxsiy qoida rol qoidasidan ustun; muddati o'tgan qoida ishlatilmaydi |
| `Feature/Payroll/BonusAccrualTest.php` | Buyurtma yopilganda sotuvchi/shifokor/usta uchun yoziladi; ikki marta yozilmaydi; qaytarishda storno bo'ladi |
| `Feature/Payroll/BonusApiTest.php` | To'lash kassadan chiqim yozadi; ruxsatlar (`view_own` hammada, `pay` faqat direktor/buxgalter) |
| `Feature/Payroll/BranchPlanApiTest.php` | Faqat direktor reja qo'ya oladi |

## 10c — Analitika (Analytics)

*(keyingi commit)*
