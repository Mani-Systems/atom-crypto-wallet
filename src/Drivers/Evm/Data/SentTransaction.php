<?php

namespace ManiSystems\CryptoWallet\Drivers\Evm\Data;

/**
 * A broadcast transfer.
 *
 * Broadcast is not settlement: the transaction is in the mempool, not yet mined. Poll
 * EvmClient::transactionReceipt() and check status === "0x1" before treating it as final.
 * A receipt with status "0x0" means it was mined and REVERTED -- gas was still spent and
 * the tokens did not move.
 */
final class SentTransaction
{
    public function __construct(
        public readonly string $hash,
        public readonly string $from,
        public readonly string $to,
        public readonly string $token,
        public readonly string $amount,
        public readonly string $baseUnits,
        public readonly string $network,
        public readonly string $explorerUrl,
    ) {
    }

    public function toArray(): array
    {
        return [
            'hash' => $this->hash,
            'from' => $this->from,
            'to' => $this->to,
            'token' => $this->token,
            'amount' => $this->amount,
            'base_units' => $this->baseUnits,
            'network' => $this->network,
            'explorer_url' => $this->explorerUrl,
        ];
    }
}
