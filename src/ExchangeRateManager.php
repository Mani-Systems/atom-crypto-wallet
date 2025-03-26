<?php

namespace ManiSystems\CryptoWallet;

use ManiSystems\CryptoWallet\Drivers\Bitgo\Modules\ExchangeRate;

class ExchangeRateManager
{
    public static function bitgo(): ExchangeRate
    {
        return new ExchangeRate;
    }
}
