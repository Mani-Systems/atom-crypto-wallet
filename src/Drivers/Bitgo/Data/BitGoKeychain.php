<?php

namespace ManiSystems\CryptoWallet\Drivers\Bitgo\Data;

class BitGoKeychain extends Data
{
    public string $id;

    public string $pub;

    public string $ethAddress;

    public string $source;

    public string $type;

    public bool $isBitGo;
}
