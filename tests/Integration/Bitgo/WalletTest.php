<?php
namespace ManiSystems\CryptoWallet\Tests\Integration\Bitgo;

use Eyika\Atom\Framework\Http\Client\ConnectionException;
use ManiSystems\CryptoWallet\Tests\TestCase;
use ManiSystems\CryptoWallet\Drivers\Bitgo\Data\Address\Address;
use ManiSystems\CryptoWallet\Drivers\Bitgo\Data\MaximumSpendable;
use ManiSystems\CryptoWallet\Drivers\Bitgo\Data\SendTransferToMany\Recipient;
use ManiSystems\CryptoWallet\Drivers\Bitgo\Data\SendTransferToMany\SendToManyRequest;
use ManiSystems\CryptoWallet\Drivers\Bitgo\Data\Transfer;
use ManiSystems\CryptoWallet\Drivers\Bitgo\Data\Wallet;
use ManiSystems\CryptoWallet\Drivers\Bitgo\Data\WebhookData;
use ManiSystems\CryptoWallet\Drivers\Bitgo\Exceptions\BitgoGatewayException;
use ManiSystems\CryptoWallet\WalletManager;

class WalletTest extends TestCase
{
    /**
     * @throws BitgoGatewayException
     */
    public function testCanGenerateWallet()
    {
        $wallet = WalletManager::bitgo('tbtc')
            ->generate('testing label', 'testing pass', 'enterprise-id');
        $this->assertIsObject($wallet, 'wallet should be an object');
        $this->assertInstanceOf(Wallet::class, $wallet, 'wallet should be instance of '.Wallet::class);
        $this->assertObjectHasProperty('id', $wallet);
        $this->assertObjectHasProperty('coin', $wallet);
        $this->assertEquals('tbtc', $wallet->coin);
    }

    /**
     * @throws BitgoGatewayException|ConnectionException
     */
    public function testCanGetWalletById()
    {
        $wallet = WalletManager::bitgo('tbtc', 'wallet-id')
            ->get();

        $this->assertIsObject($wallet, 'wallet should be an object');
        $this->assertInstanceOf(Wallet::class, $wallet, 'wallet should be instance of '.Wallet::class);
        $this->assertEquals('wallet-id', $wallet->id);
        $this->assertEquals('tbtc', $wallet->coin);
    }

    /**
     * @throws BitgoGatewayException|ConnectionException
     */
    public function testCanGetWalletTransfers()
    {
        $transfers = WalletManager::bitgo('tbtc', 'wallet-id')
            ->getTransfers();

        $this->assertIsArray($transfers, 'transfers should be an array');
        $this->assertArrayHasKey(0, $transfers, 'transfers should have at least one element');
        $this->assertInstanceOf(Transfer::class, $transfers[0], 'tranfers should contain instances of '.Transfer::class);
        $this->assertObjectHasProperty('id', $transfers[0]);
        $this->assertEquals('tbtc', $transfers[0]->coin);
    }

    /**
     * @throws BitgoGatewayException
     */
    public function testCanGenerateAddressOnWallet()
    {
        $address = WalletManager::bitgo('tbtc', 'wallet-id')
            ->generateAddress('testing label');

        $this->assertIsObject($address, 'address should be an object');
        $this->assertInstanceOf(Address::class, $address, 'address should be instance of '.Address::class);
        $this->assertObjectHasProperty('id', $address);
        $this->assertObjectHasProperty('address', $address);
        $this->assertEquals('tbtc', $address->coin);
    }

    /**
     * @throws BitgoGatewayException|ConnectionException
     */
    public function testCanGetWalletTransfer()
    {
        $transfer = WalletManager::bitgo('tbtc', 'wallet-id')
            ->getTransfer('transfer-id');

        $this->assertInstanceOf(Transfer::class, $transfer, 'transfer should be instance of ' . Transfer::class);
        $this->assertObjectHasProperty('id', $transfer);
        $this->assertObjectHasProperty('coin', $transfer);
        $this->assertEquals('tbtc', $transfer->coin);
    }

    /**
     * @throws BitgoGatewayException
     */
    public function testCanGenerateWalletWithWebhook()
    {
        $webhook = WalletManager::bitgo('tbtc')
            ->generate('wallet with webhook', 'testing pass', 'enterprise-id')
            ->addWebhook(6, 'https://www.blockchain.com/');

        $this->assertIsObject($webhook, 'webhook should be an object');
        $this->assertInstanceOf(WebhookData::class, $webhook, 'webhook should be instance of ' . WebhookData::class);
        $this->assertObjectHasProperty('coin', $webhook);
        $this->assertObjectHasProperty('url', $webhook);
        $this->assertEquals('tbtc', $webhook->coin);
        $this->assertEquals('https://www.blockchain.com/', $webhook->url);
    }

