# OPTIKA — ENUM QIYMATLARI

> Manba: `docs/PROJECT.md` §7, §8. Qarorlar: `docs/PROJECT.md` §15.
> Sana: 2026-08-10
>
> **Qoida:** har bir enum PHP tomonda `backed enum` (string) sifatida
> `app/Modules/<Modul>/Enums/` da yashaydi. Bazada `varchar` — `CHECK`
> constraint qo'yilmaydi, chunki qiymatlar bosqichma-bosqich qo'shiladi;
> validatsiya ilova darajasida (`Rule::enum()`).
>
> **Nomlash:** qiymatlar `snake_case`, inglizcha. Foydalanuvchiga
> ko'rinadigan matn `lang/{uz-latn,ru,en}/enums.php` dan olinadi —
> enum qiymati hech qachon to'g'ridan-to'g'ri ekranga chiqmaydi.

---

## 1. Core

### `branches.type` — `BranchType`
| Qiymat | Ma'nosi |
|---|---|
| `main` | Asosiy filial (A) — markaziy ombor, ustaxona shu yerda |
| `shop` | Oddiy do'kon (B, C, D, E) |

### `locations.type` — `LocationType`
| Qiymat | Ma'nosi | `owner_type` |
|---|---|---|
| `warehouse` | Filial ombori — 1-versiyada yagona ishlatiladigan tur (§15 #22) | `null` |
| `floor` | Savdo zali — sxemada bor, 1-versiyada ishlatilmaydi | `null` |
| `transit` | "Yo'lda" qoldiq (7.10) | `User` (haydovchi/xodim) yoki `Transfer` (taksi) |
| `master` | Usta zaxirasi | `User` (usta) |

### `devices.type` — `DeviceType`
`tablet` · `phone` · `pos`

### `shifts.status` — `ShiftStatus`
| Qiymat | Ma'nosi |
|---|---|
| `open` | Smena ochilgan, savdo ketmoqda |
| `closing` | Sanoq boshlandi (`actual_cash` kiritilmoqda) |
| `closed` | Yopilgan, `difference` hisoblangan |
| `disputed` | Kamomad/ortiqcha direktor tekshiruvida |

> Kunlik hisobot `closed` + `disputed` smenalar yig'indisi bo'yicha (§15 #24).

### `users.locale` — `Locale`
`uz-latn` (default) · `uz-cyrl` · `ru` · `en`

---

## 2. Catalog

### `products.type` — `ProductType`
| Qiymat | Ma'nosi | Ombordan o'tadimi |
|---|---|---|
| `frame` | Ramka | Ha |
| `lens` | Linza (ombordagi standart) | Ha |
| `accessory` | Futlyar, salfetka, zanjir | Ha |
| `ready` | Tayyor ko'zoynak, lupa, quyoshdan saqlovchi | Ha |
| `service` | Xizmat (ko'rik, yig'ish, ta'mir) | **Yo'q** |

> Individual buyurtma linzasi (7.13) `products` da **umuman yo'q** —
> u `order_items.custom_lens_params` da yashaydi.

### `products.status` — `ProductStatus` (7.17)
| Qiymat | Sotish | Ombor qoldig'i | Analitika |
|---|---|---|---|
| `pending` | **Ha** | **Ha** | Yo'q — "tekshirilmagan" qatorida |
| `approved` | Ha | Ha | Ha |
| `rejected` | **Yo'q** | `merged_into_id` tovarga birlashtiriladi | Asosiy tovar ostida |

### `product_variants.index` — `LensIndex`
`1.50` · `1.56` · `1.61` · `1.67` · `1.74`

> `decimal(3,2)` sifatida saqlanadi, enum sifatida validatsiya qilinadi.

### `product_variants.coating` — `LensCoating`
`none` · `hmc` · `shmc` · `blue` · `photochromic` · `polarized` · `mirror`

### `services.type` — `ServiceType`
`exam` (ko'rik) · `assembly` (yig'ish) · `repair` (ta'mir)

---

## 3. Warehouse

### `stock_movements.type` — `MovementType`
| Qiymat | Ishora | Ma'nosi | FIFO (7.20) |
|---|---|---|---|
| `purchase` | `+` | Yetkazib beruvchidan kirim | Qatlam **ochadi** |
| `sale` | `−` | Sotildi (chek yoki buyurtma) | Qatlam **sarflaydi** |
| `transfer_out` | `−` | Filialdan chiqdi (yo'lga) | Sarflaydi |
| `transfer_in` | `+` | Filialga kirdi (yo'ldan) | Ochadi (sarflangan tannarx bilan) |
| `adjustment` | `+/−` | Inventarizatsiya **ortiqchasi** yoki qo'lda tuzatish | `+` ochadi, `−` sarflaydi |
| `write_off` | `−` | Inventarizatsiya **kamomadi** — `adjustment` dan ajratilgan | Sarflaydi |
| `defect` | `−` | Brak (7.5) | Sarflaydi |
| `return` | `+` | Mijozdan qaytdi | Ochadi (sotilgan tannarx bilan) |
| `consume` | `−` | Usta o'z zaxirasidan buyurtmaga ishlatdi | Sarflaydi |
| `merge` | `+/−` | Dublikat tovar birlashtirilganda qoldiqni ko'chirish (7.17) | Qatlamni ko'chiradi |

> `write_off` ni `adjustment` dan ajratish sababi: hisobotda "kamomad" va
> "qo'lda tuzatish" bir xil qatorga tushmasligi kerak.

### `stock_layers.source_type` — `LayerSource`
`purchase` · `transfer_in` · `return` · `adjustment` · `initial` · `merge`

### `purchases.status` — `PurchaseStatus`
| Qiymat | Ma'nosi | Ombor |
|---|---|---|
| `draft` | Kiritilmoqda | Tegmaydi |
| `received` | Qabul qilindi | **Qatlamlar ochiladi** |
| `cancelled` | Bekor qilindi (faqat `draft` dan) | Tegmaydi |

> `received` dan orqaga qaytish yo'q — xato bo'lsa storno (7.21).

### `transfers.status` — `TransferStatus`
```
draft → sent → in_transit → received
                          → partially_received   (miqdor farqi — 7.4)
draft → cancelled
```
| Qiymat | Ma'nosi | Qoldiq qayerda |
|---|---|---|
| `draft` | Tayyorlanmoqda | Jo'natuvchi filialda |
| `sent` | Jo'natildi | `transit` location'da |
| `in_transit` | Yo'lda (haydovchi reysiga bog'landi) | `transit` location'da |
| `received` | To'liq qabul qilindi | Qabul qiluvchi filialda |
| `partially_received` | Kam keldi → `discrepancy` + direktorga signal (7.4) | Farq `write_off` |
| `cancelled` | Bekor (faqat `draft` dan) | Jo'natuvchida |

### `transfers.delivery_method` — `DeliveryMethod` (7.10)
| Qiymat | Transit egasi | Xarajat |
|---|---|---|
| `own_driver` | `User` (haydovchi) | Yo'q |
| `taxi` | `Transfer` (transferning o'zi) | **Majburiy** + chek rasmi |
| `by_hand` | `User` (xodim) | Ixtiyoriy |

### `stock_requests.status` — `StockRequestStatus`
```
pending → approved → fulfilled
        → rejected
pending → cancelled
```

### `inventories.status` — `InventoryStatus`
| Qiymat | Ma'nosi |
|---|---|
| `draft` | Rejalashtirilgan |
| `counting` | Sanoq ketmoqda |
| `review` | Farqlar direktor tekshiruvida |
| `completed` | Yakunlandi → `adjustment` / `write_off` yozildi |
| `cancelled` | Bekor qilindi |

### `defects.reason` — `DefectReason` (7.5)
| Qiymat | Kim to'laydi | Tizimda |
|---|---|---|
| `supplier_defect` | Yetkazib beruvchi | Qaytarish akti + `supplier_transactions` |
| `transport_damage` | Haydovchi/yetkazuvchi | Yo'l varaqasiga bog'lanadi |
| `master_error` | Do'kon zarari | Usta statistikasiga, mukofotdan ayiriladi (7.12) |
| `customer_request` | Mijoz yoki do'kon | Buyurtma `rework` ga qaytadi |

---

## 4. Sales

### `orders.type` — `OrderType`
| Qiymat | Ma'nosi | Holat o'qi |
|---|---|---|
| `quick` | Tez savdo (chek) — tovar darrov beriladi | `new → delivered → closed` |
| `order` | Buyurtma — yasash/kutish kerak | To'liq zanjir (quyida) |

### `orders.status` — `OrderStatus` (7.3)
```
new → awaiting_exam → prescription_ready
    → materials_reserved ─┬─ (bor) ──────────────→ in_workshop
                          └─ (yo'q) → awaiting_transfer → in_workshop
    → ready → customer_notified → delivered → closed
```
Yon tarmoqlar: `cancelled` · `returned` · `rework`

| Qiymat | Ma'nosi |
|---|---|
| `new` | Yaratildi |
| `awaiting_exam` | Ko'rik kutilmoqda |
| `prescription_ready` | Retsept tayyor |
| `materials_reserved` | Material zaxiralandi |
| `awaiting_transfer` | Material boshqa filialdan kelmoqda |
| `in_workshop` | Ustaxonada |
| `ready` | Tayyor, mijozni kutmoqda |
| `customer_notified` | Mijozga xabar berildi |
| `delivered` | Topshirildi — **daromad shu paytda tan olinadi** (7.8) |
| `closed` | Yopildi (to'lov ham tugagan) |
| `cancelled` | Bekor qilindi |
| `returned` | Qaytarildi |
| `rework` | Qayta ishlash (usta xatosi yoki mijozga to'g'ri kelmadi) |

> **Mukofot** faqat `delivered` + `payment_status = paid` bo'lganda (7.12).

### `orders.payment_status` — `PaymentStatus`
```
unpaid → partial → paid
       └→ debt (muddat o'tgan)
```
`unpaid` · `partial` · `paid` · `debt` · `refunded`

> `status` va `payment_status` — **mustaqil ikkita o'q**. `ready` + `partial`
> normal holat (7.3).

### `orders.delivery_type` — `OrderDeliveryType`
`pickup` (do'kondan olib ketadi) · `courier` (haydovchi yetkazadi)

### `payments.method` — `PaymentMethod`
| Qiymat | Kassaga tushadimi |
|---|---|
| `cash` | Ha — `cash_movements` yoziladi |
| `card` | Yo'q — terminal |
| `transfer` | Yo'q — bank o'tkazmasi |
| `click` | Yo'q — onlayn (keyingi bosqich) |
| `payme` | Yo'q — onlayn (keyingi bosqich) |

### `payments.status` — `PaymentTxStatus`
| Qiymat | Ma'nosi |
|---|---|
| `pending` | Haydovchi yig'di, kassaga hali topshirilmadi |
| `completed` | Kassada/hisobda |
| `reversed` | Storno qilingan (7.21) |

> `payments` — insert-only. `failed` holati yo'q: muvaffaqiyatsiz to'lov
> umuman yozilmaydi.

### `returns.reason` — `ReturnReason`
`customer_changed_mind` · `wrong_prescription` · `product_defect` · `wrong_item` · `other`

---

## 5. Clinic

### `visits.status` — `VisitStatus`
```
waiting → in_progress → finished
        → no_show
waiting → cancelled
```

### `visits.source` — `VisitSource`
`qr` (mijoz QR orqali navbat oldi) · `seller` (sotuvchi qo'shdi) · `walk_in`

---

## 6. Workshop

### `work_orders.status` — `WorkOrderStatus`
```
queued → in_progress → done
                     → defect   (brak — 7.5)
queued → cancelled
```

### `work_orders.priority` — `WorkOrderPriority`
`low` · `normal` · `high` · `urgent`

---

## 7. Delivery

### `trips.status` — `TripStatus`
```
planned → in_progress → finished
planned → cancelled
```

### `trip_stops.type` — `TripStopType`
`branch` (filialga transfer) · `customer` (mijozga buyurtma)

### `trip_stops.status` — `TripStopStatus`
| Qiymat | Ma'nosi |
|---|---|
| `pending` | Hali borilmadi |
| `arrived` | Yetib bordi (GPS olinadi) |
| `delivered` | Topshirdi |
| `failed` | Topshira olmadi (mijoz yo'q, rad etdi) |
| `skipped` | O'tkazib yuborildi |

### `trip_stops.confirmation_status` — `ConfirmationStatus` (7.4)
| Qiymat | Ma'nosi |
|---|---|
| `awaiting` | Mijozga savol yuborildi |
| `confirmed` | Mijoz "Ha" dedi |
| `disputed` | Mijoz "Yo'q" dedi → direktorga qizil signal |
| `unconfirmed` | 24 soat javob yo'q → avto-yopildi, belgi qoladi |

---

## 8. Finance

### `cash_movements.type` — `CashDirection`
`in` · `out`

### `cash_movements.category` — `CashCategory`
| Qiymat | Yo'nalish | Manba (`source_type`) |
|---|---|---|
| `sale` | `in` | `Order` / `Payment` |
| `debt_payment` | `in` | `Payment` |
| `collection` | `in` | `Collection` (haydovchidan) |
| `shift_opening` | `in` | `Shift` (boshlang'ich pul) |
| `refund` | `out` | `Return` |
| `expense` | `out` | `Expense` |
| `supplier_payment` | `out` | `SupplierTransaction` |
| `salary` | `out` | — |
| `bonus_payout` | `out` | `BonusEntry` |
| `deposit_to_safe` | `out` | `Shift` (inkassatsiya) |
| `shortage` | `out` | `Shift` (kamomad) |
| `surplus` | `in` | `Shift` (ortiqcha) |
| `correction` | `in`/`out` | Storno (7.21) |

### `debts.status` — `DebtStatus`
| Qiymat | Ma'nosi |
|---|---|
| `open` | Muddat kelmagan |
| `overdue` | Muddat o'tgan |
| `doubtful` | **90+ kun** — foyda hisobotidan chiqariladi (7.6) |
| `paid` | To'landi |
| `written_off` | Hisobdan chiqarildi (direktor qarori) |

> Muddat guruhlari (hisobot uchun, enum emas): `0–7` · `8–30` · `31–90` · `90+`

### `debt_reminders.channel` — `ReminderChannel`
`telegram` · `sms` · `call` · `in_person`

### `debt_reminders.response` — `ReminderResponse`
`none` · `promised` · `refused` · `disputed` · `paid`

### `expenses` / `expense_categories`
`expense_categories` — **jadval**, enum emas (direktor qo'shadi).
Seeder bilan keladigan boshlang'ich ro'yxat:
`transport` (7.10 taksi shu yerga) · `rent` · `utilities` · `salary` ·
`marketing` · `equipment` · `supplies` · `other`

### `supplier_transactions.type` — `SupplierTxType` (7.15)
| Qiymat | Ishora | Ma'nosi |
|---|---|---|
| `purchase` | `−` | Tovar keldi — biz qarzdormiz |
| `payment` | `+` | To'lov qildik |
| `return` | `+` | Tovar qaytardik |
| `adjustment` | `+/−` | Kelishilgan tuzatish |

> Balans = yig'indi. `+` = avans (u tovar qarzdor), `−` = biz pul qarzdormiz.

---

## 9. Payroll

### `bonus_rules.base` — `BonusBase` (7.12)
| Qiymat | Ma'nosi |
|---|---|
| `revenue` | Sotuv summasidan (chegirmadan keyin) |
| `profit` | **Foydadan** — tavsiya etilgan (keraksiz chegirmaga undamaydi) |
| `count` | Buyurtmalar sonidan (usta uchun) |

### `bonus_entries.status` — `BonusStatus`
| Qiymat | Ma'nosi |
|---|---|
| `accrued` | Hisoblandi (`delivered` + `paid` sharti bajarildi) |
| `reversed` | Qaytarish sababli bekor qilindi (7.12) |
| `approved` | Direktor tasdiqladi |
| `paid` | To'landi |

### `branch_plans.type` — `PlanType` (7.18)
`revenue` · `profit` · `orders`

---

## 10. Umumiy

### `activity_log` (spatie) — `log_name`
`auth` · `catalog` · `warehouse` · `sales` · `finance` · `clinic` ·
`workshop` · `delivery` · `admin`

### Rollar — `Role` (§4)
`director` · `branch_manager` · `seller` · `doctor` · `master` · `driver` ·
`warehouse` · `accountant` · `customer`

> `customer` — **alohida guard**, `users` jadvalida emas (§4).
> Qolgan 8 rol `spatie/laravel-permission` orqali.

---

## O'zgartirish tartibi

1. Yangi qiymat qo'shish — avval shu faylga, keyin PHP enum'ga.
2. Qiymat **o'chirilmaydi** — bazadagi eski yozuvlar buziladi. Ishlatilmaydigan
   qiymat `@deprecated` deb belgilanadi.
3. Qiymat **qayta nomlanmaydi** — `UPDATE` migratsiyasi kerak bo'ladi, bu esa
   moliyaviy hujjatlarga tegadi (7.21).
