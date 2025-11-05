<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workplace;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Создаём супер-админа
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@calendar-ai.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        echo "✓ Создан супер-админ: {$superAdmin->email}\n";

        // Создаём тестовый тенант
        $tenant = Tenant::create([
            'name' => 'Салон красоты "Примула"',
            'domain' => 'primula.calendar-ai.com',
            'subscription_status' => 'active',
        ]);

        echo "✓ Создан тенант: {$tenant->name}\n";

        // Создаём базовые статусы для тенанта
        $tenant->createDefaultStatuses();
        echo "✓ Созданы базовые статусы\n";

        // Создаём администратора тенанта
        $admin = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Мария Иванова',
            'email' => 'admin@primula.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
            'phone' => '+7 (999) 123-45-67',
        ]);

        echo "✓ Создан администратор: {$admin->email}\n";

        // Создаём менеджера
        $manager = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Анна Петрова',
            'email' => 'manager@primula.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'is_active' => true,
            'phone' => '+7 (999) 234-56-78',
        ]);

        echo "✓ Создан менеджер: {$manager->email}\n";

        // Создаём сотрудников
        $employee1 = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Елена Смирнова',
            'email' => 'elena@primula.com',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'is_active' => true,
            'phone' => '+7 (999) 345-67-89',
        ]);

        $employee2 = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Ольга Кузнецова',
            'email' => 'olga@primula.com',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'is_active' => true,
            'phone' => '+7 (999) 456-78-90',
        ]);

        echo "✓ Созданы сотрудники\n";

        // Создаём места работы
        $workplace1 = Workplace::create([
            'tenant_id' => $tenant->id,
            'name' => 'Основной зал',
            'address' => 'ул. Ленина, 10',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $workplace2 = Workplace::create([
            'tenant_id' => $tenant->id,
            'name' => 'VIP зал',
            'address' => 'ул. Ленина, 10, 2 этаж',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        echo "✓ Созданы места работы\n";

        // Привязываем сотрудников к местам работы
        $employee1->workplaces()->attach([$workplace1->id, $workplace2->id]);
        $employee2->workplaces()->attach([$workplace1->id]);

        // Создаём услуги
        $services = [
            [
                'tenant_id' => $tenant->id,
                'workplace_id' => null, // доступна везде
                'name' => 'Стрижка женская',
                'description' => 'Стрижка любой сложности',
                'duration_minutes' => 60,
                'price' => 1500,
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenant->id,
                'workplace_id' => null,
                'name' => 'Окрашивание волос',
                'description' => 'Окрашивание в один тон',
                'duration_minutes' => 120,
                'price' => 3000,
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenant->id,
                'workplace_id' => null,
                'name' => 'Маникюр',
                'description' => 'Классический маникюр с покрытием',
                'duration_minutes' => 90,
                'price' => 1200,
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenant->id,
                'workplace_id' => $workplace2->id, // только VIP зал
                'name' => 'СПА-уход для волос',
                'description' => 'Комплексный уход премиум класса',
                'duration_minutes' => 180,
                'price' => 5000,
                'is_active' => true,
            ],
        ];

        foreach ($services as $serviceData) {
            Service::create($serviceData);
        }

        echo "✓ Созданы услуги\n";

        // Создаём клиентов
        $clients = [
            [
                'tenant_id' => $tenant->id,
                'first_name' => 'Екатерина',
                'last_name' => 'Волкова',
                'phone' => '+7 (999) 111-22-33',
                'email' => 'ekaterina@example.com',
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenant->id,
                'first_name' => 'Наталья',
                'last_name' => 'Соколова',
                'phone' => '+7 (999) 222-33-44',
                'email' => 'natalia@example.com',
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenant->id,
                'first_name' => 'Ирина',
                'last_name' => 'Морозова',
                'phone' => '+7 (999) 333-44-55',
                'email' => null,
                'is_active' => true,
            ],
        ];

        foreach ($clients as $clientData) {
            Client::create($clientData);
        }

        echo "✓ Созданы клиенты\n";

        echo "\n=== Данные для входа ===\n";
        echo "Супер-админ: admin@calendar-ai.com / password\n";
        echo "Администратор: admin@primula.com / password\n";
        echo "Менеджер: manager@primula.com / password\n";
        echo "Сотрудник 1: elena@primula.com / password\n";
        echo "Сотрудник 2: olga@primula.com / password\n";
    }
}
