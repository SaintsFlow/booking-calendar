# GitHub Actions CI/CD Setup

## 📋 Содержание

1. [Обзор](#обзор)
2. [Необходимые настройки](#необходимые-настройки)
3. [Secrets в GitHub](#secrets-в-github)
4. [Workflow: Tests](#workflow-tests)
5. [Workflow: Deploy](#workflow-deploy)
6. [Настройка сервера](#настройка-сервера)
7. [Troubleshooting](#troubleshooting)

---

## Обзор

Настроено 2 GitHub Actions workflow:

### 1. **Tests** (`.github/workflows/tests.yml`)

-   Запускается на push и pull request в ветки `main` и `develop`
-   Matrix тестирование на PHP 8.2 и 8.3
-   MySQL 8.0 и Redis сервисы
-   PHPUnit тесты с покрытием (минимум 70%)
-   Laravel Pint (code style)
-   Composer и npm security audit

### 2. **Deploy** (`.github/workflows/deploy.yml`)

-   Запускается при push в ветку `main`
-   Сборка production версии
-   Деплой на сервер через SSH
-   Автоматический rollback при ошибках
-   Health check после деплоя

---

## Необходимые настройки

### 1. Перейдите в Settings → Secrets and variables → Actions

### 2. Добавьте следующие Repository Secrets:

| Secret Name      | Описание               | Пример                                  |
| ---------------- | ---------------------- | --------------------------------------- |
| `DEPLOY_HOST`    | IP или домен сервера   | `123.45.67.89` или `server.example.com` |
| `DEPLOY_USER`    | SSH пользователь       | `deployer` или `www-data`               |
| `DEPLOY_SSH_KEY` | Приватный SSH ключ     | `-----BEGIN RSA PRIVATE KEY-----...`    |
| `DEPLOY_PORT`    | SSH порт (опционально) | `22` (по умолчанию)                     |
| `DEPLOY_PATH`    | Путь к приложению      | `/var/www/calendar-ai`                  |
| `APP_URL`        | URL приложения         | `https://calendar-ai.example.com`       |

---

## Secrets в GitHub

### Как добавить secrets:

1. Откройте репозиторий на GitHub
2. Перейдите: **Settings** → **Secrets and variables** → **Actions**
3. Нажмите **"New repository secret"**
4. Введите имя и значение
5. Нажмите **"Add secret"**

### Генерация SSH ключа для деплоя:

```bash
# На вашей локальной машине
ssh-keygen -t rsa -b 4096 -C "github-actions-deploy" -f ~/.ssh/github_deploy_key

# Скопируйте ПРИВАТНЫЙ ключ в GitHub Secret DEPLOY_SSH_KEY
cat ~/.ssh/github_deploy_key

# Скопируйте ПУБЛИЧНЫЙ ключ на сервер
ssh-copy-id -i ~/.ssh/github_deploy_key.pub deployer@your-server.com

# Или вручную добавьте на сервере:
cat ~/.ssh/github_deploy_key.pub
# Затем на сервере:
nano ~/.ssh/authorized_keys
# Вставьте публичный ключ
```

---

## Workflow: Tests

### Что тестируется:

✅ **Matrix тестирование**

-   PHP 8.2
-   PHP 8.3

✅ **Сервисы**

-   MySQL 8.0
-   Redis 7

✅ **Проверки**

-   PHPUnit тесты (минимум 70% покрытие)
-   Laravel Pint (code style)
-   Composer security audit
-   npm security audit

### Когда запускается:

```yaml
on:
    push:
        branches: [main, develop]
    pull_request:
        branches: [main, develop]
```

### Пример статуса:

![GitHub Actions Tests](https://img.shields.io/github/actions/workflow/status/your-repo/tests.yml?label=tests)

---

## Workflow: Deploy

### Этапы деплоя:

1. **Сборка приложения**

    - Установка Composer dependencies (production)
    - Установка npm dependencies
    - Сборка Vue/Vite assets

2. **Создание архива**

    - Исключаются: `.git`, `node_modules`, `tests`, логи, кеши

3. **Деплой на сервер**

    - Создание backup текущей версии (хранится 5 последних)
    - Распаковка нового архива
    - Установка прав доступа

4. **Laravel оптимизации**

    - `config:cache`
    - `route:cache`
    - `view:cache`
    - `event:cache`

5. **База данных**

    - `migrate --force`

6. **Перезапуск сервисов**

    - Queue workers (`queue:restart`)
    - Reverb WebSocket (`reverb:restart`)
    - PHP-FPM
    - Supervisor

7. **Health Check**

    - Проверка доступности `/api/health`

8. **Rollback при ошибке**
    - Автоматическое восстановление из последнего backup

### Когда запускается:

```yaml
on:
    push:
        branches: [main]
    workflow_dispatch: # Ручной запуск
```

### Ручной запуск:

1. Перейдите: **Actions** → **Deploy to Production**
2. Нажмите **"Run workflow"**
3. Выберите ветку `main`
4. Нажмите **"Run workflow"**

---

## Настройка сервера

### 1. Создайте пользователя для деплоя:

```bash
# На сервере
sudo adduser deployer
sudo usermod -aG www-data deployer
sudo usermod -aG sudo deployer

# Дайте права на папку приложения
sudo chown -R deployer:www-data /var/www/calendar-ai
sudo chmod -R 775 /var/www/calendar-ai/storage
sudo chmod -R 775 /var/www/calendar-ai/bootstrap/cache
```

### 2. Настройте Supervisor для queue workers:

```bash
sudo nano /etc/supervisor/conf.d/calendar-worker.conf
```

```ini
[program:calendar-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/calendar-ai/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/calendar-ai/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

### 3. Настройте Reverb (WebSocket):

```bash
sudo nano /etc/supervisor/conf.d/calendar-reverb.conf
```

```ini
[program:calendar-reverb]
process_name=%(program_name)s
command=php /var/www/calendar-ai/artisan reverb:start
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/calendar-ai/storage/logs/reverb.log
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
```

### 4. Добавьте health check endpoint:

```bash
# routes/api.php
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
    ]);
});
```

### 5. Настройте sudoers для deployer:

```bash
sudo visudo
```

Добавьте:

```
deployer ALL=(ALL) NOPASSWD: /usr/sbin/service php8.2-fpm restart
deployer ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart php8.2-fpm
deployer ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl *
```

---

## Troubleshooting

### ❌ Deployment failed: Permission denied

**Проблема:** Нет прав на сервере

**Решение:**

```bash
# На сервере
sudo chown -R deployer:www-data /var/www/calendar-ai
sudo chmod -R 775 /var/www/calendar-ai/storage
sudo chmod -R 775 /var/www/calendar-ai/bootstrap/cache
```

### ❌ SSH connection failed

**Проблема:** Неверный SSH ключ или настройки

**Решение:**

1. Проверьте `DEPLOY_SSH_KEY` в GitHub Secrets (должен быть ПРИВАТНЫЙ ключ)
2. Проверьте публичный ключ на сервере: `cat ~/.ssh/authorized_keys`
3. Проверьте права: `chmod 700 ~/.ssh && chmod 600 ~/.ssh/authorized_keys`

### ❌ Tests failed: Database connection

**Проблема:** MySQL сервис не запустился в GitHub Actions

**Решение:**

-   Проверьте логи workflow
-   MySQL сервис должен пройти health check
-   Убедитесь что credentials в `.env.example` корректны

### ❌ Rollback не работает

**Проблема:** Нет backup файлов

**Решение:**

-   Backups создаются автоматически при каждом деплое
-   Проверьте папку: `/var/www/calendar-ai/backups`
-   Первый деплой не может откатиться (нет предыдущей версии)

### ❌ Queue workers не перезапускаются

**Проблема:** Supervisor не настроен или нет прав

**Решение:**

```bash
# Проверьте статус
sudo supervisorctl status

# Проверьте конфигурацию
sudo supervisorctl reread
sudo supervisorctl update

# Проверьте права в sudoers
sudo visudo
```

### ❌ Health check fails after deploy

**Проблема:** Приложение не отвечает или ошибка

**Решение:**

1. Проверьте логи: `tail -f /var/www/calendar-ai/storage/logs/laravel.log`
2. Проверьте PHP-FPM: `sudo systemctl status php8.2-fpm`
3. Проверьте nginx/apache: `sudo systemctl status nginx`
4. Проверьте .env файл на сервере
5. Выполните вручную: `php artisan optimize:clear`

---

## 📊 GitHub Actions Badge

Добавьте в README.md:

```markdown
![Tests](https://github.com/your-username/calendar-ai/actions/workflows/tests.yml/badge.svg)
![Deploy](https://github.com/your-username/calendar-ai/actions/workflows/deploy.yml/badge.svg)
```

---

## 🚀 Первый деплой

### Пошаговая инструкция:

1. **Добавьте все secrets в GitHub** (см. раздел выше)

2. **Подготовьте сервер** (см. "Настройка сервера")

3. **Создайте структуру на сервере:**

```bash
ssh deployer@your-server.com
mkdir -p /var/www/calendar-ai
cd /var/www/calendar-ai
mkdir -p storage/logs storage/framework/{cache,sessions,views}
mkdir -p bootstrap/cache
mkdir -p backups
```

4. **Скопируйте .env на сервер:**

```bash
# На локальной машине
scp .env deployer@your-server.com:/var/www/calendar-ai/.env
```

5. **Настройте .env на сервере:**

```bash
ssh deployer@your-server.com
cd /var/www/calendar-ai
nano .env
# Установите production настройки:
# APP_ENV=production
# APP_DEBUG=false
# APP_URL=https://your-domain.com
```

6. **Сделайте первый push в main:**

```bash
git add .
git commit -m "Setup GitHub Actions CI/CD"
git push origin main
```

7. **Следите за деплоем:**

    - GitHub → Actions → Deploy to Production
    - Дождитесь зеленого статуса ✅

8. **Проверьте работу:**

```bash
curl https://your-domain.com/api/health
```

---

## 📝 Заметки

-   Первый деплой может занять 5-10 минут
-   Backup создается автоматически перед каждым деплоем
-   Хранятся последние 5 backups
-   Rollback автоматический при ошибках
-   Health check опционален (continue-on-error: true)

---

## 🔒 Безопасность

✅ SSH ключ хранится как GitHub Secret (зашифрован)  
✅ .env файл не попадает в репозиторий (gitignore)  
✅ Composer и npm audit запускаются автоматически  
✅ Production сборка без dev-зависимостей  
✅ Минимальные права для deployer пользователя

---

## 📚 Полезные ссылки

-   [GitHub Actions Documentation](https://docs.github.com/en/actions)
-   [Laravel Deployment Best Practices](https://laravel.com/docs/deployment)
-   [Supervisor Documentation](http://supervisord.org/)
