# BOSQICH 8 — YETKAZISH (Delivery)

> Manba: `docs/PROJECT.md` §11 (Bosqich 8), §6.7, 7.1 (transit location),
> 7.4 (yetkazish tasdig'i — asimmetrik), 7.10 (transfer yetkazish
> usullari), §5.2 (haydovchi pul aylanmasi); `docs/SCHEMA.md` (`trips`,
> `trip_stops`, `driver_balances`, `collections`); `docs/ENUMS.md` §7;
> `docs/PERMISSIONS.md` §7; `docs/BOSQICH-4.md` §5 #4 (taksi xarajati
> qarzi), `docs/BOSQICH-6.md` §5 #7 (`transport_damage` bog'lanishi).
> Sana: 2026-08-17. Holat: **bajarilgan va yashil**.
>
> Qolip `BOSQICH-3…7` bilan bir xil.

---

## 1. Hajm

**Kiradi:** yo'l varaqasi (`trips`) va uning to'xtashlari (`trip_stops`,
filial yoki mijoz turida), haydovchining "Yetkazdim" tasdig'i (GPS,
masofa, ixtiyoriy rasm), mijozga Telegram orqali "Yetkazildimi?" savoli
va javobi (7.4), haydovchi qo'lidagi **pul** balansi va inkassatsiya
(`driver_balances`, `collections`), `transfers.trip_id` uchun haqiqiy
tashqi kalit.

**Kirmaydi:** haydovchi PWA'sining o'zi va offline sinxronizatsiya
(frontend ishi — `ANALIZ.md` §16 da ochiq savol), taksi xarajatining
`expenses` ga avtomatik bog'lanishi (Finance/Bosqich 10 kutmoqda —
`BOSQICH-4.md` §5 #4 dagi qarz hali yopilmaydi), Yandex Maps'ning o'zi
(faqat `lat`/`lng`/`address` saqlanadi, xaritani frontend chizadi).

Haydovchi qo'lidagi **tovar** balansi (transit location) allaqachon
Bosqich 4'da qurilgan (`SendTransfer` → `transit` location) — bu
bosqich unga tegmaydi, faqat **pul** tomonini qo'shadi.

---

## 2. Migratsiyalar

| Fayl | Jadvallar |
|---|---|
| `0001_01_01_000420_create_trips_table.php` | `trips`, `trip_stops` |
| `0001_01_01_000430_create_driver_balances_table.php` | `driver_balances`, `collections` |
| `0001_01_01_000440_add_trip_foreign_to_transfers_table.php` | `transfers.trip_id` — endi haqiqiy tashqi kalit |

---

## 3. Haydovchi pul aylanmasi — allaqachon tayyor turgan ilgak

