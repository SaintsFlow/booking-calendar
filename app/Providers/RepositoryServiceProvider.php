<?php

namespace App\Providers;

use App\Domain\Booking\Contracts\BookingRepositoryInterface;
use App\Infrastructure\Repositories\BookingRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Регистрация привязок репозиториев
     */
    public function register(): void
    {
        // Booking Repository
        $this->app->bind(
            BookingRepositoryInterface::class,
            BookingRepository::class
        );

        // Здесь будут добавляться другие репозитории:
        // $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        // $this->app->bind(ClientRepositoryInterface::class, ClientRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
