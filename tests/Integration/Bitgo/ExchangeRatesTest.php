<?php
namespace ManiSystems\CryptoWallet\Tests\Integration\Bitgo;

use ManiSystems\CryptoWallet\Tests\TestCase;
use ManiSystems\CryptoWallet\ExchangeRateManager;

class ExchangeRatesTest extends TestCase
{
    public function testCanFetchExchangeRates()
    {
        $res = ExchangeRateManager::bitgo()->all();
        $this->assertIsArray($res);
    }

    public function testCanGetExchangeRatesOnACoin()
    {
        $res = ExchangeRateManager::bitgo()->getByCoin('tbtc');
        
        $this->assertIsArray($res);
        $this->assertArrayHasKey('coin', $res);
        $this->assertEquals('tbtc', $res['coin']);
        $this->assertArrayHasKey('currencies', $res);
    }

    public function testCannotGetExchangeRatesOnAnInvalidCoin()
    {
        $res = ExchangeRateManager::bitgo()->getByCoin('invalid-coin');
        $this->assertNull($res);
    }
}

// it('can fetch exchange rates', function () {
//     $res = ExchangeRateManager::bitgo()->all();
//     expect($res)->toBeArray();
// });

// it('can get exchange rates on a coin', function () {
//     $res = ExchangeRateManager::bitgo()->getByCoin('tbtc');
//     expect($res)
//         ->toBeArray()
//         ->toHaveKey('coin', 'tbtc')
//         ->toHaveKey('currencies');
// });

// it('can not get exchange rates on an invalid coin', function () {
//     $res = ExchangeRateManager::bitgo()->getByCoin('invalid-coin');
//     expect($res)->toBeNull();
// });
