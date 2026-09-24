<?php

namespace Rechnerei\Inquiries;

use Illuminate\Mail\Events\MessageSent;
use Rechnerei\Inquiries\Listeners\ForwardFormSubmissionToRechnerei;
use Rechnerei\Inquiries\Listeners\ForwardMailToRechnerei;
use Statamic\Events\FormSubmitted;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $routes = [
        'cp' => __DIR__ . '/../routes/cp.php',
    ];

    protected $listen = [
        MessageSent::class => [
            ForwardMailToRechnerei::class,
        ],
        FormSubmitted::class => [
            ForwardFormSubmissionToRechnerei::class,
        ],
    ];

    protected $vite = [
        'publicDirectory' => 'dist',
        'input' => [
            'resources/js/cp.js',
        ],
    ];

    public function bootAddon(): void
    {
        Permission::register('configure rechnerei inquiries', function ($permission) {
            $permission->label('Configure Rechnerei Inquiries')->group('Rechnerei Inquiries');
        });

        Nav::extend(function ($nav) {
            $nav->tools('Rechnerei Inquiries')
                ->route('rechnerei-inquiries.index')
                ->icon('mail')
                ->can('configure rechnerei inquiries');
        });
    }
}
