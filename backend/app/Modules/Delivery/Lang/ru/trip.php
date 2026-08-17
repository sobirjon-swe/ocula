<?php

declare(strict_types=1);

return [
    'already_has_trip' => 'У этого водителя уже есть рейс на :date.',
    'stops_only_while_planned' => 'Остановку можно добавить только до начала рейса.',
    'transfer_required' => 'Для остановки в филиале нужно указать трансфер.',
    'transfer_wrong_driver' => 'Этот трансфер отправлен другим способом или другим водителем.',
    'transfer_not_sent' => 'Трансфер ещё не отправлен.',
    'transfer_already_on_trip' => 'Этот трансфер уже привязан к другому рейсу.',
    'order_required' => 'Для остановки у клиента нужно указать заказ.',
    'order_has_no_customer' => 'В этом заказе не указан клиент.',
    'address_required' => 'Нужно указать адрес.',
    'cannot_start' => 'Не удалось начать рейс: текущий статус — :status.',
    'cannot_finish' => 'Не удалось завершить рейс: текущий статус — :status.',
    'cannot_cancel' => 'Не удалось отменить рейс: текущий статус — :status.',
];
