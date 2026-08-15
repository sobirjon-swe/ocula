# BOSQICH 4 — TRANSFER (bajarilgan ish)

> Manba: `docs/PROJECT.md` §11 (Bosqich 4), 7.4, 7.10; `docs/SCHEMA.md` §3;
> `docs/ENUMS.md` §3; `docs/PERMISSIONS.md` §3; `docs/ANALIZ.md` 3.2, 3.10.
> Sana: 2026-08-15. Holat: **bajarilgan va yashil**.
>
> Bu fayl `BOSQICH-3.md` bilan bir xil maqsadda: hujjatlarni qaytadan
> titkilamasdan ishni davom ettirish. Qarama-qarshilik chiqsa —
> `PROJECT.md`/`SCHEMA.md` yutadi, bu fayl emas.

---

## 1. Hajm

**Kiradi:** ikki bosqichli transfer (jo'natish → transit → qabul qilish),
yetkazish usullari (7.10), nomuvofiqlik (7.4), ichki so'rov.

**Kirmaydi:** inventarizatsiya va brak (`inventories`, `defects` —
Bosqich 6), haydovchi reysi (`trips` — Bosqich 8), taksi xarajatining
`expenses` yozuvi (Finance bosqichi — quyida §5 #4).

---

## 2. Migratsiyalar

| Fayl | Jadvallar |
|---|---|
| `0001_01_01_000350_create_transfers_table.php` | `transfers`, `transfer_items` |
| `0001_01_01_000360_create_stock_requests_table.php` | `stock_requests` |

`transfers.trip_id` **tashqi kalitsiz** — `trips` Bosqich 8 da keladi
(`orders.prescription_id` bilan bir xil sabab).

---

## 3. Tovar qanday harakatlanadi

Butun bosqichning mag'zi shu: **tovar yo'lda yo'qolmaydi**.

```
draft        tovar jo'natuvchi omborda, hujjat qog'ozda
  ↓ send
sent         TransferOut (jo'natuvchi) + TransferIn (transit)
  ↓ receive
received     TransferOut (transit) + TransferIn (qabul qiluvchi)
             kam kelgan qism → WriteOff (transit)
```

Har uch holatda ham umumiy qoldiq tushuntirib beriladi. Jo'natish va
qabul qilishning ikkala harakati **bitta tranzaksiyada**: biri o'tib
ikkinchisi o'tmasa, tovar hech qayerda bo'lmay qolardi.

### Tannarx

`unit_cost` **jo'natish paytida** FIFO dan hisoblanadi
(`StockLedger::issue()` qaytargan `cost_total / qty_sent`) va
`transfer_items.unit_cost` ga yoziladi. Transitga ham, qabul qiluvchi
filialga ham **o'sha qiymat** bilan kiradi — aks holda transferning
o'zi foyda yoki zarar yasab qo'yardi.

### Transit joyi (ANALIZ 3.2)

| Usul | Egasi | Joy qayta ishlatiladimi |
|---|---|---|
| `own_driver` | `User` (haydovchi) | Ha — bitta haydovchining qo'lidagi hamma narsa bitta joyda |
| `by_hand` | `User` (xodim) | Ha |
| `taxi` | `Transfer` | Yo'q — javobgar odam yo'q, har transferga alohida |

Transit location `branch_id` sifatida **jo'natuvchi** filialni oladi
(ANALIZ 3.10): yo'ldagi tovar hali jo'natuvchining javobgarligida.

---

## 4. Ko'rinish — `TwoSidedBranchScope`

`transfers` va `stock_requests` da bitta `branch_id` **yo'q**: ularning
ikkita tomoni bor. Oddiy `BranchScope` yaramaydi — qabul qiluvchi
filial o'ziga kelayotgan tovarni ko'rmay qolardi.

`App\Modules\Warehouse\Scopes\TwoSidedBranchScope` "shu ikki ustundan
**birortasi** mening filialimga tegishlimi" degan savolga aylantiradi.
Transferda ustunlar `locations` ga ishora qilgani uchun filial bir qadam
orqali topiladi (ichki so'rov, join emas: `orWhereIn` bilan join
qatorlarni ikkilantirib yuborardi).

---

## 5. Rejadan/hujjatdan farq qilgan qarorlar

1. **`Branch::warehouse()` endi `BranchScope` ni chetlab o'tadi.**
   Filial allaqachon qo'lda turibdi, ya'ni chaqiruvchi uni ko'rish
   huquqini boshqa yo'l bilan olgan. Cheklov qolganda transferni qabul
   qilayotgan xodim jo'natuvchi filial omborini topa olmasdi va hujjat
   o'z ichida uzilib qolardi. Xuddi shu sabab bilan `SendTransfer` va
   `ReceiveTransfer` ichida location'lar `withoutGlobalScopes()` bilan
   o'qiladi.

2. **`TransferPolicy::view` `transfer.receive` ni ham qabul qiladi.**
   Sotuvchida `transfer.view_any` yo'q, lekin `transfer.receive` bor
   (PERMISSIONS.md §3). Kelgan tovarni sanash uchun u hujjatni ocha
   olishi kerak — aks holda unga berilgan ruxsatni amalda ishlatib
   bo'lmasdi.

3. **`resolveDiscrepancy` holatni o'zgartirmaydi.** Transfer
   `partially_received` bo'lib **qoladi** — u haqiqatan kam kelgan.
   O'chadigani `has_discrepancy`, ya'ni direktorga signal. Xulosa
   hujjat izohiga **qo'shiladi**, ustidan yozilmaydi.

4. **Taksi xarajati hozircha faqat `transfers.taxi_cost` da.**
   7.10 avtomatik `expenses` yozuvini talab qiladi, lekin `expenses`
   jadvali Finance bosqichida keladi. Summa va chek yo'li saqlanadi,
   xarajat yozuvi esa o'sha bosqichda bog'lanadi. **Bu qarz.**

5. **Yetkazish usuli qoralamada so'ralmaydi.** Hujjat tayyorlanayotganda
   kim olib borishi hali ma'lum emas; usul jo'natish paytida tanlanadi.
   Ustun `NOT NULL` bo'lgani uchun qoralamada `own_driver` turadi.

6. **Bir filial ichida transfer qilinmaydi.** `from` va `to` har xil
   filialda bo'lishi shart — bir ombordan ikkinchisiga ko'chirish
   transfer emas, u keyinchalik inventarizatsiya tuzatishi bo'ladi.

7. **Begona ombordan jo'natish 404 beradi, 422 emas.** Jo'natuvchi
   ombor `BranchScope` bilan qidiriladi — 403/422 "bunday ombor bor"
   degan ma'lumotni oshkor qilardi.

8. **Qabul qilishda har satr uchun miqdor majburiy.** Tushirib
   qoldirilgan satr "nol keldi" degan ma'noni bermaydi — aks holda bir
   satr unutilib, tovar jimgina hisobdan chiqib ketardi.

9. **So'ragan filial o'z so'rovini o'zi tasdiqlay olmaydi**
   (`DecideStockRequest`), direktordan tashqari: tovar chiqadigan
   filial uni o'z mijozlari uchun ham kerakligini biladi.

---

## 6. API

```
GET    /transfers                        POST /transfers
GET    /transfers/{id}
POST   /transfers/{id}/send              — ombor → transit
POST   /transfers/{id}/receive           — transit → ombor, farq write_off
POST   /transfers/{id}/cancel            — faqat qoralama
POST   /transfers/{id}/resolve-discrepancy

GET    /stock-requests                   POST /stock-requests
GET    /stock-requests/{id}
POST   /stock-requests/{id}/approve
POST   /stock-requests/{id}/reject
POST   /stock-requests/{id}/cancel
POST   /stock-requests/{id}/fulfill      — so'rovdan transfer qoralamasi
```

Ombor holatini o'zgartiradigan barcha `POST` lar `idempotency`
middleware ostida (§9).

---

## 7. Testlar

| Fayl | Kafolat |
|---|---|
| `Feature/Warehouse/TransferApiTest.php` (15) | Qoralama omborga tegmaydi; jo'natish transitga tannarx bilan o'tkazadi; qabul qilish tovarni yangi filialga o'tkazadi va transitni bo'shatadi; kam kelgan qism `write_off` bo'ladi va bayroq ko'tariladi; taksida xarajat majburiy va transit egasi transferning o'zi; ikkala tomon ko'radi, uchinchisi ko'rmaydi |
| `Feature/Warehouse/StockRequestApiTest.php` (11) | So'rov, tasdiqlash tovar chiqadigan filial tomonidan, so'rovdan transfer va bog'lanish, ikki tomonlama ko'rinish |

---

## 8. Bosqich 4 dan keyin

1. Finance bosqichi — `debts`, `expenses` (taksi xarajati shu yerda
   bog'lanadi), `supplier_transactions`.
2. Bosqich 5 — Klinika.
3. Admin SPA ekranlari: kassa/chek, transfer, qurilmalar, sozlamalar.
