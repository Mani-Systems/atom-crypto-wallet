<?php

namespace ManiSystems\CryptoWallet;

use InvalidArgumentException;
use ManiSystems\CryptoWallet\Drivers\Bitgo\Modules\Wallet as BitgoWallet;
use ManiSystems\CryptoWallet\Drivers\Evm\Modules\Wallet as EvmWallet;

class WalletManager
{
    /**
     * Keys held by BitGo. No key-management burden, but BitGo charges per wallet and per
     * transaction.
     */
    public static function bitgo(?string $coin = null, ?string $walletId = null): BitgoWallet
    {
        return new BitgoWallet($coin, $walletId);
    }

    /**
     * Keys held by this application, signed locally, broadcast over JSON-RPC. Cost per
     * transfer is gas alone.
     *
     * Defaults to the configured network (Base), which is the cheapest of the
     * Paycrest-supported chains by roughly two orders of magnitude.
     */
    public static function evm(?string $network = null): EvmWallet
    {
        return new EvmWallet($network ?? config('crypto-wallet.drivers.evm.default_network', 'base'));
    }

    /**
     * Resolve the configured default driver.
     */
    public static function driver(?string $name = null): BitgoWallet|EvmWallet
    {
        $name ??= config('crypto-wallet.default', 'evm');

        return match ($name) {
            'evm' => self::evm(),
            'bitgo' => self::bitgo(),
            default => throw new InvalidArgumentException("Unknown crypto wallet driver [{$name}]."),
        };
    }
}