`payments.status` (`PaymentTxStatus::Pending` — "Haydovchi yig'di,
kassaga hali topshirilmadi") va `payments.collected_by` ustunlari
Bosqich 3'dayoq yozilgan, lekin **hech kim ishlatmagan edi**
(`RecordPayment` doim `Completed` yozardi). Bu bosqich aynan shu
ilgakni ishlatadi:

```
Mijozga yetkazish (cash_to_collect > 0)
  → RecordPayment(..., collectedInField: true)
  → payments: status=pending, collected_by=haydovchi, kassaga TUSHMAYDI
  → OrderBalance::refresh() — mijozning qarzi darrov kamayadi
     (pul haydovchida, lekin mijoz endi qarzdor emas)

Haydovchi ertasi kuni A ga kelganda:
  → CollectDriverCash — Collection yozuvi + cash_movements (category=collection)
  → ENDI kassada
```

`payments` qatori **hech qachon yangilanmaydi** (`Immutable`, 7.21) —
"pending" holati abadiy shu yozuvda qoladi, bu haqiqatga to'g'ri keladi
("pul dala sharoitida yig'ilgan edi"). Haydovchi balansi esa **hosila**
(SCHEMA.md): `SUM(pending to'lovlar) − SUM(inkassatsiyalar)`. Ya'ni
alohida "to'lov to'liq bo'ldi" degan holatga o'tkazish shart emas —
balans formulasining o'zi muvozanatni ushlab turadi.

`CashCategory::Collection` (yo'nalishi `in`) ham Bosqich 3'da
tayyorlangan, lekin ishlatilmagan edi (`isManual()` ro'yxatida bor
edi). Endi `CollectDriverCash` shuni ishlatadi.

---

## 4. Ikkita mustaqil tasdiq — driver va mijoz

7.4 ga ko'ra ikkita alohida voqea:

1. **Haydovchi "Yetkazdim" bosadi** — `TripStop::deliver()`. GPS va
   ixtiyoriy rasm shu yerda. Filial turidagi to'xtashda bu **faqat**
   "yetkazib qo'ydim" degani — haqiqiy miqdorni **filial xodimi**
   alohida, mavjud `POST /transfers/{id}/receive` orqali kiritadi
   (Bosqich 4, o'zgarmagan). Mijoz turidagi to'xtashda esa shu qadam
   `DeliverOrder` (Sales, Bosqich 3) ni ham chaqiradi — tovar aynan shu
   paytda ombordan/mijozga o'tadi.

2. **Mijoz Telegram orqali javob beradi** — `confirmation_status`.
   Haydovchi yetkazgach mijozga inline tugmali xabar ketadi ([Ha]/[Yo'q],
   Telegram moduli, Bosqich 7 infratuzilmasi ustiga). Javob callback
   orqali keladi. 24 soat (`SettingKey::DeliveryAutoConfirmAfterHours`)
   javobsiz qolsa — kunlik buyruq uni `unconfirmed` qilib yopadi.

Ikkalasi ham **mustaqil**: haydovchi "yetkazdim" deganidan keyin ham
mijoz "yo'q" deb javob berishi mumkin — bu xato emas, direktorga signal
(hozircha: `disputed` holati sifatida ko'rinadi, jonli lenta UI keyingi
ish).

---

## 5. Rejadan/hujjatdan farq qilgan qarorlar

1. **Yo'l varaqasi qo'lda quriladi, avtomatik emas.** `delivery.trip.create`
   driverda **yo'q** (PERMISSIONS.md §7 jadvali) — bu ish buyrug'i
   (Bosqich 6) dan farqli, tasodif emas: dispetcher (direktor/filial
   boshlig'i/omborchi) marshrutni qo'lda tuzadi, chunki qaysi
   transferlar/buyurtmalar birga ketishini odam hal qiladi.

2. **Filial to'xtashi mavjud transferga bog'lanadi, yangisini
   yaratmaydi.** `AddTripStop` faqat allaqachon `SendTransfer` bilan
   `own_driver` usulida jo'natilgan transferni oladi (`transfer.driver_id`
   shu trip haydovchisiga teng bo'lishi shart) va `transfers.trip_id` ni
   orqaga bog'laydi. Ikki alohida "transfer yaratish" yo'li bo'lmasin
   degan qaror.

3. **`TripStop::deliver()` filial turida `ReceiveTransfer` ni
   chaqirmaydi.** 7.4 asimmetrik: haydovchi tasdig'i va filial xodimi
   tasdig'i **ikkita alohida amal**, ikkalasi ham majburiy, lekin bir xil
   odam emas. Ular birlashtirilsa, xodim haqiqiy miqdorni kiritish
   imkoniyatini yo'qotardi.

4. **Stop.deliver/fail — faqat o'z reysiga.** PERMISSIONS.md §7 buni
   so'zma-so'z aytmaydi, lekin nuance #6 (`delivery.balance.view` —
   "faqat o'ziniki") bilan bir xil mantiq: aks holda haydovchi boshqa
   haydovchining to'xtashini yetkazgan bo'lib qo'yardi. `TripStopPolicy`
   da `$model->trip->driver_id === $user->id` tekshiruvi qo'shildi.

5. **`trip.start`/`finish` — dispetcher yoki o'z haydovchisi.**
   `delivery.trip.create` bo'lgan foydalanuvchi istalgan reysni
   boshlaydi/tugatadi (director/branch_manager/warehouse); faqat
   `delivery.trip.start` bo'lgan (driver) — o'zinikini.

6. **Mijoz tasdig'ining Telegram tugmalari — Bosqich 7 webhookka
   qo'shimcha.** `TelegramWebhookController` endi `callback_query`
   turini ham tushunadi (avval faqat `/start`). Bu ikki bosqichni
   bog'laydigan yagona joy: `Delivery\Events\StopDelivered` →
   `Telegram\Listeners\AskDeliveryConfirmation` (chiquvchi xabar) →
   webhook `callback_query` → `Delivery\Actions\TripStop\ConfirmStopDelivery`
   / `DisputeStopDelivery` (kiruvchi javob).

7. **`SettingKey::DeliveryAutoConfirmAfterHours` yangi qo'shildi.**
   `config/optika.php` da `delivery.auto_confirm_after_hours` standart
   qiymat sifatida allaqachon bor edi (Bosqich 1 dan), lekin direktor
   o'zgartira oladigan sozlama sifatida ulanmagan edi.

8. **Taksi xarajati va `transport_damage` bog'lanishi — hali qarz.**
   `transfers.trip_id` endi haqiqiy tashqi kalit bo'lgani uchun
   `defects.transfer_id → transfers.trip_id` orqali brakni yo'l
   varaqasiga bog'lash **ma'lumot darajasida** endi mumkin (qo'shimcha
   ustun kerak emas), lekin alohida hisobot/ekran hali yo'q — Analytics
   bosqichi ishi. Taksi xarajatining `expenses`ga bog'lanishi
   `BOSQICH-4.md` §5 #4 dagi kabi Finance bosqichini kutadi.

---

## 6. API

```
GET    /trips                         GET  /trips/{id}
POST   /trips                         — reys yaratish (dispetcher)
POST   /trips/{id}/stops              — to'xtash qo'shish
POST   /trips/{id}/start              POST /trips/{id}/finish
POST   /trips/{id}/cancel             — faqat `planned` dan

POST   /trip-stops/{id}/deliver       — "Yetkazdim" (GPS, rasm)
POST   /trip-stops/{id}/fail          — topshira olmadim (sabab)

GET    /driver-balances               GET /driver-balances/{driverId}
POST   /collections                   — inkassatsiya (haydovchi topshiradi)
GET    /collections
```

Pulga tegadigan `POST` lar (`deliver`, `collections`) `idempotency`
ostida (§9).

---

## 7. Testlar

| Fayl | Kafolat |
|---|---|
| `Feature/Delivery/TripApiTest.php` | Reys yaratiladi, to'xtash qo'shiladi, faqat o'z haydovchisi boshlaydi/tugatadi |
| `Feature/Delivery/DeliverStopTest.php` | Mijoz to'xtashi yetkazilganda buyurtma topshiriladi va `cash_to_collect` bo'lsa `pending` to'lov yaratiladi; filial to'xtashi transferni **qabul qilmaydi** (alohida amal bo'lib qoladi); boshqa haydovchi yetkaza olmaydi |
| `Feature/Delivery/DriverBalanceTest.php` | Yetkazish balansni oshiradi, inkassatsiya balansni kamaytiradi va kassaga tushadi (`cash_movements`); ortiqcha inkassatsiya rad etiladi |
| `Feature/Telegram/DeliveryConfirmationTest.php` | Yetkazilgach mijozga tugmali xabar ketadi; "Ha"/"Yo'q" callback holatni yangilaydi; muddat o'tgach `unconfirmed` bo'ladi |

---

## 8. Bosqich 8 dan keyin

1. Bosqich 9 — Mijoz kabineti: shu bosqichda qurilgan yetkazish tasdig'i
   tushunchasi Mini App'da ham ko'rinadi.
2. Bosqich 10 — Moliya: taksi xarajati `expenses`ga bog'lanadi,
   haydovchi balansi va inkassatsiya hisobotga qo'shiladi.
