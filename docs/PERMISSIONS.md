# OPTIKA — RUXSATLAR (PERMISSIONS)

> Manba: `docs/PROJECT.md` §4, §6, §7. Sana: 2026-08-10
> Mexanizm: `spatie/laravel-permission`, guard `web` (xodimlar) va
> `customer` (mijoz — §4).

## Konventsiya

Format: **`module.resource.action`** (§4).

- `module` — `app/Modules/*` nomining kichik harfli varianti
- `action` — fe'l: `view` · `view_any` · `create` · `update` · `delete` ·
  va domenga xos (`approve`, `receive`, `reverse`, ...)
- `view` = bitta yozuvni ko'rish, `view_any` = ro'yxatni ko'rish
- `*.view_all_branches` — **`BranchScope` ni chetlab o'tish**. Bu eng xavfli
  ruxsat, faqat `director` va `accountant` da (§4)

**Qoidalar:**
1. Ruxsat **rolga** beriladi, foydalanuvchiga to'g'ridan-to'g'ri emas.
   Istisno: vaqtinchalik kengaytmalar (direktor qo'lda beradi).
2. Har bir Policy metodi shu ro'yxatdagi ruxsatga tayanadi. Policy'da
   `if ($user->isDirector())` kabi qattiq shart **yozilmaydi**.
3. Yangi modul qo'shilganda ruxsatlari shu faylga qo'shiladi, keyin
   `RolePermissionSeeder` ga.
4. Filial cheklovi ruxsatdan **alohida** ishlaydi: `sales.order.view_any`
   bo'lgan sotuvchi ham faqat o'z filialini ko'radi (`BranchScope`).

---

## Rollar qisqacha (§4)

| Rol | Qurilma | Filial qamrovi |
|---|---|---|
| `director` | Telefon + kompyuter | **Barchasi** |
| `accountant` | Kompyuter | **Barchasi** (moliya) |
| `branch_manager` | Kompyuter | O'z filiali |
| `seller` | Kompyuter | O'z filiali |
| `doctor` | Kompyuter/planshet | O'z filiali |
| `master` | Planshet | O'z filiali + o'z zaxirasi |
| `warehouse` | Kompyuter | O'z filiali |
| `driver` | Telefon | Reysdagi filiallar |
| `customer` | Telefon | — (alohida guard) |

---

## 1. `core` — filial, xodim, smena, qurilma

| Ruxsat | Tavsif |
|---|---|
| `core.branch.view_any` | Filiallar ro'yxati |
| `core.branch.view` | Filial kartochkasi |
| `core.branch.create` | Yangi filial |
| `core.branch.update` | Tahrirlash |
| `core.branch.delete` | O'chirish (faqat bo'sh filial) |
| `core.user.view_any` | Xodimlar ro'yxati |
| `core.user.view` | Xodim kartochkasi |
| `core.user.create` | Xodim qo'shish |
| `core.user.update` | Tahrirlash |
| `core.user.delete` | Faolsizlantirish (`is_active = false`) |
| `core.user.assign_role` | Rol biriktirish |
| `core.user.set_debt_limit` | Qarz limiti (7.6) |
| `core.user.set_pin` | PIN o'rnatish/qayta tiklash (7.14) |
| `core.device.view_any` | Qurilmalar |
| `core.device.register` | Planshet ro'yxatdan o'tkazish |
| `core.device.revoke` | Tokenni bekor qilish |
| `core.shift.view_any` | Smenalar ro'yxati |
| `core.shift.view_all_branches` | Barcha filial smenalari |
| `core.shift.open` | Smena ochish |
| `core.shift.close` | Smena yopish |
| `core.shift.approve_difference` | Kamomad/ortiqchani tasdiqlash |
| `core.settings.view` | Tizim sozlamalari |
| `core.settings.update` | Sozlamalarni o'zgartirish (`min_prepayment_percent` — 7.7) |

## 2. `catalog` — tovar, variant, narx

