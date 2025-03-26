<?php

namespace ManiSystems\CryptoWallet\Drivers\Bitgo\Contracts;

use ManiSystems\CryptoWallet\Drivers\Bitgo\Data\Address\Address;
use ManiSystems\CryptoWallet\Drivers\Bitgo\Data\MaximumSpendable;
use ManiSystems\CryptoWallet\Drivers\Bitgo\Data\SendTransferToMany\SendToManyRequest;
use ManiSystems\CryptoWallet\Drivers\Bitgo\Data\Transfer;
use ManiSystems\CryptoWallet\Drivers\Bitgo\Data\Wallet as WalletDto;
use ManiSystems\CryptoWallet\Drivers\Bitgo\Data\WebhookData;

interface WalletContract
{
    public function generate(string $label, string $passphrase, string $enterpriseId, array $options = []): self;

    public function get();

    public function addWebhook(int $numConfirmations = 0): WebhookData;

    public function generateAddress(?string $label = null): Address;

    public function getTransfer(string $transferId): Transfer;

    /**
     * @return WalletDto[]
     */
    public function listAll(?string $coin = null, ?array $params = []): array;

    public function sendTransferToMany(SendToManyRequest $sendToManyRequest);

    public function getMaximumSpendable(?array $params = []): MaximumSpendable;

    /**
     * @return Transfer[]
     */
    public function getTransfers(): array;
}
