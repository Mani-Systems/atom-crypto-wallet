<?php

namespace ManiSystems\CryptoWallet;

use Eyika\Atom\Framework\Foundation\ServiceProvider;
use ManiSystems\CryptoWallet\Drivers\Bitgo\Modules\Wallet;

/**
 * CryptoWalletServiceProvider is the service provider for the Bitgo Wallet package.
 */
class CryptoWalletServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     */
    public function register(): void
    {
        // $this->mergeConfigFrom(
        //     __DIR__.'/../config/crypto-wallet.php',
        //     'crypto-wallet'
        // );
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/crypto-wallet.php' => config_path('crypto-wallet.php'),
        ], 'crypto-wallet-config');
    }
}
