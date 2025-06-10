<?php

declare(strict_types = 1);

namespace App\Providers;

use App\Models\Campaign;
use App\Models\EmailList;
use App\Models\Subscriber;
use App\Models\Template;
use App\Policies\CampaignPolicy;
use App\Policies\EmailListPolicy;
use App\Policies\SubscriberPolicy;
use App\Policies\TemplatePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        EmailList::class  => EmailListPolicy::class,
        Subscriber::class => SubscriberPolicy::class,
        Template::class   => TemplatePolicy::class,
        Campaign::class   => CampaignPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
