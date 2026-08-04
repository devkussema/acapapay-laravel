<?php

namespace AcapaPay\Laravel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\VerifyCsrfToken;

class AcapaPayServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Força a paragem se o framework base não for o Laravel
        if (! $this->app instanceof \Illuminate\Foundation\Application) {
            throw new \RuntimeException('Erro fatal: O SDK AcapaPay destina-se exclusivamente a projetos baseados no Laravel Framework.');
        }

        // Regista as configs base
        $this->mergeConfigFrom(
            __DIR__.'/../config/acapapay.php', 'acapapay'
        );

        // Regista a classe para a Facade
        $this->app->singleton('acapapay', function ($app) {
            return new AcapaPayManager();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/acapapay.php' => config_path('acapapay.php'),
        ], 'acapapay-config');

        // Carregar Vistas (Blade Component para o Iframe)
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'acapapay');

        // Registar Rota do Webhook de forma inteligente
        $this->registerRoutes();

        // Registar Comandos Artisan se a aplicação estiver a correr na Consola
        if ($this->app->runningInConsole()) {
            $this->commands([
                \AcapaPay\Laravel\Console\Commands\TestConnectionCommand::class,
                \AcapaPay\Laravel\Commands\SyncPlansCommand::class,
            ]);
        }
    }

    protected function registerRoutes()
    {
        Route::group([
            'prefix' => 'webhooks/acapapay',
            'namespace' => 'AcapaPay\Laravel\Http\Controllers',
        ], function () {
            // Regista a rota do webhook ignorando dinamicamente a proteção CSRF do projeto pai
            Route::post('/', 'WebhookController@handle')
                ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class, \App\Http\Middleware\VerifyCsrfToken::class])
                ->name('acapapay.webhook');
        });
    }
}
