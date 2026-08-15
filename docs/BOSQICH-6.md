# BOSQICH 6 — USTAXONA (bajarilgan ish)

> Manba: `docs/PROJECT.md` §11 (Bosqich 6), §6.6, 7.5, 7.12, §10 (usta ekrani);
> `docs/SCHEMA.md` §3 (`defects`), §6; `docs/ENUMS.md` §3, §6;
> `docs/PERMISSIONS.md` §3, §6; `docs/ANALIZ.md` 3.2, 3.4.
> Sana: 2026-08-15. Holat: **bajarilgan va yashil**.
>
> Qolip `BOSQICH-3…5` bilan bir xil. Ziddiyat chiqsa —
> `PROJECT.md`/`SCHEMA.md` yutadi, keyin §5 (farq qilgan qarorlar).

---

## 1. Hajm

**Kiradi:** ish buyrug'i va kanban, usta zaxirasi, material sarfi
(`consume`), brak (7.5), qayta ishlash.

**Kirmaydi:** inventarizatsiya (`inventories`) va yo'qotilgan savdo
(`lost_sales`) — ular Bosqich 10 atrofida; brakning yetkazib beruvchiga
qaytarish akti (`supplier_transactions`) — Finance bosqichi (§5 #6).

---

## 2. Migratsiyalar

| Fayl | Jadvallar |
|---|---|
| `0001_01_01_000390_create_work_orders_table.php` | `work_orders`, `work_order_items` |
| `0001_01_01_000400_create_defects_table.php` | `defects` |

---

## 3. Ish buyrug'i qayerdan keladi

Buyruq **qo'lda yaratilmaydi**. `PERMISSIONS.md` §6 da `work_order.create`
ruxsati yo'q — bu tasodif emas, ishora: buyruq buyurtma `in_workshop`
holatiga o'tganda tizim tomonidan tug'iladi.

```
ChangeOrderStatus(→ in_workshop)  →  CreateWorkOrder
```

Materiallar buyurtmaning **ombordagi** satrlaridan olinadi: xizmat
satrini ustaxonaga berib bo'lmaydi, unda material yo'q.

Bitta buyurtmaga bitta buyruq: qayta ishlash yangi hujjat emas, o'sha
buyruqning `rework_count` i (SCHEMA.md §6).

---

## 4. Usta zaxirasi va ikki marta chiqim xavfi

"Usta zaxirasi" alohida jadval emas: u `master` turidagi `location`,
egasi ustaning o'zi (ANALIZ 3.2). Shuning uchun qoldiq, FIFO va
hisobotlar ombor bilan **bir xil mexanizmdan** o'tadi.

```
ombor  --transfer_out/in-->  usta zaxirasi  --consume-->  buyurtma
```

`consume` ataylab `sale` dan ajratilgan (ANALIZ 3.4): bu sotuv emas
(pul harakati yo'q), lekin tannarxga tushadi.

**Eng nozik joy.** Material ustaxonada sarflangach, buyurtma
topshirilganda `DeliverOrder` uni **yana** ombordan chiqarardi va tovar
ikki marta kamayardi. Yechim: sarflangan material o'z satrini
belgilaydi — harakat `order_items.movement_id` ga, tannarx
`cost_total` ga yoziladi, `DeliverOrder` esa belgilangan satrni
qaytadan chiqarmaydi. Buni alohida test tekshiradi.

---

## 5. Rejadan/hujjatdan farq qilgan qarorlar

1. **Ish buyrug'i tizim tomonidan tug'iladi** (yuqorida). `WorkOrderPolicy::create`
   har doim `false`.

2. **Usta zaxirasiga berish `warehouse.adjustment.create` ruxsati ostida.**
   PERMISSIONS.md da bu amal uchun alohida ruxsat yo'q
   (`master_stock` da faqat `view` va `consume`), lekin zaxirani
   to'ldiradigan yo'lsiz butun mexanizm ishlamas edi. Bu qo'lda qoldiq
   ko'chirish — ya'ni `adjustment.create` ning aynan o'zi. Yangi ruxsat
   o'ylab topilmadi.

3. **Zaxiraga berish `transfer_out`/`transfer_in` bilan.** Tovar
   filialdan chiqmaydi, faqat joyi o'zgaradi — bu location'lar
   orasidagi ko'chirish. Hujjat (`transfers` qatori) yaratilmaydi:
   `CreateTransfer` bir filial ichidagi ko'chirishni ataylab rad etadi
   (Bosqich 4 §5 #6).

4. **Sarflangan materialning braki ombor harakatini yozmaydi.**
   `consume` uni daftardan chiqarib bo'lgan; yana `defect` yozsak,
   mavjud bo'lmagan tovar hisobdan chiqarilardi. O'shanda faqat zarar
   qiymati yoziladi — u sarflash paytidagi tannarxdan olinadi.

5. **`customer_request` braki buyurtmani `rework` ga qaytaradi**
   (7.5 jadvali). Buyruq yana `queued` bo'ladi va `rework_count`
   oshadi. Qolgan sabablarda buyruq `defect` bo'lib qoladi.

6. **`supplier_defect` da qaytarish akti tayyorlanmaydi.** 7.5 uni
   talab qiladi, lekin `supplier_transactions` Finance bosqichida.
   Hozircha brak sababi bilan yoziladi. **Bu qarz.**

7. **`transport_damage` yo'l varaqasiga bog'lanmaydi** — `trips`
   Bosqich 8 da. `defects.transfer_id` ustuni tayyor turibdi.

8. **Bosqich 3 dan qolgan qarz yopildi:** qaytarishda `restock = false`
   bo'lgan satr endi `defects` ga yoziladi. Sabab qaytarish sababidan
   kelib chiqadi: `product_defect` → `supplier_defect`, qolganlari →
   `customer_request`. Ombor harakati yozilmaydi (tovar sotilganda
   chiqib bo'lgan), lekin zarar qiymati yoziladi.

9. **`set_priority` filial boshlig'ida yo'q** — PERMISSIONS.md §6
   matritsasi bo'yicha u ustada va direktorda. Ruxsat kengaytirilmadi.

10. **Brak yozuvi qo'lda yaratilmaydi** (`DefectController` da `store`
    yo'q): u ustaxonadan yoki qaytarishdan avtomatik tug'iladi —
    "usta faqat sababni tanlaydi, qolganini tizim qiladi".

---

## 6. API

```
GET    /work-orders               GET  /work-orders/board   — kanban
GET    /work-orders/{id}
PUT    /work-orders/{id}/master   PUT  /work-orders/{id}/priority
POST   /work-orders/{id}/start    POST /work-orders/{id}/finish
POST   /work-orders/{id}/cancel
POST   /work-orders/{id}/consume  — material sarfi (ANALIZ 3.4)
POST   /work-orders/{id}/defect   — brak (7.5)

GET    /defects                   GET  /defects/{id}
POST   /defects/{id}/approve

GET    /master-stock              — ustaning qo'lidagi qoldiq
POST   /master-stock              — ombordan zaxiraga berish
```

Omborga tegadigan `POST` lar `idempotency` ostida (§9).

---

## 7. Testlar

| Fayl | Kafolat |
|---|---|
| `Feature/Workshop/WorkOrderApiTest.php` (11) | Buyruq buyurtma ustaxonaga o'tganda tug'iladi va ikkinchisi ochilmaydi; usta ishni oladi va yakunlaydi; `consume` zaxiradan chiqaradi; **topshirish sarflangan materialni qayta chiqarmaydi**; `customer_request` braki `rework` ga qaytaradi; usta xatosi ombordan hisobdan chiqaradi; sarflangan materialning braki omborga tegmaydi; kanban shoshilinch ishni tepaga qo'yadi |
| `Feature/Sales/ReturnApiTest.php` (10) | Qaytarishdagi brak `defects` ga yoziladi va sabab qaytarish sababidan kelib chiqadi |

---

## 8. Bosqich 6 dan keyin

1. Bosqich 7 — Telegram bot (bildirishnoma).
2. Bosqich 8 — Yetkazish: `trips`, haydovchi PWA, inkassatsiya.
   `transport_damage` braki o'sha yerda yo'l varaqasiga bog'lanadi.
3. Finance bosqichi — `debts`, `expenses` (Bosqich 4 taksi xarajati),
   `supplier_transactions` (Bosqich 6 qaytarish akti).
