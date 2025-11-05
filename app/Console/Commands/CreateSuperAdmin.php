<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateSuperAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create-super 
                            {--name= : Имя администратора}
                            {--email= : Email администратора}
                            {--password= : Пароль администратора}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Создать первого супер-администратора системы';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Создание супер-администратора');
        $this->newLine();

        // Проверяем, есть ли уже супер-админ
        $existingSuperAdmin = User::where('is_super_admin', true)->first();
        if ($existingSuperAdmin) {
            $this->warn('⚠️  Супер-администратор уже существует:');
            $this->line("   Email: {$existingSuperAdmin->email}");
            $this->line("   Имя: {$existingSuperAdmin->name}");

            if (!$this->confirm('Создать ещё одного супер-администратора?', false)) {
                $this->info('Отменено.');
                return 0;
            }
            $this->newLine();
        }

        // Получаем данные из опций или запрашиваем интерактивно
        $name = $this->option('name') ?: $this->ask('Имя администратора', 'Super Admin');
        $email = $this->option('email') ?: $this->ask('Email администратора');
        $password = $this->option('password');

        // Валидация email
        $validator = Validator::make(['email' => $email], [
            'email' => 'required|email|unique:users,email',
        ]);

        if ($validator->fails()) {
            $this->error('❌ Ошибка валидации:');
            foreach ($validator->errors()->all() as $error) {
                $this->line("   • {$error}");
            }
            return 1;
        }

        // Запрашиваем пароль, если не передан
        if (!$password) {
            $password = $this->secret('Пароль администратора');
            $passwordConfirm = $this->secret('Подтвердите пароль');

            if ($password !== $passwordConfirm) {
                $this->error('❌ Пароли не совпадают!');
                return 1;
            }
        }

        // Валидация пароля
        if (strlen($password) < 8) {
            $this->error('❌ Пароль должен содержать минимум 8 символов!');
            return 1;
        }

        $this->newLine();
        $this->info('Создание супер-администратора...');

        try {
            // Создаём специального тенанта для супер-админа (если нужно)
            $tenant = Tenant::firstOrCreate(
                ['domain' => 'superadmin'],
                [
                    'name' => 'SuperAdmin Tenant',
                    'subscription_status' => 'active',
                    'trial_ends_at' => null,
                ]
            );

            // Создаём пользователя
            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'is_super_admin' => true,
                'is_admin' => true,
            ]);

            $this->newLine();
            $this->info('✅ Супер-администратор успешно создан!');
            $this->newLine();
            $this->table(
                ['Поле', 'Значение'],
                [
                    ['ID', $user->id],
                    ['Имя', $user->name],
                    ['Email', $user->email],
                    ['Тенант', $tenant->name],
                    ['Супер-админ', '✓'],
                    ['Админ', '✓'],
                ]
            );
            $this->newLine();
            $this->comment('💡 Используйте эти данные для входа в систему');

            return 0;
        } catch (\Throwable $e) {
            $this->error('❌ Ошибка при создании супер-администратора:');
            $this->line("   {$e->getMessage()}");
            return 1;
        }
    }
}
