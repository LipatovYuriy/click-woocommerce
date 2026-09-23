# CLICK Payment Gateway for WooCommerce — v1.2.0

**Дата:** 23.09.2026
**Пакет:** `woocommerce-clickuz-gateway-1.2.0.zip`
**Совместимость:** WordPress 6.0+ · WooCommerce 7.0 – 10.2 · PHP 7.4 – 8.3 · HPOS · Cart/Checkout Blocks
**Основа:** официальный `woocommerce-clickuz-gateway` 1.1.1

Релиз приводит плагин в рабочее состояние на актуальных версиях WooCommerce: закрыты
потеря статуса заказа на этапе prepare, SQL-инъекция в complete, срыв оплаты на заказах
с дробной суммой и несовместимость с HPOS.

---

## Критические исправления

| # | Проблема в 1.1.1 | Что сделано |
|---|---|---|
| 1 | Не объявлена совместимость с HPOS — плагин в списке несовместимых, включение HPOS блокируется | `FeaturesUtil::declare_compatibility('custom_order_tables', …)` + `cart_checkout_blocks` |
| 2 | `prepare()` вызывал `set_status()` / `set_transaction_id()` без `save()` — статус on-hold и transaction_id не сохранялись | Сохранение через `update_status()` после `set_transaction_id()` |
| 3 | SQL-инъекция: `$_POST['merchant_prepare_id']` подставлялся в запрос напрямую | Все запросы через `$wpdb->prepare()`, `merchant_prepare_id` дополнительно сверяется с ID заказа |
| 4 | Сумма отправлялась округлённой до целого, а сверялась с точным итогом → ответ `-2 Incorrect parameter amount` на любом заказе с дробной суммой | Единый метод `WC_Gateway_Clickuz::get_amount()` — 2 знака и на отправке, и на сверке |
| 5 | `return_url` вёл на `get_view_order_url()` — гость после оплаты попадал на форму логина | `get_checkout_order_received_url()` |
| 6 | `WC()->customer->get_id()` на `woocommerce_init` → фатал на PHP 8 при возврате с оплаты | Проверка `WC()->customer` и `WC()->cart` перед обращением |
| 7 | Необъявленные ключи `$_POST` (`sign_string`, `error`, `error_note`, `click_paydoc_id`) → PHP-warning в теле JSON-ответа, Click получал битый ответ | Полная валидация обязательных полей, приведение типов, `hash_equals()` для подписи, сверка `service_id` |
| 8 | Колонка `error BIGINT UNSIGNED` — при strict mode запись отрицательных кодов Click отбивалась | Колонка сделана знаковой |

## Совместимость и инфраструктура

- Определение WooCommerce через `class_exists()` вместо `active_plugins` — работает при сетевой активации в multisite; добавлен заголовок `Requires Plugins: woocommerce`.
- `load_plugin_textdomain()` перенесён на `init` — нет notice `_load_textdomain_just_in_time` в WP 6.7+.
- Схема БД обновляется по опции `wc_click_db_version` при обновлении плагина, а не только при активации; добавлены индексы по `merchant_trans_id` и `click_trans_id`, поля `created_at` / `updated_at`.
- Blocks-интеграция: передаются `description` и `supports` (раньше `supports.features` был `undefined`), версия скрипта по `filemtime`, подключены переводы; `checkout.js` переписан с фолбэками вместо `window.clickuz_settings`.
- Исправлена опечатка `CLICK_MERHCANT_ID` — константы из `wp-config.php` теперь действительно работают.

## Улучшения

- Рабочее логирование: настройка **Debug log**, записи в WooCommerce → Status → Logs, источник `clickuz` (в 1.1.1 источник был `paypal`, а само логирование никогда не включалось).
- Новые настройки **Title** и **Description** метода оплаты.
- `is_available()` скрывает метод, если не заполнены Merchant ID / Service ID / Secret Key либо валюта магазина не UZS (переопределяется фильтром `clickuz_is_available`).
- Экранирование всего вывода на странице оплаты; параметры popup-кнопки передаются через `wp_json_encode`.
- `can_refund_order()` возвращает `false` — возвраты выполняются в кабинете мерчанта (раньше метод существовал без `process_refund()`).

## Изменения API для разработчиков

- Фильтр `click_return_url` теперь получает вторым аргументом объект заказа: `apply_filters('click_return_url', $url, $order)`.
- Новый фильтр `clickuz_is_available( bool $available, WC_Gateway_Clickuz $gateway )`.
- Публичные геттеры `get_merchant_id()`, `get_merchant_user_id()`, `get_service_id()`, `get_secret_key()`.
- Статические хелперы `WC_Gateway_Clickuz::get_amount()`, `::get_transaction_param()`, `::get_return_url_for()`.

## Обратная совместимость

- Настройки, ID метода (`clickuz`) и URL колбэков (`/click-api/prepare`, `/click-api/complete`) не менялись — перенастройка в кабинете Click не требуется.
- Таблица `wp_wc_click_transactions` обновляется на месте через `dbDelta`, существующие записи сохраняются.
- Требование PHP поднято до 7.4.

## Известные ограничения

- Возвраты через API не поддерживаются (только кабинет мерчанта).
- Режим кнопки «Без редиректа» (popup) работает только на странице `order-pay`; при обычном оформлении покупатель уходит на my.click.uz.
- Магазины не в UZS требуют фильтра `clickuz_is_available`.

# Установка и обновление — CLICK Payment Gateway 1.2.0