| Ruxsat | Tavsif |
|---|---|
| `catalog.product.view_any` | Katalog ro'yxati |
| `catalog.product.view` | Tovar kartochkasi |
| `catalog.product.create` | To'liq tovar yaratish |
| `catalog.product.quick_create` | **Sotuv paytida tez qo'shish** (7.13) → `status = pending` |
| `catalog.product.update` | Tahrirlash |
| `catalog.product.delete` | O'chirish (faqat harakati yo'q tovar) |
| `catalog.product.approve` | **Tasdiqlash/rad etish** (7.17) |
| `catalog.product.merge` | Dublikatlarni birlashtirish (7.13) |
| `catalog.variant.create` | Variant qo'shish |
| `catalog.variant.update` | Variant tahrirlash |
| `catalog.price.view` | Narxni ko'rish |
| `catalog.price.update` | Narx o'rnatish/o'zgartirish |
| `catalog.cost.view` | **Tannarxni ko'rish** — sotuvchiga berilmaydi |
| `catalog.brand.manage` | Brendlar |
| `catalog.category.manage` | Kategoriyalar |
| `catalog.service.manage` | Xizmatlar va narxlari |

## 3. `warehouse` — qoldiq, kirim, transfer, inventarizatsiya

| Ruxsat | Tavsif |
|---|---|
| `warehouse.stock.view` | Qoldiqni ko'rish |
| `warehouse.stock.view_all_branches` | Barcha filial qoldig'i |
| `warehouse.movement.view_any` | Harakatlar daftari (7.1) |
| `warehouse.movement.reverse` | **Storno** (7.21) — faqat direktor |
| `warehouse.purchase.view_any` | Kirimlar ro'yxati |
| `warehouse.purchase.create` | Kirim yaratish (`draft`) |
| `warehouse.purchase.receive` | Qabul qilish → FIFO qatlami ochiladi (7.20) |
| `warehouse.purchase.cancel` | Bekor qilish (faqat `draft`) |
| `warehouse.transfer.view_any` | Transferlar |
| `warehouse.transfer.create` | Transfer tayyorlash |
| `warehouse.transfer.send` | Jo'natish |
| `warehouse.transfer.receive` | Qabul qilish + **haqiqiy miqdor** (7.4) |
| `warehouse.transfer.cancel` | Bekor qilish |
| `warehouse.transfer.resolve_discrepancy` | Miqdor farqini hal qilish (7.4) |
| `warehouse.request.create` | Tovar so'rash (filialdan filialga) |
| `warehouse.request.approve` | So'rovni tasdiqlash |
| `warehouse.request.fulfill` | So'rov bo'yicha transfer yaratish |
| `warehouse.inventory.view_any` | Inventarizatsiyalar |
| `warehouse.inventory.start` | Boshlash |
| `warehouse.inventory.count` | Sanoq kiritish |
| `warehouse.inventory.review` | Farqlarni ko'rib chiqish |
| `warehouse.inventory.complete` | Yakunlash → `adjustment`/`write_off` |
| `warehouse.defect.report` | Brak qayd etish (7.5) |
| `warehouse.defect.view_any` | Braklar ro'yxati |
| `warehouse.defect.approve` | Brakni tasdiqlash (zarar hisobga olinadi) |
| `warehouse.adjustment.create` | Qo'lda tuzatish |
| `warehouse.lost_sale.create` | Yo'qotilgan savdo yozish (7.9) |
| `warehouse.lost_sale.view_any` | Yo'qotilgan savdo hisoboti |
| `warehouse.master_stock.view` | Usta zaxirasi |
| `warehouse.master_stock.consume` | Zaxiradan ishlatish (`consume`) |

## 4. `sales` — buyurtma, to'lov, mijoz

