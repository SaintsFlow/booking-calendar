<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Service;
use App\Models\Status;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workplace;
use App\Policies\BookingPolicy;
use App\Policies\ClientPolicy;
use App\Policies\ServicePolicy;
use App\Policies\StatusPolicy;
use App\Policies\TenantPolicy;
use App\Policies\UserPolicy;
use App\Policies\WorkplacePolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

// Events
use App\Events\Booking\BookingCreated;
use App\Events\Booking\BookingUpdated;
use App\Events\Booking\BookingDeleted;
use App\Events\Service\ServiceCreated;
use App\Events\Service\ServiceUpdated;
use App\Events\Service\ServiceDeleted;

// Listeners
use App\Listeners\CRM\SendBookingUpdatedToCrm;
use App\Listeners\CRM\SendBookingDeletedToCrm;
use App\Listeners\CRM\SendServiceCreatedToCrm;
use App\Listeners\CRM\SendServiceUpdatedToCrm;
use App\Listeners\CRM\SendServiceDeletedToCrm;
use App\Listeners\CRM\SendBookingToBitrix24;
use App\Listeners\CRM\SendBookingUpdateToBitrix24;
use App\Listeners\CRM\SendServiceToBitrix24;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Tenant::class => TenantPolicy::class,
        User::class => UserPolicy::class,
        Booking::class => BookingPolicy::class,
        Client::class => ClientPolicy::class,
        Service::class => ServicePolicy::class,
        Status::class => StatusPolicy::class,
        Workplace::class => WorkplacePolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Регистрируем политики
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Регистрируем Event Listeners для CRM интеграции (Generic Webhook)
        //        Event::listen(BookingUpdated::class, SendBookingUpdatedToCrm::class);
        //        Event::listen(BookingDeleted::class, SendBookingDeletedToCrm::class);
        //        Event::listen(ServiceCreated::class, SendServiceCreatedToCrm::class);
        //        Event::listen(ServiceUpdated::class, SendServiceUpdatedToCrm::class);
        //        Event::listen(ServiceDeleted::class, SendServiceDeletedToCrm::class);

        // Регистрируем Event Listeners для Bitrix24 интеграции
        Event::listen(BookingCreated::class, SendBookingToBitrix24::class);
        Event::listen(BookingUpdated::class, SendBookingUpdateToBitrix24::class);
        Event::listen(ServiceCreated::class, SendServiceToBitrix24::class);
        Event::listen(ServiceUpdated::class, SendServiceToBitrix24::class);
    }
}
