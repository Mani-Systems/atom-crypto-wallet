<?php

namespace ManiSystems\CryptoWallet\Drivers\Bitgo\Modules;

use Eyika\Atom\Framework\Http\Client\ConnectionException;
use Eyika\Atom\Framework\Support\Arr;
use ManiSystems\CryptoWallet\Drivers\Bitgo\BitgoClient;
use ManiSystems\CryptoWallet\Drivers\Bitgo\Exceptions\BitgoGatewayException;

class ExchangeRate
{
    protected BitgoClient $client;

    /**
     * @var string
     */
    protected mixed $coin;

    public function __construct()
    {
        $this->client = new BitgoClient;
        $this->coin = config('crypto-wallet.drivers.bitgo.default_coin');
    }

    /**
     * @throws BitgoGatewayException
     * @throws ConnectionException
     */
    public function all(): ?array
    {
        return $this->client->getExchangeRates();
    }

    /**
     * @throws BitgoGatewayException
     * @throws ConnectionException
     */
    public function getByCoin(?string $coin = null): ?array
    {
        $rates = $this->all();

        return Arr::first(array_filter($rates['marketData'], function ($rate) use ($coin) {
            return $rate['coin'] == $coin;
        }));
    }
}