| Ruxsat | Tavsif |
|---|---|
| `sales.order.view_any` | Buyurtmalar ro'yxati |
| `sales.order.view_all_branches` | Barcha filial buyurtmalari |
| `sales.order.create` | Chek/buyurtma yaratish |
| `sales.order.update` | Tahrirlash (yopilmagan) |
| `sales.order.cancel` | Bekor qilish |
| `sales.order.change_status` | Holatni o'zgartirish (7.3) |
| `sales.order.deliver` | Topshirish → daromad tan olinadi |
| `sales.order.rework` | Qayta ishlashga yuborish |
| `sales.discount.apply` | Chegirma berish (limitgacha) |
| `sales.discount.approve` | **Limitdan yuqori chegirma** — direktor |
| `sales.payment.create` | To'lov qabul qilish |
| `sales.payment.reverse` | To'lovni storno qilish (7.21) |
| `sales.return.create` | Qaytarish rasmiylashtirish |
| `sales.return.approve` | Qaytarishni tasdiqlash |
| `sales.customer.view_any` | Mijozlar ro'yxati |
| `sales.customer.view` | Mijoz kartochkasi |
| `sales.customer.create` | Mijoz qo'shish |
| `sales.customer.update` | Tahrirlash |
| `sales.customer.debt.view` | Qarz qoldig'i (7.6) |
| `sales.customer.debt.grant` | Qarzga berish (o'z limitigacha) |
| `sales.receipt.print` | Chek chiqarish (7.16) |

## 5. `clinic` — vizit, retsept

| Ruxsat | Tavsif |
|---|---|
| `clinic.visit.view_any` | Navbat va vizitlar |
| `clinic.visit.create` | Navbatga qo'shish |
| `clinic.visit.start` | Ko'rikni boshlash |
| `clinic.visit.finish` | Yakunlash |
| `clinic.visit.cancel` | Bekor qilish |
| `clinic.prescription.create` | Retsept yozish |
| `clinic.prescription.update` | Tahrirlash (faqat o'zi yozgan, 24 soat ichida) |
| `clinic.prescription.view` | **Faol tiket** — `visits.branch_id` filialida (7.11) |
| `clinic.prescription.view_history` | **Retsept tarixi** — barcha filiallar, faqat o'qish (7.11) |
| `clinic.ticket.transfer_branch` | Tiketni boshqa filialga o'tkazish — **sabab majburiy**, logga yoziladi (7.11) |

## 6. `workshop` — ustaxona

| Ruxsat | Tavsif |
|---|---|
| `workshop.work_order.view_any` | Ustaxona navbati |
| `workshop.work_order.assign` | Ustaga biriktirish |
| `workshop.work_order.start` | Ishni boshlash |
| `workshop.work_order.finish` | Yakunlash |
| `workshop.work_order.defect` | Brak deb belgilash (7.5) |
| `workshop.work_order.set_priority` | Muhimlik darajasi |

## 7. `delivery` — yetkazish

| Ruxsat | Tavsif |
|---|---|
| `delivery.trip.view_any` | Reyslar |
| `delivery.trip.create` | Reys tuzish |
| `delivery.trip.start` | Boshlash |
| `delivery.trip.finish` | Yakunlash |
| `delivery.stop.deliver` | "Yetkazdim" + GPS + rasm (7.4) |
| `delivery.stop.fail` | Topshira olmadim |
| `delivery.balance.view` | Haydovchi qo'lidagi pul |
| `delivery.balance.view_any` | Barcha haydovchilar balansi |
| `delivery.collection.create` | Pulni topshirish |
| `delivery.collection.receive` | Pulni qabul qilish (kassir/buxgalter) |

## 8. `finance` — kassa, xarajat, qarz, yetkazib beruvchi

| Ruxsat | Tavsif |
|---|---|
| `finance.cash.view` | Kassa holati |
| `finance.cash.view_all_branches` | Barcha filial kassasi |
| `finance.cash_movement.create` | Kassa yozuvi (kirim/chiqim) |
| `finance.cash_movement.reverse` | Storno (7.21) |
| `finance.expense.view_any` | Xarajatlar |
| `finance.expense.create` | Xarajat yozish |
| `finance.expense.approve` | Tasdiqlash |
| `finance.expense_category.manage` | Xarajat kategoriyalari |
| `finance.debt.view_any` | Qarzlar ro'yxati |
| `finance.debt.approve` | Limitdan yuqori qarzni tasdiqlash (7.6) |
| `finance.debt.write_off` | Hisobdan chiqarish |
| `finance.debt.remind` | Eslatma yuborish |
| `finance.supplier.view_any` | Yetkazib beruvchilar |
| `finance.supplier.manage` | Qo'shish/tahrirlash |
| `finance.supplier.balance.view` | Ikki tomonlama balans (7.15) |
| `finance.supplier_payment.create` | To'lov qilish |

## 9. `payroll` — mukofot va rejalar

| Ruxsat | Tavsif |
|---|---|
| `payroll.bonus.view_own` | **O'z mukofotini ko'rish** — har bir xodimda |
| `payroll.bonus.view_any` | Barcha mukofotlar |
| `payroll.bonus.approve` | Tasdiqlash |
| `payroll.bonus.pay` | To'lash |
| `payroll.bonus_rule.view` | Mukofot qoidalari |
| `payroll.bonus_rule.manage` | Foiz biriktirish (7.19) |
| `payroll.plan.view` | Rejalar |
| `payroll.plan.manage` | Reja qo'yish (7.18) |

## 10. `analytics` — hisobotlar

| Ruxsat | Tavsif |
|---|---|
| `analytics.dashboard.view` | Bosh ekran |
| `analytics.cash_report.view` | Kassa hisoboti (7.8-A) |
| `analytics.profit_report.view` | **Savdo va foyda** (7.8-B) — tannarx ko'rinadi |
| `analytics.stock_report.view` | ABC tahlil, o'lik zaxira (6.11) |
| `analytics.staff_report.view` | Xodimlar ko'rsatkichi |
| `analytics.branch_rating.view` | Filiallar reytingi (7.18) |
| `analytics.export` | Excel/CSV eksport |

## 11. `admin` — texnik

| Ruxsat | Tavsif |
|---|---|
| `admin.activity_log.view` | Audit log (spatie/activitylog) |
| `admin.horizon.access` | Horizon paneli |
| `admin.impersonate` | Boshqa xodim nomidan kirish (nosozlik uchun) |

## 12. `customer` guard — mijoz kabineti

> Alohida guard. Bu ruxsatlar `web` guard rollariga **berilmaydi**.

| Ruxsat | Tavsif |
|---|---|
| `customer.profile.view` | O'z profili |
| `customer.profile.update` | Telefon, til o'zgartirish |
| `customer.order.view_own` | O'z buyurtmalari va holati |
| `customer.prescription.view_own` | O'z retseptlari |
| `customer.debt.view_own` | O'z qarzi |
| `customer.delivery.confirm` | "Qabul qildim" tasdig'i (7.4) |

---

## Rol → ruxsat matritsasi

`✓` = butun guruh · `—` = yo'q · matn = guruhdan faqat o'sha amal

| Ruxsat guruhi | director | branch_manager | seller | doctor | master | warehouse | driver | accountant |
|---|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| `core.branch.*` | ✓ | view | — | — | — | — | — | view |
| `core.user.*` | ✓ | view_any, view | — | — | — | — | — | view_any |
| `core.user.assign_role` | ✓ | — | — | — | — | — | — | — |
| `core.user.set_debt_limit` | ✓ | — | — | — | — | — | — | — |
| `core.device.*` | ✓ | ✓ | — | — | — | — | — | — |
| `core.shift.open/close` | ✓ | ✓ | ✓ | — | — | ✓ | — | ✓ |
| `core.shift.view_all_branches` | ✓ | — | — | — | — | — | — | ✓ |
| `core.shift.approve_difference` | ✓ | ✓ | — | — | — | — | — | — |
| `core.settings.update` | ✓ | — | — | — | — | — | — | — |
| `catalog.product.view_any/view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | — | ✓ |
| `catalog.product.quick_create` | ✓ | ✓ | ✓ | — | — | ✓ | — | — |
| `catalog.product.create/update` | ✓ | ✓ | — | — | — | ✓ | — | — |
| `catalog.product.approve/merge` | ✓ | — | — | — | — | — | — | — |
| `catalog.price.update` | ✓ | ✓ | — | — | — | — | — | — |
| `catalog.cost.view` | ✓ | ✓ | **—** | — | — | ✓ | — | ✓ |
| `warehouse.stock.view` | ✓ | ✓ | ✓ | — | ✓ | ✓ | — | ✓ |
| `warehouse.stock.view_all_branches` | ✓ | — | — | — | — | — | — | ✓ |
| `warehouse.purchase.*` | ✓ | ✓ | — | — | — | ✓ | — | view_any |
| `warehouse.transfer.create/send` | ✓ | ✓ | — | — | — | ✓ | — | — |
| `warehouse.transfer.receive` | ✓ | ✓ | ✓ | — | — | ✓ | — | — |
| `warehouse.transfer.resolve_discrepancy` | ✓ | ✓ | — | — | — | — | — | — |
| `warehouse.request.create` | ✓ | ✓ | ✓ | — | — | ✓ | — | — |
| `warehouse.request.approve/fulfill` | ✓ | ✓ | — | — | — | ✓ | — | — |
| `warehouse.inventory.*` | ✓ | ✓ | count | — | — | ✓ | — | — |
| `warehouse.defect.report` | ✓ | ✓ | ✓ | — | ✓ | ✓ | — | — |
| `warehouse.defect.approve` | ✓ | ✓ | — | — | — | — | — | — |
| `warehouse.adjustment.create` | ✓ | — | — | — | — | ✓ | — | — |
| `warehouse.movement.reverse` | **✓** | — | — | — | — | — | — | — |
| `warehouse.lost_sale.create` | ✓ | ✓ | ✓ | — | — | ✓ | — | — |
| `warehouse.master_stock.*` | ✓ | view | — | — | ✓ | view | — | — |
| `sales.order.view_any/create` | ✓ | ✓ | ✓ | — | — | — | — | view_any |
| `sales.order.view_all_branches` | ✓ | — | — | — | — | — | — | ✓ |
| `sales.order.change_status` | ✓ | ✓ | ✓ | — | ✓ | — | — | — |
| `sales.order.deliver` | ✓ | ✓ | ✓ | — | — | — | ✓ | — |
| `sales.discount.apply` | ✓ | ✓ | ✓ | — | — | — | — | — |
| `sales.discount.approve` | **✓** | — | — | — | — | — | — | — |
| `sales.payment.create` | ✓ | ✓ | ✓ | — | — | — | ✓ | ✓ |
| `sales.payment.reverse` | **✓** | — | — | — | — | — | — | — |
| `sales.return.create` | ✓ | ✓ | ✓ | — | — | — | — | — |
| `sales.return.approve` | ✓ | ✓ | — | — | — | — | — | — |
| `sales.customer.*` | ✓ | ✓ | ✓ | view | — | — | view | ✓ |
| `sales.customer.debt.grant` | ✓ | ✓ | ✓ | — | — | — | — | — |
| `clinic.visit.*` | ✓ | ✓ | create | ✓ | — | — | — | — |
| `clinic.prescription.create/update` | — | — | — | ✓ | — | — | — | — |
| `clinic.prescription.view` | ✓ | ✓ | ✓ | ✓ | ✓ | — | — | — |
| `clinic.prescription.view_history` | ✓ | ✓ | ✓ | ✓ | — | — | — | — |
| `clinic.ticket.transfer_branch` | ✓ | ✓ | — | — | — | — | — | — |
| `workshop.work_order.view_any` | ✓ | ✓ | ✓ | — | ✓ | — | — | — |
| `workshop.work_order.assign` | ✓ | ✓ | — | — | ✓ | — | — | — |
| `workshop.work_order.start/finish/defect` | ✓ | — | — | — | ✓ | — | — | — |
| `delivery.trip.*` | ✓ | ✓ | — | — | — | ✓ | start/finish | — |
| `delivery.stop.deliver/fail` | — | — | — | — | — | — | ✓ | — |
| `delivery.balance.view` | ✓ | ✓ | — | — | — | — | own | ✓ |
| `delivery.collection.create` | — | — | — | — | — | — | ✓ | — |
| `delivery.collection.receive` | ✓ | ✓ | ✓ | — | — | — | — | ✓ |
| `finance.cash.view` | ✓ | ✓ | own shift | — | — | — | — | ✓ |
| `finance.cash.view_all_branches` | ✓ | — | — | — | — | — | — | ✓ |
| `finance.expense.create` | ✓ | ✓ | ✓ | — | — | ✓ | ✓ | ✓ |
| `finance.expense.approve` | ✓ | ✓ | — | — | — | — | — | ✓ |
| `finance.debt.view_any` | ✓ | ✓ | ✓ | — | — | — | — | ✓ |
| `finance.debt.approve` | **✓** | — | — | — | — | — | — | — |
| `finance.debt.write_off` | **✓** | — | — | — | — | — | — | — |
| `finance.supplier.*` | ✓ | — | — | — | — | view_any | — | ✓ |
| `finance.supplier_payment.create` | ✓ | — | — | — | — | — | — | ✓ |
| `finance.cash_movement.reverse` | **✓** | — | — | — | — | — | — | — |
| `payroll.bonus.view_own` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `payroll.bonus.view_any` | ✓ | ✓ | — | — | — | — | — | ✓ |
| `payroll.bonus.approve/pay` | ✓ | — | — | — | — | — | — | pay |
| `payroll.bonus_rule.manage` | **✓** | — | — | — | — | — | — | — |
| `payroll.plan.manage` | **✓** | — | — | — | — | — | — | — |
| `analytics.dashboard.view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `analytics.cash_report.view` | ✓ | ✓ | — | — | — | — | — | ✓ |
| `analytics.profit_report.view` | ✓ | ✓ | — | — | — | — | — | ✓ |
| `analytics.stock_report.view` | ✓ | ✓ | — | — | — | ✓ | — | ✓ |
| `analytics.staff_report.view` | ✓ | ✓ | — | — | — | — | — | ✓ |
| `analytics.export` | ✓ | ✓ | — | — | — | ✓ | — | ✓ |
| `admin.activity_log.view` | ✓ | — | — | — | — | — | — | ✓ |
| `admin.horizon.access` | ✓ | — | — | — | — | — | — | — |
| `admin.impersonate` | ✓ | — | — | — | — | — | — | — |

---

## Bosqich 1 da yaratiladigan qism

Bosqich 1 (§11) faqat `core` + `catalog` bilan ishlaydi, lekin
`RolePermissionSeeder` **butun ro'yxatni** yaratadi va rollarga biriktiradi —
keyingi bosqichlarda ekran qo'shilganda ruxsat allaqachon tayyor turadi.
Har bosqichda faqat Policy va Controller yoziladi.

`director` + `seller` rollari Bosqich 1 da **to'liq** ishlanadi
(`ANALIZ.md` §4, oxirgi risk).

---

## Muhim nozikliklar

1. **`catalog.cost.view` sotuvchida yo'q.** Sotuvchi tannarxni ko'rmaydi —
   aks holda chegirma berishda tannarxga qarab savdolashadi.
2. **`director` da `clinic.prescription.create` yo'q.** Retseptni faqat
   shifokor yozadi — tibbiy javobgarlik. Direktor o'qiy oladi.
3. **`driver` da `delivery.collection.receive` yo'q.** Pulni topshiradi,
   qabul qilmaydi — ikki tomonlama nazorat.
4. **Barcha `*.reverse` (storno) faqat `director` da** (7.21).
5. **`payroll.bonus.view_own` hammada bor** — xodim o'z mukofotini ko'rishi
   motivatsiya uchun muhim, lekin boshqalarnikini ko'rmaydi.
6. `driver` uchun `delivery.balance.view` — **faqat o'ziniki**, Policy'da
   `$trip->driver_id === $user->id` bilan qo'shimcha tekshiriladi.
