# 📅 Calendar - Полная документация

Laravel-приложение для управления бронированиями с интеграцией Bitrix24 CRM, real-time обновлениями через WebSocket и мультитенантностью.

---

## 📑 Содержание

1. [Быстрый старт](#-быстрый-старт)
2. [Архитектура](#-архитектура)
3. [Bitrix24 Integration](#-bitrix24-crm-integration)
    - [OAuth 2.0 авторизация](#oauth-20-авторизация)
    - [Синхронизация товаров](#синхронизация-товаров-с-bitrix24)
    - [CRM Pipeline](#crm-pipeline)
4. [WebSocket (Laravel Reverb)](#-websocket-integration-laravel-reverb)
5. [База данных](#️-база-данных)
6. [Frontend (Vue 3 + Inertia)](#-frontend-vue-3--inertiajs)
7. [Авторизация](#-авторизация)
8. [Административные команды](#-административные-команды)
9. [Тестирование](#-тестирование)
10. [Deployment](#-deployment)
11. [Troubleshooting](#-troubleshooting)

---

## 🚀 Быстрый старт

### Требования

-   PHP 8.2+
-   Composer
-   Node.js & NPM
-   MySQL/PostgreSQL
-   Redis (опционально, для продакшена)

### Установка

```bash
# 1. Клонировать репозиторий
git clone <repository-url>
cd calendar-ai

# 2. Установить зависимости
composer install
npm install

# 3. Настроить окружение
cp .env.example .env
php artisan key:generate

# 4. Настроить БД в .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=calendar_ai
DB_USERNAME=root
DB_PASSWORD=

# 5. Запустить миграции
php artisan migrate --seed

# 6. Создать первого супер-администратора
php artisan admin:create-super

# 7. Запустить приложение (3-4 терминала)
# Terminal 1:
php artisan serve

# Terminal 2:
php artisan reverb:start

# Terminal 3:
npm run dev

# Terminal 4 (опционально - для фоновых задач):
php artisan queue:work
```

Приложение будет доступно по адресу: http://127.0.0.1:8000

---

## 📐 Архитектура

### Структура проекта

```
app/
├── Actions/              # Domain Actions (бизнес-логика)
│   ├── Booking/         # Создание, обновление, удаление бронирований
│   ├── Calendar/        # Логика календаря
│   └── Schedule/        # Управление расписанием
├── Application/         # Application Layer
│   ├── Booking/         # Application Services
│   └── Service/
├── Domain/              # Domain Layer (бизнес-сущности)
│   └── Booking/
├── Infrastructure/      # Infrastructure Layer
│   ├── CRM/             # CRM интеграции
│   │   ├── Bitrix24/    # Bitrix24 Pipeline
│   │   └── Pipeline/    # Pipeline паттерн
│   ├── ExternalServices/
│   └── Repositories/
├── Events/              # Domain Events
├── Jobs/                # Background Jobs
├── Listeners/           # Event Listeners
└── Models/              # Eloquent Models
```

### Clean Architecture

Проект построен на принципах Clean Architecture:

-   **Domain Layer**: Бизнес-логика, не зависящая от фреймворка
-   **Application Layer**: Use Cases и Application Services
-   **Infrastructure Layer**: Внешние интеграции (CRM, БД, API)
-   **Presentation Layer**: Controllers, Views (Inertia.js + Vue 3)

---

## 🔌 Bitrix24 CRM Integration

### ⚡ Персональные настройки для каждого тенанта

Каждый тенант (кабинет) имеет свои персональные настройки Bitrix24:

```php
// Модель: TenantBitrix24Settings
$settings = TenantBitrix24Settings::where('tenant_id', 1)->first();

// Webhook URL хранится в зашифрованном виде
$settings->webhook_url; // Автоматически расшифровывается

// OAuth credentials (зашифрованы)
$settings->oauth_client_id;      // Client ID для OAuth 2.0
$settings->oauth_client_secret;  // Client Secret для OAuth 2.0

// Настройки контакта
$settings->contact_type_id;
$settings->contact_source_id;

// Настройки сделки
$settings->deal_category_id;
$settings->deal_stage_id;
$settings->deal_currency_id;

// Настройки каталога товаров
$settings->catalog_iblock_id;    // ID информационного блока каталога
```

### 📊 CRM ID в сущностях

Каждая сущность хранит свой ID из CRM:

```php
// Client
$client->crm_contact_id  // ID контакта в Bitrix24

// Booking
$booking->crm_deal_id    // ID сделки в Bitrix24

// Service
$service->bitrix24_product_id  // ID товара в каталоге Bitrix24
$service->type                 // 'service' или 'product'
```

---

## OAuth 2.0 авторизация

### Как работает

#### Шаг 1: Пользователь нажимает "Войти через Bitrix24"

```
GET /auth/bitrix24/redirect?tenant=company.bitrix24.ru
↓
Перенаправление на: https://oauth.bitrix.info/oauth/authorize/?client_id=XXX&response_type=code&state=XXX
```

#### Шаг 2: Bitrix24 перенаправляет обратно с кодом

```
GET /auth/bitrix24/callback?code=XXX&domain=company.bitrix24.ru&member_id=XXX&state=XXX
↓
Обмен code на access_token
↓
Получение данных пользователя через API
↓
Поиск/создание тенанта по domain
↓
Поиск/создание пользователя по bitrix24_user_id
↓
Авторизация и редирект на /
```

#### Шаг 3: Автоматическое сохранение webhook URL

Webhook URL формируется как:

```
https://{domain}/rest/{member_id}/{access_token}/
```

И автоматически сохраняется в `tenant_bitrix24_settings.webhook_url`

### Логика поиска пользователя

1. **Поиск по `(tenant_id, bitrix24_user_id)`** - основной метод
2. **Поиск по `(tenant_id, email)`** - если первый не нашёл
3. **Создание нового пользователя** - если ничего не найдено

### Настройка OAuth в Bitrix24

1. Зайдите в свой Bitrix24 портал
2. Перейдите в **Приложения → Разработчикам → Другое → Локальное приложение**
3. Нажмите **Создать приложение**
4. Заполните:
    - **Название**: Calendar AI
    - **URL вашего обработчика**: `https://ваш-домен.ru/auth/bitrix24/callback`
    - **Права доступа**:
        - `user` - информация о пользователе
        - `crm` - работа с CRM (контакты, сделки)
        - `catalog` - работа с каталогом товаров
5. Сохраните и получите:
    - **Код приложения (CLIENT_ID)**: local.XXXXXXXXX.XXXXXXXXX
    - **Ключ приложения (CLIENT_SECRET)**: XXXXXXXXXXXXXXXXXXXXXXXXXX

### Настройка в административной панели

1. Зайдите в **Настройки → Bitrix24**
2. Заполните секцию "OAuth настройки":
    - **OAuth Client ID**: `local.XXXXXXXXX.XXXXXXXXX`
    - **OAuth Client Secret**: `XXXXXXXXXXXXXXXXXXXXXXXXXX`
3. Нажмите **"Сохранить"**

⚠️ **ВАЖНО**: Все чувствительные данные автоматически шифруются при сохранении в базу данных!

### Безопасность

-   ✅ **CSRF защита** через state parameter
-   ✅ **Уникальность доменов** - один tenant на один портал
-   ✅ **Индексы** для быстрого поиска
-   ✅ **Логирование** всех OAuth операций
-   ✅ **Валидация** всех входных данных
-   ✅ **Шифрование credentials** - CLIENT_ID и CLIENT_SECRET хранятся в БД зашифрованно
-   ✅ **Мульти-тенантность** - каждый тенант имеет свои OAuth credentials

---

## Синхронизация товаров с Bitrix24

### Обзор

Реализована двусторонняя синхронизация товаров и услуг между локальной базой данных и торговым каталогом Битрикс24.

### 1. Синхронизация локальных сервисов → Битрикс24

При создании или обновлении сервиса:

-   Если у сервиса нет `bitrix24_product_id`:
    -   Ищется товар в Битрикс24 по названию
    -   Если найден - сохраняется ID
    -   Если не найден - создается новый товар
-   Если `bitrix24_product_id` уже есть:
    -   Обновляется существующий товар (название, активность)

**События:**

-   `ServiceCreated` → `SendServiceToBitrix24` → `SyncProductToBitrix24Job`
-   `ServiceUpdated` → `SendServiceToBitrix24` → `SyncProductToBitrix24Job`

### 2. Синхронизация Битрикс24 → локальные сервисы

Запускается:

-   Автоматически каждую полночь (`Schedule::command('bitrix24:sync-products')`)
-   Вручную через админ-панель (кнопка "Синхронизировать товары")
-   Вручную через CLI: `php artisan bitrix24:sync-products --tenant=ID`

**Логика:**

-   Получает все товары из каталога Битрикс24 (порциями по 50)
-   Для каждого товара:
    -   Ищет локальный сервис по `bitrix24_product_id`
    -   Если найден - обновляет при наличии расхождений (название, активность)
    -   Если не найден - создает новый сервис с `type=product`

**Job:** `SyncProductsFromBitrix24Job`

### 3. Добавление товаров в сделку при создании брони

При создании сделки в Битрикс24 (событие `BookingCreated`):

**Процесс:**

1. **Проверка товаров**: Перед созданием сделки проверяется наличие `bitrix24_product_id` у всех услуг
2. **Синхронная синхронизация**: Если хотя бы у одной услуги нет ID - запускается `SyncProductToBitrix24Job::runSync()` и выполняется **синхронно** (с ожиданием)
3. **Проверка контакта**: Проверяется наличие контакта по `crm_contact_id` клиента:
    - Если есть - используется существующий
    - Если нет или не найден - создается новый
4. **Создание сделки**: Через Pipeline создается сделка
5. **Добавление товаров**: После создания сделки автоматически добавляются товарные позиции через `crm.deal.productrows.set`

**Для каждой услуги:**

-   Если есть `bitrix24_product_id` - добавляется как товар из каталога
-   Если нет - добавляется как кастомная позиция (PRODUCT_ID=0)

### 4. Обновление товаров при обновлении брони

При обновлении брони (событие `BookingUpdated`):

**Процесс:**

1. **Проверка товаров**: Аналогично созданию - проверяется и синхронизируется каждая услуга
2. **Обновление сделки**: Обновляются основные поля сделки (название, сумма, даты, комментарий)
3. **Обновление товарных позиций**: Полностью перезаписываются товарные позиции через `crm.deal.productrows.set`

### API методы Bitrix24ApiClient

```php
// Получить список товаров
listProducts(int $iblockId, array $filter = [], int $start = 0): array

// Создать товар
createProduct(int $iblockId, array $fields): ?int

// Обновить товар
updateProduct(int $productId, array $fields): bool

// Получить товар по ID
getProduct(int $productId): ?array

// Установить товарные позиции для сделки
setDealProducts(int $dealId, array $rows): bool

// Универсальный вызов любого метода API
call(string $method, array $params = []): array
```

### Настройка синхронизации товаров

1. Зайти в админ-панель Битрикс24
2. Перейти в CRM > Товары > Настройки каталога
3. Скопировать ID каталога (например, `23`)
4. В приложении: Settings > Bitrix24
5. Указать `catalog_iblock_id = 23`
6. Сохранить настройки
7. Нажать кнопку "Синхронизировать товары"

---

## CRM Pipeline

### Автоматическая синхронизация

#### При создании клиента:

```php
// Events/ClientCreated.php
ClientCreated::dispatch($client);
  ↓
// Listeners/CRM/SyncClientToBitrix24.php
SyncContactToBitrix24Job::dispatch($client->id, $client->tenant_id);
  ↓
// Jobs/CRM/SyncContactToBitrix24Job.php
- Проверяет настройки тенанта
- Создаёт контакт в Bitrix24
- Сохраняет crm_contact_id в базе
```

#### При обновлении клиента:

```php
// Events/ClientUpdated.php
ClientUpdated::dispatch($client);
  ↓
// Listeners/CRM/UpdateClientInBitrix24.php
UpdateContactInBitrix24Job::dispatch($client->id, $client->tenant_id);
  ↓
// Jobs/CRM/UpdateContactInBitrix24Job.php
- Проверяет наличие crm_contact_id
- Обновляет контакт через crm.contact.update
```

#### При создании бронирования:

```php
// Events/BookingCreated.php
BookingCreated::dispatch($booking);
  ↓
// Listeners/CRM/SendBookingToBitrix24.php
ProcessBookingInBitrix24Job::dispatch($booking->id, $booking->tenant_id);
  ↓
// Jobs/CRM/ProcessBookingInBitrix24Job.php (Pipeline)
Step 1: Проверка и синхронизация товаров
Step 2: Find Duplicate Contacts
Step 3: Create/Retrieve Contact → Сохраняет crm_contact_id
Step 4: Find Existing Deals
Step 5: Create Deal → Сохраняет crm_deal_id
Step 6: Add Products to Deal
```

#### При обновлении бронирования:

```php
// Events/BookingUpdated.php
BookingUpdated::dispatch($booking);
  ↓
// Listeners/CRM/UpdateBookingInBitrix24.php
UpdateBookingInBitrix24Job::dispatch($booking->id, $booking->tenant_id);
  ↓
// Jobs/CRM/UpdateBookingInBitrix24Job.php
Step 1: Проверка и синхронизация товаров
Step 2: Update Deal Fields
Step 3: Update Deal Products
```

### Обзор Pipeline Pattern

Интеграция с Bitrix24 построена на **Pipeline Pattern**:

```
Event: BookingCreated
    ↓
Listener: SendBookingToBitrix24
    ↓
Job: ProcessBookingInBitrix24Job (async)
    ↓
Pipeline: 6 Steps
    ├─ Step 1: Ensure Products Are Synced (synchronous)
    ├─ Step 2: Find Duplicate Contacts (crm.duplicate.findbycomm)
    ├─ Step 3: Create/Retrieve Contact (crm.contact.add)
    ├─ Step 4: Find Existing Deals (crm.deal.list)
    ├─ Step 5: Create Deal (crm.deal.add)
    └─ Step 6: Add Products to Deal (crm.deal.productrows.set)
```

### Компоненты

#### 1. DTO (Data Transfer Objects)

```php
// app/Infrastructure/CRM/Bitrix24/DTO/

ContactData     - Иммутабельный объект с данными контакта
DealData        - Иммутабельный объект с данными сделки
PipelineContext - Контекст, передаваемый между шагами Pipeline
```

#### 2. Builders (Fluent API)

```php
// app/Infrastructure/CRM/Bitrix24/Builders/

$contactData = (new ContactDataBuilder())
    ->setName('Иван')
    ->setLastName('Иванов')
    ->setPhone('+79991234567')
    ->setEmail('ivan@example.com')
    ->applyDefaults()  // Применяет значения из config
    ->build();

$dealData = (new DealDataBuilder())
    ->setTitle('Бронирование #123')
    ->setOpportunity(5000.00)
    ->addContactId(42)
    ->applyDefaults()
    ->build();
```

#### 3. Pipeline Steps

```php
// app/Infrastructure/CRM/Pipeline/Steps/

FindDuplicateContactsStep     - Поиск дубликатов по телефону
CreateOrRetrieveContactStep   - Создание контакта (если нужно)
FindExistingDealsStep         - Поиск существующих сделок
CreateDealStep                - Создание новой сделки
```

#### 4. Filters (Strategy Pattern)

```php
// app/Infrastructure/CRM/Bitrix24/Filters/

$filter = (new DealFilterBuilder())
    ->byContactIds([42, 43])
    ->onlyOpen()
    ->byCategoryId(0)
    ->build();
```

### Настройка Bitrix24

```bash
# В админке: Настройки → Bitrix24

# Webhook URL (необязательно, если используется OAuth)
webhook_url = https://your-domain.bitrix24.ru/rest/1/webhook_code/

# OAuth credentials (обязательно для авторизации)
oauth_client_id = local.XXXXXXXXX.XXXXXXXXX
oauth_client_secret = XXXXXXXXXXXXXXXXXXXXXXXXXX

# Настройки контакта
contact_type_id = CLIENT
contact_source_id = WEBFORM

# Настройки сделки
deal_category_id = 0
deal_stage_id = NEW
deal_currency_id = RUB

# ID торгового каталога
catalog_iblock_id = 23
```

---

## 🔄 WebSocket Integration (Laravel Reverb)

### Настройка

```bash
# .env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST="127.0.0.1"
REVERB_PORT=8080
REVERB_SCHEME=http

# Frontend
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

### События

```php
// События бронирований
BookingCreated   - Новое бронирование создано
BookingUpdated   - Бронирование обновлено
BookingDeleted   - Бронирование удалено

// События услуг
ServiceCreated   - Новая услуга создана
ServiceUpdated   - Услуга обновлена
ServiceDeleted   - Услуга удалена
```

### Подписка на события (Vue 3)

```javascript
// resources/js/Pages/Calendar/Index.vue
window.Echo.private(`tenant.${tenantId}`)
    .listen("BookingCreated", (event) => {
        console.log("New booking:", event.booking);
        // Обновить календарь
    })
    .listen("BookingUpdated", (event) => {
        // Обновить данные
    });
```

---

## 🗄️ База данных

### Основные таблицы

```sql
tenants          - Мультитенантность
  ├─ bitrix24_domain (string, nullable, unique)
  └─ bitrix24_member_id (string, nullable)

tenant_bitrix24_settings - Настройки Bitrix24 для каждого тенанта
  ├─ webhook_url (text, encrypted)
  ├─ oauth_client_id (text, encrypted)
  ├─ oauth_client_secret (text, encrypted)
  ├─ catalog_iblock_id (integer, nullable)
  └─ [...другие CRM настройки]

users            - Пользователи (сотрудники)
  ├─ bitrix24_user_id (string, nullable, indexed)
  ├─ is_super_admin (boolean, default: false)
  └─ is_admin (boolean, default: false)

clients          - Клиенты
  └─ crm_contact_id (integer, nullable)

services         - Услуги
  ├─ bitrix24_product_id (string, nullable, indexed)
  └─ type (enum: 'product', 'service', default: 'service')

workplaces       - Рабочие места
bookings         - Бронирования
  └─ crm_deal_id (integer, nullable)

statuses         - Статусы бронирований
employee_vacations - Отпуска сотрудников
```

### Миграции

```bash
php artisan migrate           # Запустить миграции
php artisan migrate:fresh     # Пересоздать БД
php artisan migrate:rollback  # Откатить
php artisan db:seed           # Заполнить тестовыми данными
```

---

## 🎨 Frontend (Vue 3 + Inertia.js)

### Технологии

-   **Vue 3** (Composition API)
-   **Inertia.js** (SSR-like без API)
-   **Tailwind CSS** (Styling)
-   **FullCalendar** (Календарь)
-   **Laravel Echo** (WebSocket)

### Структура

```
resources/js/
├── Components/          # Переиспользуемые компоненты
├── Layouts/            # Layout шаблоны
└── Pages/              # Страницы (Inertia)
    ├── Calendar/       # Календарь
    ├── Booking/        # Бронирования
    ├── Client/         # Клиенты
    ├── Service/        # Услуги
    └── Settings/       # Настройки (включая Bitrix24)
```

---

## 🔐 Авторизация

### Policies

```php
BookingPolicy  - Политики доступа к бронированиям
ClientPolicy   - Политики доступа к клиентам
ServicePolicy  - Политики доступа к услугам
TenantPolicy   - Мультитенантность
UserPolicy     - Политики доступа к пользователям
```

### Middleware

```php
CheckTenantAccess  - Проверка доступа к tenant
```

### Роли пользователей

```php
// Супер-администратор (полный доступ ко всем тенантам)
$user->is_super_admin = true;

// Администратор тенанта (доступ только к своему тенанту)
$user->is_admin = true;

// Обычный пользователь (сотрудник)
$user->is_admin = false;
```

---

## 🛠️ Административные команды

### Создание супер-администратора

```bash
# Интерактивный режим
php artisan admin:create-super

# С параметрами
php artisan admin:create-super \
    --name="Super Admin" \
    --email="admin@example.com" \
    --password="securepass123"
```

**Что делает команда:**

-   Проверяет наличие существующего супер-администратора (с подтверждением)
-   Валидирует email (уникальность, формат)
-   Валидирует пароль (минимум 8 символов, подтверждение)
-   Создает тенант "superadmin" (если не существует)
-   Создает пользователя с флагами:
    -   `is_super_admin = true`
    -   `is_admin = true`
-   Выводит красивую таблицу с данными созданного пользователя

### Синхронизация товаров Bitrix24

```bash
# Синхронизировать товары для всех тенантов
php artisan bitrix24:sync-products

# Синхронизировать товары для конкретного тенанта
php artisan bitrix24:sync-products --tenant=1
```

---

## 🧪 Тестирование

```bash
# Запустить тесты
php artisan test

# Запустить конкретный тест
php artisan test --filter BookingTest

# С покрытием кода
php artisan test --coverage
```

---

## 📦 Deployment

### Production настройки

```bash
# .env (production)
APP_ENV=production
APP_DEBUG=false
QUEUE_CONNECTION=redis
BROADCAST_CONNECTION=reverb

# Оптимизация
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# Supervisor для queue worker
sudo nano /etc/supervisor/conf.d/calendar-worker.conf
```

### Supervisor config

```ini
[program:calendar-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/worker.log
```

---

## 🛠️ Полезные команды

```bash
# Очистка кеша
php artisan optimize:clear
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Queue
php artisan queue:work           # Запустить worker
php artisan queue:restart        # Перезапустить workers
php artisan queue:failed         # Показать failed jobs
php artisan queue:retry all      # Повторить все failed jobs

# События
php artisan event:list           # Список всех событий

# Reverb
php artisan reverb:start         # Запустить WebSocket сервер
php artisan reverb:restart       # Перезапустить сервер

# Администрирование
php artisan admin:create-super   # Создать супер-администратора

# Bitrix24
php artisan bitrix24:sync-products [--tenant=ID]  # Синхронизировать товары
```

---

## 📝 API примеры

### Создание бронирования

```php
POST /api/bookings

{
    "client_id": 1,
    "user_id": 2,
    "workplace_id": 1,
    "service_ids": [1, 2],
    "status_id": 1,
    "start_time": "2025-11-04 10:00:00",
    "end_time": "2025-11-04 11:00:00",
    "note": "Примечание"
}
```

### Обновление бронирования

```php
PUT /api/bookings/{id}

{
    "status_id": 2,
    "note": "Обновленное примечание"
}
```

---

## 🐛 Troubleshooting

### Queue не обрабатывает задачи

```bash
# Проверить статус
php artisan queue:work --once

# Проверить failed jobs
php artisan queue:failed

# Повторить failed jobs
php artisan queue:retry all
```

### WebSocket не подключается

```bash
# Проверить Reverb
php artisan reverb:start

# Проверить настройки в .env
echo $REVERB_APP_KEY
```

### Ошибки CRM интеграции

```bash
# Проверить логи
tail -f storage/logs/laravel.log | grep "🚀\|❌"

# Проверить конфигурацию Bitrix24 для тенанта
php artisan tinker
>>> TenantBitrix24Settings::where('tenant_id', 1)->first()
```

### OAuth авторизация не работает

```bash
# Проверить OAuth credentials в админке
# Настройки → Bitrix24 → OAuth настройки

# Проверить callback URL в Bitrix24 приложении
# Должен быть: https://ваш-домен.ru/auth/bitrix24/callback

# Проверить логи
tail -f storage/logs/laravel.log | grep "OAuth"
```

### Товары не синхронизируются

```bash
# Проверить catalog_iblock_id
php artisan tinker
>>> TenantBitrix24Settings::where('tenant_id', 1)->first()->catalog_iblock_id

# Запустить синхронизацию вручную
php artisan bitrix24:sync-products --tenant=1

# Проверить логи
tail -f storage/logs/laravel.log | grep "product\|Product"
```

---

## 📄 Лицензия

MIT License

---

## 👥 Контакты

Для вопросов и предложений обращайтесь к команде разработки.
