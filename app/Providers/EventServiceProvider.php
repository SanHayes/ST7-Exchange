<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Weixin\WeixinExtendSocialite;
use SocialiteProviders\WeixinWeb\WeixinWebExtendSocialite;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        // access_token 生成以后清除旧的 token ，然后记录登录时间和日期
        'Laravel\Passport\Events\AccessTokenCreated' => [
//            'App\Listeners\RevokeOldTokens',
            'App\Listeners\LogSuccessfulLogin',
        ],
        // refresh_token 生成以后删除已吊销的 token
        'Laravel\Passport\Events\RefreshTokenCreated' => [
//            'App\Listeners\PruneOldTokens',
        ],
        SocialiteWasCalled::class => [
            // ... other providers
            WeixinExtendSocialite::class.'@handle', // 微信API登录
            WeixinWebExtendSocialite::class.'@handle', // 微信 PC 登录
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
