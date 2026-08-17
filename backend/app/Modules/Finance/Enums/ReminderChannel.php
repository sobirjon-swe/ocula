<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

/**
 * Qarz eslatmasi kanali — SCHEMA.md `debt_reminders`, BOSQICH-10.md §10a.
 *
 * `Telegram` faqat `telegram:remind-debts` buyrug'i tomonidan avtomatik
 * yoziladi — xodim uni qo'lda tanlay olmaydi (`manual()` ro'yxatida yo'q),
 * aks holda "yuborildimi" bilan "qo'ng'iroq qilindimi" chalkashib ketardi.
 */
enum ReminderChannel: string
{
    case Telegram = 'telegram';
    case Call = 'call';
    case InPerson = 'in_person';
    case Sms = 'sms';

    /**
     * @return array<int, self>
     */
    public static function manual(): array
    {
        return [self::Call, self::InPerson, self::Sms];
    }
}
