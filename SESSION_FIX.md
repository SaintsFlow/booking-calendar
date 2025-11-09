# Исправление проблемы "Сессия истекла"

## Проблема

Частые ошибки "Сессия истекла" (419 CSRF Token Mismatch) сразу после входа или при отправке форм в модальных окнах.

## Причины

1. **HTTPS на production без правильных настроек cookie**
2. **Короткое время жизни сессии** (120 минут по умолчанию)
3. **Неправильная конфигурация same-site cookie**

## Решение

### 1. Обновите .env на production сервере

```bash
# В файле .env на production (calendar.ittechnology.kz)
SESSION_DRIVER=database
SESSION_LIFETIME=480  # 8 часов вместо 2
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true  # ВАЖНО для HTTPS!
SESSION_SAME_SITE=lax
```

### 2. Для локальной разработки (.env)

```bash
SESSION_DRIVER=database
SESSION_LIFETIME=480
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false  # false для HTTP
SESSION_SAME_SITE=lax
```

### 3. Примените изменения на production

```bash
# На сервере calendar.ittechnology.kz
cd /var/www/calendar-ai  # или ваш путь

# Обновите .env (добавьте строки выше)
nano .env

# Очистите кеш конфигурации
php artisan config:clear
php artisan config:cache

# Перезапустите PHP-FPM
sudo systemctl restart php8.2-fpm
```

### 4. Проверьте результат

1. Откройте приложение в браузере
2. Откройте DevTools → Application → Cookies
3. Проверьте cookie с именем `календарь-бронирования-session`:
    - `Secure` должен быть `✓` (на HTTPS)
    - `SameSite` должен быть `Lax`
    - `HttpOnly` должен быть `✓`

## Дополнительная защита

Уже реализовано в `resources/js/bootstrap.js`:

```javascript
// Автоматическая перезагрузка при CSRF ошибке
window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 419) {
            console.error("CSRF token mismatch - session may have expired");
            if (confirm("Сессия истекла. Перезагрузить страницу?")) {
                window.location.reload();
            }
        }
        return Promise.reject(error);
    }
);
```

## Проверка на других окружениях

Если проблема сохраняется:

1. **Проверьте nginx/apache конфигурацию**:

    - Proxy должен передавать правильные заголовки
    - `proxy_set_header X-Forwarded-Proto https;`

2. **Проверьте APP_URL**:

    ```bash
    # В .env должен совпадать с реальным URL
    APP_URL=https://calendar.ittechnology.kz
    ```

3. **Проверьте время на сервере**:
    ```bash
    date  # должно быть правильное время
    timedatectl  # проверьте timezone
    ```

## Мониторинг

Логи сессий можно проверить в таблице `sessions` базы данных:

```sql
-- Посмотреть активные сессии
SELECT
    id,
    user_id,
    ip_address,
    FROM_UNIXTIME(last_activity) as last_activity_time,
    TIMESTAMPDIFF(MINUTE, FROM_UNIXTIME(last_activity), NOW()) as minutes_ago
FROM sessions
ORDER BY last_activity DESC
LIMIT 10;
```
