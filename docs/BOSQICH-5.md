# BOSQICH 5 — KLINIKA (bajarilgan ish)

> Manba: `docs/PROJECT.md` §11 (Bosqich 5), §6.5, 7.11, §10 (shifokor ekrani);
> `docs/SCHEMA.md` §5; `docs/ENUMS.md` §5; `docs/PERMISSIONS.md` §5.
> Sana: 2026-08-15. Holat: **bajarilgan va yashil**.
>
> `BOSQICH-3.md` / `BOSQICH-4.md` bilan bir xil qolip. Ziddiyat chiqsa —
> `PROJECT.md`/`SCHEMA.md` yutadi, keyin §5 (farq qilgan qarorlar).

---

## 1. Hajm

**Kiradi:** vizit va navbat, retsept, sotuvchida avtomatik tiket,
7.11 qamrov qoidasi, tiketni boshqa filialga o'tkazish.

**Kirmaydi:** mijoz o'zi QR skanerlab navbat oladigan **ochiq oqim** —
u mijoz guard'ini talab qiladi (Bosqich 9, Mini App). Batafsil §5 #1.

---

## 2. Migratsiyalar

| Fayl | Jadvallar |
|---|---|
| `0001_01_01_000370_create_visits_table.php` | `visits` |
| `0001_01_01_000380_create_prescriptions_table.php` | `prescriptions`, `prescription_transfers` |

Ikkinchi migratsiya **`orders.prescription_id` ga tashqi kalit** ham
qo'shadi — u Bosqich 3 da `prescriptions` jadvali yo'qligi uchun
bog'lanmay qolgan edi.

