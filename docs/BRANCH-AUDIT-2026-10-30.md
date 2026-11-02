# Ocula — branchlar va yakunlash auditi

Sana: 2026-10-30. Manba: `git fetch origin --prune`, barcha remote branchlar,
`git cherry origin/main <branch>`, tree diff va origin/main hujjatlari.

## Branchlar

| Branch | HEAD | main bilan holati |
|---|---|---|
| main | 5d52fed | Eng to'liq mahsulot kodi; lokal main c55bbc2 dan oldinda |
| claude/bosqich-10b-mukofot | a22c86f | Barcha patchlari main ichida bor |
| claude/bosqich-10c-analitika | a1e5feb | Barcha patchlari main ichida bor |
| claude/bosqich-11-landing | e621220 | Barcha patchlari main ichida bor |
| claude/customer-pwa | e621220 | Landing branchi bilan bir xil HEAD; mijoz PWA kodi bunda yo'q |
| claude/shu-loyiha-qolgan-ishlar-tzzp9u | 056669a | Barcha patchlari main ichida bor; yakuniy tree farqi faqat landing/CLAUDE.md |
| dependabot/github_actions/github-actions-daa0830317 | 72e3c0a | main ustiga yagona setup-php action SHA yangilanishi |

Commit SHA lari turlicha bo'lsa ham feature patchlarining ekvivalentligi
`git cherry` orqali tasdiqlandi. Eski feature branchlarni qayta merge qilish
mahsulot funksiyalarini qo'shmaydi. Dependabot o'zgarishi alohida tekshirilishi kerak.

## Amaldagi holat

Remote main backendida Core, Catalog, Warehouse, Sales, Clinic, Workshop,
Telegram, Delivery, Customer, Finance, Payroll va Analytics mavjud.
Shuningdek Next.js landing va React mijoz kabineti mavjud.
Admin interfeysi kirish, mahsulotlar va qoldiq sahifalari bilan cheklangan.
Remote main test daraxtida 65 fayl bor; ularning mavjudligi testlar o'tganini
bildirmaydi. Ushbu auditda yangi main testlari bajarilmadi.

## Tasdiqlangan ochiq ishlar

1. Xodimlar panelida savdo/kassa, smena, mijozlar, buyurtmalar/to'lov/qaytarish,
   kirim/transfer/so'rovlar, klinika, ustaxona, moliya/mukofot va direktor
   ekranlarini mavjud API bilan ulash.
2. Haydovchi uchun telefon interfeysi, offline saqlash va qayta yuborish navbati.
   BOSQICH-8.md buni ochiqchasiga hajmdan chiqarib qoldirgan.
3. Inventarizatsiya: asosiy talabda bor, remote tree ichida implementatsiyasi
   topilmadi; sxema va endpointlar alohida chuqur tekshiriladi.
4. Sotuvchi qarz limiti va tasdiqlash qoidalarini majburiy qo'llash.
   BOSQICH-10.md bu tekshiruv hali qo'shilmaganini qayd etadi.
5. Yetkazib beruvchiga brak qaytarish va moliyaviy hisobni manba kirim/FIFO
   qatlamlari bilan bog'lash; amaldagi hujjatda ochiq qolgan.
6. Mukofot: qisman qaytarishning mutanosib storno hisobi, to'langan mukofot
   tuzatishlari va usta uchun pul stavkasi bo'yicha amaldagi soddalashtirishlarni
   talab bilan solishtirish. Pul stavkasi biznes qarorini talab qilishi mumkin.
7. To'rt til talabini barcha interfeyslar va xato xabarlarida tekshirish.
   Landing hozir hujjat bo'yicha faqat uz-latn; bosqich hujjati asosiy talabdan tor.
8. Mijoz PWA manifestiga qo'shimcha haqiqiy offline/service-worker ishlashini
   tekshirish; tekshirilgan source tree ichida service-worker topilmadi.
9. Frontend CI, barcha ilovalarning production buildlari, PostgreSQL testlari,
   rollar/filiallar izolyatsiyasi va brauzer orqali butun biznes oqimlarini tekshirish.
   Joriy workflow faqat backend tekshiruvlarini bajaradi.
10. Ishga tushirish/deploy qo'llanmasi, Redis queue va scheduler, Telegram webhook,
    zaxira nusxa va tiklash tartibi; tashqi servislar uchun real sozlamalarni tekshirish.

## Yakunlash mezoni bo'yicha taklif

Asos: origin/main va PROJECT.md, SCHEMA.md, ENUMS.md, PERMISSIONS.md.
Mavjud Laravel + React + Next.js arxitekturasi saqlanadi.
Avval test/build holati aniqlanadi, keyin funksional bo'shliqlar yopiladi,
so'ng asosiy ish oqimlari brauzerda tekshiriladi.

Tekshiriladigan oqimlar: smena ochish → kirim → savdo → to'lov → qaytarish →
smena yopish; ko'rik → retsept → buyurtma → ustaxona → topshirish;
transfer → haydovchi → qabul/nomuvofiqlik → inkassatsiya;
mijoz kabineti → yetkazish tasdig'i; qarz/xarajat/mukofot → hisobot;
landing → onlayn navbat so'rovi → xodimning ko'rib chiqishi.

React Native PROJECT.md da shartli: PWA yetmasa alohida bosqich.
Hostingga chiqarish uchun manzil va servis hisoblari hali aniqlanmagan;
kodning tayyorligi bilan haqiqiy production ishga tushishi alohida tekshiriladi.

Bu audit va yakunlash hajmi taklifi; implementatsiya tugaganligi haqida hisobot emas.
