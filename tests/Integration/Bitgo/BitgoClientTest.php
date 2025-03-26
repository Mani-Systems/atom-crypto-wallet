<?php
namespace ManiSystems\CryptoWallet\Tests\Integration\Bitgo;

use ManiSystems\CryptoWallet\Tests\TestCase;

class BitgoClientTest extends TestCase
{
    public function testPingBitgoRestApi()
    {
        $response = $this->client->ping();
        $this->assertTrue($response->ok());
    }

    public function testPingBitgoExpressRestApi()
    {
        $response = $this->client->pingExpress();
        $this->assertTrue($response->ok());
    }

    public function testCanDetectCurrentBitgoUser()
    {
        $response = $this->client->me();

        $this->assertIsArray($response);
        $this->assertArrayHasKey('user', $response);
    }
}

// test('ping Bitgo rest api', function () {
//     $response = $this->client->ping();
//     $this->assertTrue($response->ok());
// });

// test('ping BitgoExpress rest api', function () {
//     $response = $this->client->pingExpress();
//     $this->assertTrue($response->ok());
// });

// it('can detect current bitgo user', function () {
//     $response = $this->client->me();
//     expect($response)
//         ->toBeArray()
//         ->toHaveKey('user');
// });