`visits.queue_date` — oddiy `date` ustuni, generated column emas:
`created_at::date` PostgreSQL da immutable emas (vaqt mintaqasiga
bog'liq). Qiymatni ilova qo'yadi, noyoblikni
`unique(branch_id, queue_date, queue_number)` kafolatlaydi.

---

## 3. Navbat

Raqam **filial va kun** kesimida beriladi, ertaga yana 1 dan boshlanadi.
Takrorlanmaslik ikki qatlamda:

1. `CreateVisit` tranzaksiya ichida **filial qatorini qulflaydi**
   (`Branch ... lockForUpdate()`) — ikki sotuvchi bir vaqtda navbat
   berganda ikkalasi ham "7" ni olmaydi. Bu `DocumentNumber` dagi
   bilan bir xil mantiq (ANALIZ 3.12).
2. Bazadagi unique indeks.

Bir mijoz bir kunda bir filialda **bitta ochiq vizitga** ega bo'ladi:
ikki marta navbatga qo'shilgan mijoz shifokor ro'yxatini ikkilantirardi.

`no_show` va `cancelled` ataylab ajratilgan (ENUMS §5): hisobotda
shifokorning bo'sh o'tirgan vaqti ko'rinishi kerak.

---

## 4. Tiket va 7.11 — bosqichning yuragi

Tiket **alohida jadval emas**: u retsept qatorining `ticket_active` +
`ticket_branch_id` juftligi. Retsept saqlangan zahoti tiket ochiladi va
sotuvchi ekranida paydo bo'ladi.

| Nima | Kim ko'radi | Qanday |
|---|---|---|
| **Faol tiket** (ish hujjati) | faqat `ticket_branch_id` filiali | `GET /prescriptions` |
| **Retsept tarixi** (o'qish) | barcha filiallar | `GET /customers/{id}/prescriptions` |

Sabab (7.11): A filialdagi shifokorning retsepti B filialga tushsa, B da
**keraksiz ko'zoynak yasalib qoladi** — sof brak va zarar.

**Tiket qachon yopiladi:** retsept bo'yicha buyurtma ochilganda
(`CreateOrder` → `CloseTicket`). Faqat **o'z filialining** tiketi:
mijoz boshqa filialga kelib eski retsepti bo'yicha buyurtma bersa
(7.11 buni ataylab ruxsat beradi), asl filialdagi ish hali
bajarilmagan va uning tiketi ochiq qoladi.

**Ko'chirish** (`POST /prescriptions/{id}/transfer-ticket`) qoidadan
chekinish: alohida ruxsat, **sabab majburiy**, `prescription_transfers`
ga yoziladi.

---

## 5. Rejadan/hujjatdan farq qilgan qarorlar

1. **QR ochiq oqimi bu bosqichda emas.** `VisitSource::Qr` ma'lumot
   sifatida to'liq qo'llab-quvvatlanadi (planshet QR orqali kelgan
   vizitni shu manba bilan ochadi), lekin mijoz o'zi skanerlab navbat
   oladigan **autentifikatsiyasiz endpoint** yozilmadi: u `customers`
   guard'ini talab qiladi va u Bosqich 9 da keladi. **Bu qarz.**

2. **`Prescription` da global scope yo'q.** 7.11 ikki xil ko'rinish
   talab qiladi (tiket — cheklangan, tarix — cheklovsiz). Global scope
   qo'ysak, tarixni har safar chetlab o'tishga to'g'ri kelardi va
   "chetlab o'tish" odatga aylanib, cheklovning ma'nosi yo'qolardi.
   Shuning uchun cheklov aniq scope'lar bilan:
   `activeTicketsFor()` / `activeTickets()`.

3. **Tahrirlash sharti Policy'da emas, Action'da.** "O'zi yozgan va 24
   soat ichida" sharti yozuvning **yoshiga** bog'liq. Policy'da
   tekshirilsa, xato xabari "ruxsat yo'q" bo'lib chiqardi — aslida
   sabab boshqa va shifokor nima qilishni bilmasdi.

4. **Diopter sakrashi — ogohlantirish, taqiq emas.** Retsept saqlanadi,
   javob `meta.warnings` bilan qaytadi (§10: "1.5+ farq qilsa —
   ogohlantirish"). Chegara `config/optika.php` da.

5. **0.25 qadam API darajasida tekshiriladi**, faqat ekranda emas:
   "−2.5 o'rniga −25" xatosi haqiqiy pul yo'qotish (§10), va tekshiruv
   faqat interfeysda qolsa, boshqa mijoz (mobil, import) uni chetlab
   o'tardi.

6. **Sotuvchi navbatga qo'sha oladi, lekin navbat ro'yxatini ko'rmaydi.**
   PERMISSIONS.md §5 matritsasida sotuvchida `clinic.visit.create` bor,
   `clinic.visit.view_any` yo'q. Bu ziddiyat emas (Bosqich 4 dagi
   transfer holatidan farqli): navbatga qo'shish ro'yxatni ko'rishni
   talab qilmaydi, shuning uchun ruxsat kengaytirilmadi.

7. **Retsept yozish direktorda ham yo'q** — `RolePermissionSeeder`
   dagi `DIRECTOR_EXCLUDES` shundoq turibdi (tibbiy javobgarlik).
   Test buni aniq tekshiradi.

8. **`prescriptions` da AXIS chegarasi DB darajasida ham bor**
   (`CHECK (0..180)`) — burchak sifatida ma'nosiz qiymat validatsiya
   chetlab o'tilganda ham bazaga tushmasin.

---

## 6. API

```
GET    /visits                 GET  /visits/queue      — jonli navbat
GET    /visits/{id}            POST /visits
POST   /visits/{id}/start      POST /visits/{id}/finish
POST   /visits/{id}/cancel     POST /visits/{id}/no-show

GET    /prescriptions                          — faol tiketlar (o'z filiali)
GET    /prescriptions/{id}
POST   /prescriptions                          — yozish, tiket ochiladi
PUT    /prescriptions/{id}                     — o'zi yozgani, 24 soat ichida
POST   /prescriptions/{id}/transfer-ticket     — sabab majburiy
GET    /customers/{customer}/prescriptions     — tarix (barcha filiallar)
```

Navbat va retsept yozish `idempotency` ostida: planshetda internet
uzilib qayta yuborilganda ikkinchi navbat yoki ikkinchi retsept paydo
bo'lmasin (§9).

---

## 7. Testlar

| Fayl | Kafolat |
|---|---|
| `Feature/Clinic/VisitApiTest.php` (13) | Navbat 1 dan boshlanadi va ketma-ket; har filial o'z navbatini sanaydi; kechagi navbat bugungisiga qo'shilmaydi; bir mijoz kuniga bir marta; boshlangan ko'rik bekor qilinmaydi; `no_show` alohida; jonli navbat faqat bugungi ochiqlarni beradi |
| `Feature/Clinic/PrescriptionApiTest.php` (17) | Tiket vizit filialida ochiladi; **boshqa filial tiketni ko'rmaydi, tarixni ko'radi**; ko'chirish sabab bilan va logga; diopter sakrashi ogohlantiradi lekin to'smaydi; 0.25 qadam va AXIS chegarasi; tahrir faqat o'zi yozgani va 24 soat ichida; direktor ham yoza olmaydi |
| `Feature/Clinic/TicketClosesOnOrderTest.php` (3) | Retsept bo'yicha buyurtma tiketni yopadi; boshqa filialdagi tiket ochiq qoladi |

---

## 8. Bosqich 5 dan keyin

1. Bosqich 6 — Ustaxona (kanban, usta zaxirasi, brak, qayta ishlash).
   Retsept va tiket o'sha yerda ish buyrug'iga aylanadi.
2. Finance bosqichi — `debts`, `expenses` (Bosqich 4 dagi taksi
   xarajati shu yerda bog'lanadi), `supplier_transactions`.
3. Bosqich 9 — mijoz kabineti; QR orqali navbat olish shu yerda yopiladi.
