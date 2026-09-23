=== Woocommerce CLICK Payment Method ===
Contributors: CLICK
Tags: ecommerce, e-commerce, woocommerce, click, payment gateway, uzbekistan
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 10.2
Stable tag: 1.2.1
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

== Description ==

CLICK payment gateway for WooCommerce (classic checkout and Cart/Checkout Blocks).
Compatible with High-Performance Order Storage (HPOS).

Callback URLs to set in the CLICK merchant cabinet:
* Prepare:  https://example.com/click-api/prepare
* Complete: https://example.com/click-api/complete

Credentials can be defined in wp-config.php instead of the settings screen:
CLICK_MERCHANT_ID, CLICK_MERCHANT_USER_ID, CLICK_SERVICE_ID, CLICK_SECRET_KEY

== Changelog ==

= 1.2.1 =
* Fix - Сумма снова передаётся целым числом (как в рабочей 1.1.1); дробная часть шлётся только если она реально есть.
* Fix - Убрана сверка service_id в колбэках — при несовпадении с кабинетом давала -1 Sign check error.
* Fix - Убраны колонки created_at/updated_at: вставка больше не зависит от успешного ALTER таблицы.
* Fix - Снята жёсткая проверка валюты UZS в is_available() (осталась только проверка заполненных кредов).
* Fix - process_payment() не переводит заказ в pending принудительно (конфликтовало со Store API на блоках).
* Tweak - Ошибки $wpdb пишутся в лог clickuz при ответах -7.

= 1.2.0 =
* Fix - Объявлена совместимость с HPOS (custom_order_tables).
* Fix - prepare(): заказ теперь сохраняется (статус on-hold и transaction_id больше не теряются).
* Fix - Убрана SQL-инъекция в complete(); все запросы через $wpdb->prepare().
* Fix - Сумма передаётся и сверяется с точностью до 2 знаков (не ломаются заказы с дробным итогом).
* Fix - return_url ведёт на order-received (гости больше не попадают на форму логина).
* Fix - Устранён возможный фатал при возврате с оплаты (WC()->customer === null).
* Fix - Валидация всех POST-полей колбэков, hash_equals для подписи, проверка service_id.
* Fix - Колонка `error` в таблице транзакций сделана знаковой (Click присылает отрицательные коды).
* Fix - Определение WooCommerce через class_exists + заголовок Requires Plugins (работает в multisite).
* Fix - load_plugin_textdomain перенесён на init (WP 6.7+).
* Fix - Экранирование вывода на странице оплаты, JSON-параметры для popup-кнопки.
* Tweak - Blocks: передаются description и supports, версия скрипта, переводы.
* Tweak - Настройки: заголовок/описание метода, debug-лог (WooCommerce > Status > Logs, source: clickuz).
* Tweak - Схема БД обновляется при обновлении плагина, добавлены индексы.
