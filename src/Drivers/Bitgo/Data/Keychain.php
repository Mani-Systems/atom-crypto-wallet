<?php

namespace ManiSystems\CryptoWallet\Drivers\Bitgo\Data;

class Keychain extends Data
{
    public string $id;

    public string $pub;

    public string $ethAddress;

    public string $source;

    public string $type;

    public string $encryptedPrv;

    public string $prv;
}
