<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        \App\Events\TransactionPaymentAdded::class => [
            \App\Listeners\AddAccountTransaction::class,
        ],

        \App\Events\TransactionPaymentUpdated::class => [
            \App\Listeners\UpdateAccountTransaction::class,
        ],

        \App\Events\TransactionPaymentDeleted::class => [
            \App\Listeners\DeleteAccountTransaction::class,
        ],
    ];

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }
}
