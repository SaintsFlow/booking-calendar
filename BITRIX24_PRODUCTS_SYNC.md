# Синхронизация товаров/услуг с Битрикс24

## Обзор

Реализована двусторонняя синхронизация товаров и услуг между локальной базой данных и торговым каталогом Битрикс24.

## Новые поля

### Таблица `services`

-   `bitrix24_product_id` (string, nullable, indexed) - ID товара в каталоге Битрикс24
-   `type` (enum: 'product', 'service', default: 'service') - Тип: товар или услуга

### Таблица `tenant_bitrix24_settings`

-   `catalog_iblock_id` (integer, nullable) - ID информационного блока торгового каталога

## Функциональность

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

**Новая логика (ВАЖНО!):**

1. **Проверка товаров:** Перед созданием сделки проверяется наличие `bitrix24_product_id` у всех услуг
2. **Синхронная синхронизация:** Если хотя бы у одной услуги нет ID - запускается `SyncProductToBitrix24Job::runSync()` и выполняется **синхронно** (с ожиданием)
3. **Проверка контакта:** Проверяется наличие контакта по `crm_contact_id` клиента:
    - Если есть - используется существующий
    - Если нет или не найден - создается новый
4. **Создание сделки:** Через Pipeline создается сделка
5. **Добавление товаров:** После создания сделки автоматически добавляются товарные позиции через `crm.deal.productrows.set`

**Для каждой услуги:**

-   Если есть `bitrix24_product_id` - добавляется как товар из каталога
-   Если нет - добавляется как кастомная позиция (PRODUCT_ID=0)

**Метод:** `ProcessBookingInBitrix24Job::addProductsToDeal()`

### 4. Обновление товаров при обновлении брони

При обновлении брони (событие `BookingUpdated`):

**Полный процесс:**

1. **Проверка товаров:** Аналогично созданию - проверяется и синхронизируется каждая услуга
2. **Обновление сделки:** Обновляются основные поля сделки (название, сумма, даты, комментарий)
3. **Обновление товарных позиций:** Полностью перезаписываются товарные позиции через `crm.deal.productrows.set`

**Метод:** `UpdateBookingInBitrix24Job::updateDealProducts()`

**Job:** `UpdateBookingInBitrix24Job`

## API методы Bitrix24ApiClient

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

## Новые методы Job

```php
// SyncProductToBitrix24Job
SyncProductToBitrix24Job::runSync(Service $service): void
// Синхронная синхронизация товара (с ожиданием завершения)

// ProcessBookingInBitrix24Job
ensureProductsAreSynced(Booking $booking, TenantBitrix24Settings $settings): void
// Проверить и синхронизировать все товары перед созданием брони

ensureContactExists(Bitrix24ApiClient $apiClient, Booking $booking, TenantBitrix24Settings $settings): ?int
// Проверить существование контакта по crm_contact_id или создать новый

addProductsToDeal(Bitrix24ApiClient $apiClient, int $dealId, Booking $booking, TenantBitrix24Settings $settings): void
// Добавить товарные позиции в сделку

// UpdateBookingInBitrix24Job
ensureProductsAreSynced(Booking $booking, TenantBitrix24Settings $settings): void
// Проверить и синхронизировать все товары перед обновлением брони

updateDealProducts(Bitrix24ApiClient $apiClient, Booking $booking, TenantBitrix24Settings $settings): void
// Обновить товарные позиции в сделке
```

## Админ-панель

**Страница:** Settings > Bitrix24

**Новые поля:**

-   **ID торгового каталога (IBlock)** - обязательно для синхронизации товаров
    -   Получить можно через `catalog.catalog.list`
    -   Пример: `23`

**Новые кнопки:**

-   **Синхронизировать товары** - запускает `SyncProductsFromBitrix24Job`
    -   Доступна только если указан `catalog_iblock_id`
    -   Показывает результат синхронизации

## Команды Artisan

```bash
# Синхронизировать товары для всех тенантов
php artisan bitrix24:sync-products

# Синхронизировать товары для конкретного тенанта
php artisan bitrix24:sync-products --tenant=1
```

## Расписание (Schedule)

```php
// routes/console.php
Schedule::command('bitrix24:sync-products')->dailyAt('00:00');
```

