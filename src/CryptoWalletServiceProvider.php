<?php

namespace ManiSystems\CryptoWallet;

use Eyika\Atom\Framework\Foundation\ServiceProvider;

/**
 * Auto-discovered via composer.json extra.atom.providers. Wires:
 *   - config/crypto-wallet.php (merged; publishable with --tag=crypto-wallet-config),
 *   - the WalletManager, bound as 'crypto-wallet' and used by the Wallet facade.
 *
 * Shape follows the official Atom packages (atom-reverb, atom-octane): mergeConfigFrom and
 * container bindings in register(), publishes()/commands() in boot().
 */
class CryptoWalletServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     */
    public function register(): void
    {
        // This was commented out, which meant config('crypto-wallet.*') resolved to null in
        // any application that had not run vendor:publish -- so a freshly required package
        // was silently unconfigured, and the EVM driver would report every network unknown.
        // mergeConfigFrom belongs in register(), not boot(): other providers' boot() may read
        // this config, and boot() ordering is not guaranteed relative to them.
        $this->mergeConfigFrom(
            __DIR__ . '/../config/crypto-wallet.php',
            'crypto-wallet'
        );

        $this->app->singleton('crypto-wallet', fn () => new WalletManager());
        $this->app->bind(WalletManager::class, fn ($app) => $app->make('crypto-wallet'));

        // The functional half of the facade alias. The extra.atom.aliases block in
        // composer.json is declarative metadata that nothing currently reads -- this call is
        // what Application::registerProviders() actually consumes, via getFacades().
        $this->registerFacades([
            'Wallet' => Facades\Wallet::class,
        ]);
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/crypto-wallet.php' => config_path('crypto-wallet.php'),
        ], 'crypto-wallet-config');
    }
}
