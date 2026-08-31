<?php

namespace ManiSystems\CryptoWallet\Facades;

use Eyika\Atom\Framework\Support\Facade\Facade;

/**
 * The package's composer.json has advertised this alias since the beginning, but the class
 * was never written. It stayed invisible only because PackageManifest::aliases() is not yet
 * consumed by the framework -- Application::registerProviders() wires facades from the
 * provider's registerFacades() call instead. So the alias was inert rather than fatal, and
 * would have started failing the moment the framework began honouring the manifest block.
 *
 * @method static \ManiSystems\CryptoWallet\Drivers\Evm\Modules\Wallet evm(?string $network = null)
 * @method static \ManiSystems\CryptoWallet\Drivers\Bitgo\Modules\Wallet bitgo(?string $coin = null, ?string $walletId = null)
 * @method static \ManiSystems\CryptoWallet\Drivers\Evm\Modules\Wallet|\ManiSystems\CryptoWallet\Drivers\Bitgo\Modules\Wallet driver(?string $name = null)
 */
class Wallet extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'crypto-wallet';
    }
}
