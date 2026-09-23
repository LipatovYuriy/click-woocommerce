# Установка и обновление — CLICK Payment Gateway 1.2.0

## 0. Требования

- WordPress 6.0+
- WooCommerce 7.0+ (протестировано до 10.2)
- PHP 7.4+ (рекомендуется 8.1+), расширения `curl`, `json`
- Валюта магазина UZS (иначе см. п. 7)
- Доступ к кабинету мерчанта Click и данные: Merchant ID, Merchant User ID, Service ID, Secret Key

## 1. Резервная копия

Перед обновлением обязательно:

```bash
# файлы
tar -czf ~/backup-clickuz-$(date +%F).tar.gz wp-content/plugins/woocommerce-clickuz-gateway*

# база (таблица транзакций + заказы)
wp db export ~/backup-db-$(date +%F).sql
```

Обновлять лучше в часы минимального трафика: незавершённые в этот момент транзакции
Click могут получить ошибку и будут повторены платёжной системой.

## 2. Удаление старой версии

Через админку: **Плагины → Woocommerce CLICK Payment Method → Деактивировать → Удалить**.
Настройки и таблица `wp_wc_click_transactions` при этом сохраняются (плагин не чистит данные при удалении).

Через WP-CLI:

```bash
wp plugin deactivate woocommerce-clickuz-gateway-master
wp plugin delete woocommerce-clickuz-gateway-master
```

> Папка в 1.2.0 называется `woocommerce-clickuz-gateway` (без `-master`). Если просто залить
> новую версию поверх, в системе окажутся два плагина с одним ID шлюза — старую папку нужно удалить.

## 3. Установка

**Через админку:** Плагины → Добавить новый → Загрузить плагин → `woocommerce-clickuz-gateway-1.2.0.zip` → Установить → Активировать.

**Через WP-CLI:**

```bash
wp plugin install /path/woocommerce-clickuz-gateway-1.2.0.zip --activate
```

**Вручную:**

```bash
cd wp-content/plugins
unzip woocommerce-clickuz-gateway-1.2.0.zip
chown -R www-data:www-data woocommerce-clickuz-gateway
```
затем активировать плагин в админке.

При активации создаётся/обновляется таблица `wp_wc_click_transactions` и сбрасываются
правила ЧПУ (нужны для эндпоинтов `/click-api/*`).

## 4. Настройка

**WooCommerce → Настройки → Платежи → CLICK**:

| Поле | Значение |
|---|---|
| Enable/Disable | включить |
| Title | CLICK (отображается на чекауте) |
| Description | текст под названием метода |
| Merchant ID / Merchant User ID / Merchant Service ID | из кабинета Click |
| Secret Key | из кабинета Click |
| Button type | With redirect (рекомендуется) |
| Status of order after payment | Обработка (processing) |
| Debug log | включить на время тестирования |

Нажать **Сохранить** — это же инициирует проверку схемы БД.

### Креды через wp-config.php (опционально)

Удобно, если prod и staging используют разные мерчанты:

```php
define( 'CLICK_MERCHANT_ID',      '12345' );
define( 'CLICK_MERCHANT_USER_ID', '54321' );
define( 'CLICK_SERVICE_ID',       '67890' );
define( 'CLICK_SECRET_KEY',       'xxxxxxxx' );
```

Константы имеют приоритет над значениями из настроек.

## 5. Настройка кабинета Click

В кабинете мерчанта у сервиса должны быть указаны:

```
Prepare URL:  https://example.com/click-api/prepare
Complete URL: https://example.com/click-api/complete
```

Эти же адреса выводятся внизу страницы настроек плагина. Относительно 1.1.1 они
не изменились — при обновлении менять ничего не нужно.

Требования к серверу:
- эндпоинты доступны по HTTPS с валидным сертификатом, без Basic Auth и без редиректов;
- IP-адреса Click не блокируются WAF / Cloudflare / fail2ban (у эндпоинтов нет капчи и rate-limit исключений);
- на сайте не включён «режим обслуживания» и кэш не перехватывает POST-запросы.

Проверка доступности:

```bash
curl -i -X POST https://example.com/click-api/prepare -d 'click_trans_id=1'
# ожидаемый ответ: HTTP 200 и JSON {"error":"-1", ...} или {"error":"-8", ...}
```
Если приходит HTML, 404 или редирект — сбросить ЧПУ (Настройки → Постоянные ссылки → Сохранить) и проверить WAF.

## 6. Приёмочная проверка

1. **Чекаут (классический)** — метод CLICK виден, оформление уводит на `my.click.uz`.
2. **Чекаут на блоках** — метод виден в блоке Checkout, название и описание корректные.
3. **Успешная оплата (минимальная сумма)**: заказ уходит в `on-hold` после prepare → в выбранный статус после complete; в заказе записан transaction_id.
4. **Дробная сумма** (например, с доставкой 15 500,50) — оплата проходит, ошибки `-2` нет.
5. **Оплата гостем** — после оплаты покупатель попадает на страницу «Заказ получен», корзина пуста.
6. **Отмена/ошибка оплаты** — заказ переходит в `failed`, повторная оплата возможна.
7. **Повторный колбэк** на оплаченный заказ — ответ `-4 Already paid`, статус не меняется.
8. **Логи**: WooCommerce → Status → Logs, источник `clickuz` — есть пары request/response без PHP-warning.
9. **HPOS**: WooCommerce → Настройки → Дополнительно → Features — плагин не в списке несовместимых.

После успешной приёмки **выключить Debug log** (в логи пишутся параметры запросов).

## 7. Магазин не в UZS

По умолчанию метод скрывается при валюте ≠ UZS. Если магазин конвертирует сам —
добавить в `functions.php` темы или в mu-plugin:

```php
add_filter( 'clickuz_is_available', '__return_true' );
```

## 8. Откат на 1.1.1

1. Деактивировать и удалить `woocommerce-clickuz-gateway`.
2. Установить прежний архив `woocommerce-clickuz-gateway-master.zip`.
3. Удалить опцию версии схемы, чтобы старый код не конфликтовал с новыми полями:
   ```bash
   wp option delete wc_click_db_version
   ```
   Новые колонки (`created_at`, `updated_at`, индексы) можно оставить — старый код их игнорирует.
4. Проверить настройки шлюза: `title`, `description`, `debug` старой версией не используются и просто игнорируются.

## 9. Диагностика

| Симптом | Причина / решение |
|---|---|
| Колбэк возвращает HTML или 404 | Не сброшены ЧПУ — Настройки → Постоянные ссылки → Сохранить |
| `-1 Sign check error` | Неверный Secret Key либо Service ID не совпадает с указанным в запросе |
| `-2 Incorrect parameter amount` | Сумма заказа изменилась между prepare и complete (купон, пересчёт доставки) |
| `-5 User does not exist` | Заказ удалён или `transaction_param` искажён сторонним плагином номеров заказов |
| `-6 Transaction does not exist` | Не выполнился prepare (проверить доступность эндпоинта) |
| Метод не виден на чекауте | Не заполнены креды, валюта не UZS, либо метод отключён в настройках |
| Заказ остаётся в `pending` после оплаты | Click не достучался до complete — смотреть логи и блокировки WAF |
