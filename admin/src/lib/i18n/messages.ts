/**
 * Interfeys matnlari — PROJECT.md §10.
 *
 * Uchta tarjima yuritiladi: `uz-latn`, `ru`, `en`. `uz-cyrl` qo'lda
 * tarjima qilinmaydi — backend uni `uz-latn` dan transliteratsiya qiladi,
 * shuning uchun til tanlash ro'yxatida ham shu uchtasi turadi.
 *
 * Backenddan kelgan xato matnlari bu yerda takrorlanmaydi: ular allaqachon
 * kerakli tilda keladi (`Accept-Language`).
 */

export const messages = {
  'uz-latn': {
    'app.name': 'OPTIKA',
    'app.loading': 'Yuklanmoqda…',

    'nav.products': 'Tovarlar',
    'nav.stock': 'Ombor qoldig‘i',
    'nav.logout': 'Chiqish',

    'login.title': 'Tizimga kirish',
    'login.subtitle': 'Telefon raqami va parolingizni kiriting',
    'login.phone': 'Telefon',
    'login.password': 'Parol',
    'login.submit': 'Kirish',
    'login.submitting': 'Kirilmoqda…',

    'products.title': 'Tovarlar',
    'products.search': 'Nomi bo‘yicha qidirish…',
    'products.name': 'Nomi',
    'products.brand': 'Brend',
    'products.category': 'Kategoriya',
    'products.type': 'Turi',
    'products.status': 'Holati',
    'products.variants': 'Variantlar',
    'products.empty': 'Tovar topilmadi.',

    'status.pending': 'Tasdiq kutmoqda',
    'status.approved': 'Tasdiqlangan',
    'status.rejected': 'Rad etilgan',

    'stock.title': 'Ombor qoldig‘i',
    'stock.sku': 'SKU',
    'stock.barcode': 'Shtrix-kod',
    'stock.optical': 'Optika',
    'stock.quantity': 'Qoldiq',
    'stock.branch': 'Filial',
    'stock.location': 'Joy',
    'stock.empty': 'Qoldiq yo‘q.',
    'stock.allBranches': 'Barcha filiallar',

    'table.total': 'Jami',
    'table.prev': 'Oldingi',
    'table.next': 'Keyingi',
    'table.page': 'Sahifa',

    'error.forbidden': 'Bu bo‘limga ruxsatingiz yo‘q.',
    'error.retry': 'Qayta urinish',
  },

  ru: {
    'app.name': 'OPTIKA',
    'app.loading': 'Загрузка…',

    'nav.products': 'Товары',
    'nav.stock': 'Остатки',
    'nav.logout': 'Выход',

    'login.title': 'Вход в систему',
    'login.subtitle': 'Введите номер телефона и пароль',
    'login.phone': 'Телефон',
    'login.password': 'Пароль',
    'login.submit': 'Войти',
    'login.submitting': 'Вход…',

    'products.title': 'Товары',
    'products.search': 'Поиск по названию…',
    'products.name': 'Название',
    'products.brand': 'Бренд',
    'products.category': 'Категория',
    'products.type': 'Тип',
    'products.status': 'Статус',
    'products.variants': 'Варианты',
    'products.empty': 'Товары не найдены.',

    'status.pending': 'Ожидает подтверждения',
    'status.approved': 'Подтверждён',
    'status.rejected': 'Отклонён',

    'stock.title': 'Остатки на складе',
    'stock.sku': 'SKU',
    'stock.barcode': 'Штрих-код',
    'stock.optical': 'Оптика',
    'stock.quantity': 'Остаток',
    'stock.branch': 'Филиал',
    'stock.location': 'Место',
    'stock.empty': 'Остатков нет.',
    'stock.allBranches': 'Все филиалы',

    'table.total': 'Всего',
    'table.prev': 'Назад',
    'table.next': 'Вперёд',
    'table.page': 'Страница',

    'error.forbidden': 'Нет доступа к этому разделу.',
    'error.retry': 'Повторить',
  },

  en: {
    'app.name': 'OPTIKA',
    'app.loading': 'Loading…',

    'nav.products': 'Products',
    'nav.stock': 'Stock',
    'nav.logout': 'Sign out',

    'login.title': 'Sign in',
    'login.subtitle': 'Enter your phone number and password',
    'login.phone': 'Phone',
    'login.password': 'Password',
    'login.submit': 'Sign in',
    'login.submitting': 'Signing in…',

    'products.title': 'Products',
    'products.search': 'Search by name…',
    'products.name': 'Name',
    'products.brand': 'Brand',
    'products.category': 'Category',
    'products.type': 'Type',
    'products.status': 'Status',
    'products.variants': 'Variants',
    'products.empty': 'No products found.',

    'status.pending': 'Pending approval',
    'status.approved': 'Approved',
    'status.rejected': 'Rejected',

    'stock.title': 'Stock balances',
    'stock.sku': 'SKU',
    'stock.barcode': 'Barcode',
    'stock.optical': 'Optics',
    'stock.quantity': 'Quantity',
    'stock.branch': 'Branch',
    'stock.location': 'Location',
    'stock.empty': 'No stock.',
    'stock.allBranches': 'All branches',

    'table.total': 'Total',
    'table.prev': 'Previous',
    'table.next': 'Next',
    'table.page': 'Page',

    'error.forbidden': 'You do not have access to this section.',
    'error.retry': 'Retry',
  },
} as const

export type MessageKey = keyof (typeof messages)['uz-latn']
export type TranslatedLocale = keyof typeof messages