Запускается автоматически каждую полночь для всех тенантов с:

-   `enabled = true`
-   `webhook_url` настроен
-   `catalog_iblock_id` указан

## Структура товарных позиций в сделке

```php
[
    'PRODUCT_ID' => 123,              // ID товара из каталога (0 если не из каталога)
    'PRODUCT_NAME' => 'Стрижка',      // Название услуги
    'PRICE' => 1500.00,                // Цена из брони
    'QUANTITY' => 1,                   // Всегда 1 для услуг
]
```

## Логирование

Все операции логируются с контекстом:

```php
// Синхронизация товаров
Log::info('Service sync job dispatched', [
    'service_id' => $service->id,
    'event' => 'created',
]);

Log::info('Service not synced, running sync now', [
    'service_id' => $service->id,
    'service_name' => $service->name,
]);

// Контакты
Log::info('Using existing contact', [
    'contact_id' => $client->crm_contact_id,
    'client_id' => $client->id,
]);

Log::info('Creating new contact', [
    'client_id' => $client->id,
    'client_name' => $client->name,
]);

// Товарные позиции
Log::info('Successfully added products to deal', [
    'deal_id' => $dealId,
    'booking_id' => $booking->id,
    'products_count' => count($productRows),
]);

Log::info('Successfully updated products in deal', [
    'deal_id' => $booking->crm_deal_id,
    'booking_id' => $booking->id,
    'products_count' => count($productRows),
]);
```

## Обработка ошибок

-   Все Jobs имеют `tries = 3` и `backoff = 60` секунд
-   Ошибки добавления товаров в сделку НЕ прерывают создание/обновление сделки
-   При отсутствии `catalog_iblock_id` операции пропускаются с логом
-   **Синхронизация товаров выполняется синхронно** - бронь не будет создана/обновлена, пока все товары не синхронизируются
-   **Проверка контакта** - если контакт не найден по ID, создается новый автоматически
-   **Товары без bitrix24_product_id** добавляются как кастомные позиции (PRODUCT_ID=0)

## Пример использования

### 1. Настройка

1. Зайти в админ-панель Битрикс24
2. Перейти в CRM > Товары > Настройки каталога
3. Скопировать ID каталога (например, `23`)
4. В приложении: Settings > Bitrix24
5. Указать `catalog_iblock_id = 23`
6. Сохранить настройки

### 2. Первая синхронизация

```bash
php artisan bitrix24:sync-products --tenant=1
```

Или через админ-панель: кнопка "Синхронизировать товары"

### 3. Создание сервиса

```php
$service = Service::create([
    'name' => 'Стрижка',
    'price' => 1500,
    'duration_minutes' => 30,
    'type' => 'service',
    'tenant_id' => 1,
]);

// Автоматически отправится событие ServiceCreated
// Job найдет или создаст товар в Битрикс24
// И сохранит bitrix24_product_id
```

### 4. Создание брони

```php
$booking = Booking::create([/* ... */]);
$booking->services()->attach($serviceId);

// Автоматически:
// 1. Проверяются все товары - если нет bitrix24_product_id, синхронизируются СИНХРОННО
// 2. Проверяется контакт по crm_contact_id клиента
// 3. Создается сделка в Битрикс24
// 4. Добавляются товарные позиции для всех услуг
```

### 5. Обновление брони

```php
$booking->update(['total_price' => 2000]);
$booking->services()->sync([1, 2, 3]);

// Автоматически:
// 1. Проверяются все товары - если нет bitrix24_product_id, синхронизируются СИНХРОННО
// 2. Обновляется сделка в Битрикс24 (название, сумма, даты)
// 3. ПОЛНОСТЬЮ ПЕРЕЗАПИСЫВАЮТСЯ товарные позиции
```

## Ограничения

-   Цены товаров синхронизируются только при создании брони (не из каталога)
-   Единица измерения всегда 1
-   Не синхронизируются остатки и резервы
-   Удаление товаров не поддерживается (только деактивация)

## Миграции

```bash
php artisan migrate
```

Применит:

-   `add_bitrix24_fields_to_services_table`
-   `add_catalog_iblock_id_to_tenant_bitrix24_settings_table`
