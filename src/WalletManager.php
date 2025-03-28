<?php

namespace ManiSystems\CryptoWallet;

use ManiSystems\CryptoWallet\Drivers\Bitgo\Modules\Wallet;

class WalletManager
{
    public static function bitgo(?string $coin = null, ?string $walletId = null): Wallet
    {
        return new Wallet($coin, $walletId);
    }
    //TODO: add more drivers here
}
