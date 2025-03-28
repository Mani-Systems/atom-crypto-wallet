# Laravel Crypto Wallet

## Table of Contents
1. [Introduction](#introduction)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Usage](#usage)
5. [Testing](#testing)
6. [Contributing](#contributing)

## Introduction

Laravel Crypto Wallet is a flexible Laravel package that provides a **unified factory class** for interacting with various crypto wallet drivers. Currently, it supports **Bitgo**, with plans to add more drivers and introduce a **unified facade** in future releases.

> Our Future Plan:
>
>
> Enhance Bitgo support, add more drivers, unify the Wallet facade, offer driver selection via configuration—and still let you work directly with each driver.
>

> Note: The unified facade is not yet available, but it will be introduced in a future release
>

Currently, we support **Bitgo** for operations such as:

- [Generating a Wallet](#generating-a-wallet)
- [Getting a Wallet](#getting-a-wallet)
- [Listing Wallets](#listing-wallets)
- [Generating Addresses](#generating-addresses)
- [Getting Wallet Transfers](#getting-wallet-transfers)
- [Sending Transactions](#sending-transactions)
- [Getting Maximum Spendable](#getting-maximum-spendable)
- [Consolidating Wallet Balances](#consolidating-wallet-balances)
- [Adding Webhooks](#adding-webhooks)
- [Exchange Rates](#exchange-rates)

The package uses a clear, fluent API and is fully testable, making it simpler to integrate cryptocurrency-related features into your Laravel application.

## Installation

1. **Install via Composer**:

```bash
composer require manisystems/laravel-crypto-wallet
```

## Bitgo Express Docker

To run Bitgo Express locally using Docker, you can use the following commands:
```bash
docker pull bitgo/express:latest
docker run -it -p 3080:3080 bitgo/express:latest
```
For more information on Bitgo Express Docker, refer to the official [Bitgo Express Docker documentation](https://developers.bitgo.com/guides/get-started/express/install).

## Configuration

By default, the configuration for the Bitgo driver is included under the `drivers.bitgo` key. Once published, you’ll find the config at `config/cryptowallet.php`. Below is an example of what the Bitgo config might look like:

```php
return [
    'drivers' => [
        'bitgo' => [
            'use_mocks' => env('BITGO_USE_MOCKS', false),
            'testnet' => env('BITGO_TESTNET', true),
            'api_key' => env('BITGO_API_KEY'),
            'express_api_url' => env('BITGO_EXPRESS_API_URL'),
            'default_coin' => env('BITGO_DEFAULT_COIN', 'tbtc4'),//This is not a typo or just a result of a lazy developer :). BitGo is moving to Testnet4, so the Bitcoin testnet is now TBTC4.
            'webhook_callback_url' => env('BITGO_WEBHOOK_CALLBACK'),
        ],
    ],
];

```

**.env Example**:

```dotenv
BITGO_USE_MOCKS = false
BITGO_TESTNET = true
BITGO_API_KEY = YOUR-BITGO-API-KEY
BITGO_EXPRESS_API_URL = http://localhost:3080/api/v2/
BITGO_DEFAULT_COIN = tbtc4
BITGO_WEBHOOK_CALLBACK = https://yourapp.com/webhook/bitgo
```

Adjust these environment variables according to your needs.

## Usage

### Bitgo Driver

All Bitgo functionality is encapsulated within the **Bitgo** driver, which is used by default when calling `WalletManager::bitgo()`.

### Wallet Factory

The core entry point for all wallet-related activities is the `WalletManager` class. You can call static methods (like `bitgo()`) to instantiate a specific driver. For example:

```php
use ManiSystems\CryptoWallet\WalletManager;

$wallet = WalletManager::bitgo();
```

Optionally, you can specify a coin and/or wallet ID:

```php
$wallet = WalletManager::bitgo(coin: 'tbtc', walletId: 'my-wallet-id');
```

### Generating a Wallet

```php
$wallet = WalletManager::bitgo(coin: 'tbtc')
    ->generate(
        label: 'Test Wallet',
        passphrase: 'test-passphrase',
        enterpriseId: 'enterprise-id',
    );
```

### Getting a Wallet

```php
$wallet = WalletManager::bitgo(coin: 'tbtc', walletId: 'wallet-id')
        ->get();
```

### Listing Wallets

```php
$wallets = WalletManager::bitgo()->listAll();
```

### Generating Addresses

```php
$address = WalletManager::bitgo(coin: 'tbtc', walletId: 'wallet-id')
    ->generateAddress(label: 'My Address');
```

### Getting Wallet Transfers

```php
$transfers = WalletManager::bitgo(coin: 'tbtc', walletId: 'wallet-id')
    ->getTransfers();
```

### Sending Transactions

**Send to multiple recipients:**

```php
use ManiSystems\CryptoWallet\Drivers\Bitgo\Data\SendTransferToMany\SendToManyRequest;
use ManiSystems\CryptoWallet\Drivers\Bitgo\Data\SendTransferToMany\Recipient;

$sendTransferData = new SendToManyRequest(
    recipients: [
        new Recipient(address: 'tb1psv9q9zlp94s9jncnlye4kj0acyp56suxf28hn4k34vyrmsrp4qtsc9eqlq', amount: 4368),
    ],

    walletPassphrase: 'test',
    feeRate: 250,
);

$response = WalletManager::bitgo(coin: 'tbtc', walletId: 'wallet-id')
    ->sendTransferToMany(sendToManyRequest: $sendTransferData);
```

### Getting Maximum Spendable

```php
$maxSpendable = WalletManager::bitgo(coin: 'tbtc', walletId: 'wallet-id')
    ->getMaximumSpendable([
        'feeRate' => 0,
    ]);
```

### Consolidating Wallet Balances

```php
$result = WalletManager::bitgo(coin: 'tbtc', walletId: 'wallet-id')->consolidate([
    'walletPassphrase' => 'testing-pass',
    'bulk' => true,
    'minValue' => '0',
    'minHeight' => 0,
    'minConfirms' => 0,
]);
```

### Adding Webhooks

When you generate a wallet, you can easily attach a webhook:

```php
$webhook = WalletManager::bitgo('tbtc')
    ->generate(
        label: 'wallet with webhook', 
        passphrase: 'test-passphrase',
        enterpriseId: 'enterprise-id'
    )
    ->addWebhook(
        numConfirmations: 6, 
        callbackUrl: 'https://yourapp.com/webhook/bitgo'
    );

```

## Exchange Rates

You can easily fetch current exchange rates using the `ExchangeRateManager`.

Currently, we provide a **Bitgo** driver implementation.

### Fetch All Exchange Rates

```php
use ManiSystems\CryptoWallet\ExchangeRateManager;

$rates = ExchangeRateManager::bitgo()->all();
```

### Fetch Exchange Rates for a Specific Coin

```php
use ManiSystems\CryptoWallet\ExchangeRateManager;

$tbtcRates = ExchangeRateManager::bitgo()->getByCoin('tbtc');
```

## Testing

We use [Pest PHP](https://pestphp.com/) to ensure all functionalities work as expected. You can find our test files under `tests/`. To run the tests:

```bash
./vendor/bin/pest
# or
php artisan test
```

## Contributing

1. Fork the repository
2. Create a new branch (`git checkout -b feature/someFeature`)
3. Make your changes
4. Write or update tests
5. Commit your changes (`git commit -m 'feat: Add some feature'`)
6. Push to the branch (`git push origin feature/someFeature`)
7. Create a Pull Request

We welcome all contributions that help improve this package!
