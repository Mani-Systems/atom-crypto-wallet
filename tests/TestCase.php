<?php

namespace ManiSystems\CryptoWallet\Tests;

use Eyika\Atom\Framework\Support\TestCase as BaseTestCase;
// use Orchestra\Testbench\TestCase as Orchestra;
use ManiSystems\CryptoWallet\CryptoWalletServiceProvider;
use ManiSystems\CryptoWallet\Drivers\Bitgo\BitgoClient;
use ManiSystems\CryptoWallet\Drivers\Bitgo\Contracts\WalletContract;
// use Spatie\LaravelData\LaravelDataServiceProvider;

class TestCase extends BaseTestCase
{
    use BitgoHttpMocks;

    public BitgoClient $client;

    public WalletContract $wallet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new BitgoClient;
        if (config('crypto-wallet.drivers.bitgo.use_mocks')) {
            self::setupMocks();
        }
    }

    protected function getPackageProviders($app): array
    {
        return [
            CryptoWalletServiceProvider::class,
            // LaravelDataServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        // config()->set('database.default', 'testing');
    }
}
