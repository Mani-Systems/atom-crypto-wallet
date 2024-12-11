<?php

namespace RedberryProducts\CryptoWallet;

use RedberryProducts\CryptoWallet\Drivers\Bitgo\Modules\ExchangeRate;

class ExchangeRateFactory
{
    public static function bitgo(): ExchangeRate
    {
        return new ExchangeRate;
    }
}