    /**
     * @throws BitgoGatewayException|ConnectionException
     */
    public function testInitsWalletCorrectly()
    {
        $wallet = WalletManager::bitgo('tbtc', 'wallet-id');

        $this->assertIsObject($wallet, 'wallet should be an object');
        $this->assertObjectHasProperty('coin', $wallet);
        $this->assertObjectHasProperty('id', $wallet);
        $this->assertEquals('tbtc', $wallet->coin);
        $this->assertEquals('wallet-id', $wallet->id);
    }

    /**
     * @throws BitgoGatewayException|ConnectionException
     */
    public function testCanListAllAvailableWallets()
    {
        $wallets = WalletManager::bitgo()->listAll();

        $this->assertIsArray($wallets, 'wallets should be an array');
        $this->assertNotEmpty($wallets, 'wallets list should not be empty');
        $this->assertInstanceOf(Wallet::class, $wallets[0], 'wallets should contain instances of ' . Wallet::class);
        $this->assertObjectHasProperty('coin', $wallets[0]);
        $this->assertObjectHasProperty('id', $wallets[0]);
    }

    /**
     * @throws BitgoGatewayException
     */
    public function testCanSendTransaction()
    {
        $sendTransferData = new SendToManyRequest(
            recipients: [
                new Recipient(address: 'address-1', amount: 333),
                new Recipient(address: 'address-2', amount: 333),
            ],
            walletPassphrase: 'test',
        );
        // print_r($sendTransferData).PHP_EOL;

        $res = WalletManager::bitgo('tbtc', 'wallet-id')->sendTransferToMany($sendTransferData);

        $this->assertIsArray($res, 'sendTransferToMany response should be an array');
    }

    /**
     * @throws BitgoGatewayException
     */
    public function testCanGetMaximumSpendableAmount()
    {
        $result = WalletManager::bitgo('tbtc', 'wallet-id')->getMaximumSpendable([
            'feeRate' => 0,
        ]);

        $this->assertInstanceOf(MaximumSpendable::class, $result, 'maximum spendable result should be instance of ' . MaximumSpendable::class);
        $this->assertObjectHasProperty('coin', $result);
        $this->assertObjectHasProperty('maximumSpendable', $result);
    }

    /**
     * @throws BitgoGatewayException
     */
    public function testCanConsolidateWalletBalance()
    {
        $result = WalletManager::bitgo('tbtc', 'wallet-id')->consolidate([
            'walletPassphrase' => 'test',
            'bulk' => true,
            'minValue' => '0',
            'minHeight' => 0,
            'minConfirms' => 0,
        ]);

        $this->assertIsArray($result, 'consolidation result should be an array');
    }
}

// it('can get wallet transfer', function () {
//     $transfer = WalletManager::bitgo('tbtc', 'wallet-id')
//         ->getTransfer('transfer-id');

//     expect($transfer)
//         ->toBeInstanceOf(Transfer::class)
//         ->toHaveProperty('id')
//         ->toHaveProperty('coin', 'tbtc');
// });

// it('can generate wallet with webhook', function () {
//     $webhook = WalletManager::bitgo('tbtc')
//         ->generate('wallet with webhook', 'testing pass', 'enterprise-id')
//         ->addWebhook(6, 'https://www.blockchain.com/');

//     expect($webhook)
//         ->toBeObject()
//         ->toBeInstanceOf(WebhookData::class)
//         ->toHaveProperty('coin', 'tbtc')
//         ->toHaveProperty('url', 'https://www.blockchain.com/');

// });

// it('inits wallet correctly', function () {
//     $wallet = WalletManager::bitgo('tbtc', 'wallet-id');
//     expect($wallet)
//         ->toBeObject()
//         ->toHaveProperty('coin', 'tbtc')
//         ->toHaveProperty('id', 'wallet-id');
// });

// it('can list all the available wallets', function () {
//     $wallets = WalletManager::bitgo()->listAll();

//     expect($wallets)
//         ->toBeArray()
//         ->and($wallets[0])
//         ->toBeInstanceOf(Wallet::class)
//         ->toHaveProperties(['coin', 'id']);
// });

// it('can send transaction', closure: function () {

//     $sendTransferData = new SendToManyRequest(
//         recipients: [
//             new Recipient(address: 'address-1', amount: 333),
//             new Recipient(address: 'address-2', amount: 333),
//         ],
//         walletPassphrase: 'test',
//     );
//     $res = WalletManager::bitgo('tbtc', 'wallet-id')->sendTransferToMany($sendTransferData);
//     expect($res)->toBeArray();
// });

// it('can get a maximum spendable amount of the wallet', function () {
//     $result = WalletManager::bitgo('tbtc', 'wallet-id')->getMaximumSpendable([
//         'feeRate' => 0,
//     ]);

//     expect($result)
//         ->toBeInstanceOf(MaximumSpendable::class)
//         ->toHaveProperties(['coin', 'maximumSpendable']);
// });

// it('can consolidate wallet balance', function () {
//     $result = WalletManager::bitgo('tbtc', 'wallet-id')->consolidate([
//         'walletPassphrase' => 'test',
//         'bulk' => true,
//         'minValue' => '0',
//         'minHeight' => 0,
//         'minConfirms' => 0,
//     ]);

//     expect($result)->toBeArray();
// });
