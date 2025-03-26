<?php

namespace ManiSystems\CryptoWallet\Drivers\Bitgo\Data\SendTransferToMany;

use ManiSystems\CryptoWallet\Drivers\Bitgo\Data\Data;

class SendToManyRequest extends Data
{
    /**
     * @param  Recipient[]  $recipients
     * @param  array|null  $eip1559  ['maxPriorityFeePerGas' => int|string, 'maxFeePerGas' => int|string]
     * @param  array|null  $memo  ['type' => string, 'value' => string]
     * @param  array|null  $trustlines  [{'token' => string, 'action' => string, 'limit' => string}]
     * @param  array|null  $stakingOptions  ['amount' => int|string, 'validator' => string]
     * @param  array|null  $reservation  ['expireTime' => string]
     */
    public readonly array $recipients;
    public readonly ?string $otp;
    public readonly ?string $walletPassphrase;
    public readonly ?string $prv;
    public readonly ?string $type;
    public readonly ?int $numBlocks;
    public readonly int|string|null $feeRate;
    public readonly int|string|null $maxFeeRate;
    public readonly float|string|null $feeMultiplier;
    public readonly ?int $minConfirms;
    public readonly ?bool $enforceMinConfirmsForChange;
    public readonly int|string|null $gasPrice;
    public readonly ?array $eip1559;
    public readonly int|string|null $gasLimit;
    public readonly ?int $targetWalletUnspents;
    public readonly int|string|null $minValue;
    public readonly int|string|null $maxValue;
    public readonly ?string $sequenceId;
    public readonly ?string $nonce;
    public readonly ?bool $noSplitChange;
    public readonly ?array $unspents;
    public readonly ?string $changeAddress;
    public readonly ?string $txFormat;
    public readonly ?bool $instant;
    public readonly ?array $memo;
    public readonly ?string $comment;
    public readonly ?string $destinationChain;
    public readonly ?string $sourceChain;
    public readonly string|array|null $changeAddressType;
    public readonly ?string $startTime;
    public readonly ?string $consolidateId;
    public readonly ?int $lastLedgerSequence;
    public readonly ?int $ledgerSequenceDelta;
    public readonly ?array $rbfTxIds;
    public readonly ?bool $isReplaceableByFee;
    public readonly ?int $validFromBlock;
    public readonly ?int $validToBlock;
    public readonly ?array $trustlines;
    public readonly ?array $stakingOptions;
    public readonly ?string $messageKey;
    public readonly ?array $reservation;
    public readonly ?string $data;
}
