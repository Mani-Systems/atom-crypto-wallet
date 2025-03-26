<?php

namespace ManiSystems\CryptoWallet\Drivers\Bitgo\Data\SendTransferToMany;

use ManiSystems\CryptoWallet\Drivers\Bitgo\Data\Data;

class Recipient extends Data
{
    public string $address;
    public int $amount;
    public ?string $tokenName = null;
    public ?TokenData $tokenData = null;
}
