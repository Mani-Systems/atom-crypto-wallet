<?php

namespace ManiSystems\CryptoWallet\Drivers\Evm\Modules;

use InvalidArgumentException;
use kornrunner\Ethereum\EIP1559Transaction;
use ManiSystems\CryptoWallet\Drivers\Evm\Data\SentTransaction;
use ManiSystems\CryptoWallet\Drivers\Evm\EvmClient;
use ManiSystems\CryptoWallet\Drivers\Evm\Exceptions\EvmRpcException;
use ManiSystems\CryptoWallet\Drivers\Evm\Support\Erc20;
use ManiSystems\CryptoWallet\Drivers\Evm\Support\KeyPair;
use SensitiveParameter;

/**
 * Self-custodied EVM wallet: keys generated and held by this application, transactions
 * signed locally and broadcast over JSON-RPC.
 *
 * Cost per transfer is gas alone -- roughly $0.002 on Base -- with no custodian fee.
 */
class Wallet
{
    private readonly array $network;

    private readonly array $tokens;

    private readonly array $gas;

    private ?EvmClient $client = null;

    public function __construct(
        private readonly string $networkName,
        ?array $config = null,
    ) {
        $config ??= config('crypto-wallet.drivers.evm', []);

        if (! isset($config['networks'][$networkName])) {
            throw new InvalidArgumentException(
                "Unknown EVM network [{$networkName}]. Configured: "
                . implode(', ', array_keys($config['networks'] ?? [])) . '.'
            );
        }

        $this->network = $config['networks'][$networkName];
        $this->tokens = $config['tokens'][$networkName] ?? [];
        $this->gas = $config['gas'] ?? [];
    }

    public function client(): EvmClient
    {
        return $this->client ??= EvmClient::make(
            $this->network['rpc'],
            $this->network['chain_id'],
            $this->network['rpc_timeout'] ?? 15,
        );
    }

    /** Swap in a client, for tests. */
    public function withClient(EvmClient $client): self
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Create a new wallet. The caller is responsible for encrypting privateKey() before it
     * touches storage -- this package deliberately does not choose an encryption scheme for
     * the host application.
     */
    public function generate(): KeyPair
    {
        return KeyPair::generate();
    }

    /** Token balance in base units, as a decimal string. */
    public function tokenBalance(string $address, string $symbol): string
    {
        $token = $this->token($symbol);

        $result = $this->client()->call($token['address'], Erc20::balanceOf($address));

        // A call to a non-contract address returns "0x" rather than erroring, which would
        // otherwise read as a zero balance and hide a misconfigured token address.
        if ($result === '0x' || $result === '' || $result === null) {
            throw new EvmRpcException(
                "balanceOf returned no data for {$symbol} at {$token['address']} -- "
                . 'the configured token address is probably not a contract on this network.'
            );
        }

        return gmp_strval(gmp_init(substr($result, 2), 16), 10);
    }

    /** Token balance as a human-readable decimal string. */
    public function tokenBalanceFormatted(string $address, string $symbol): string
    {
        return Erc20::fromBaseUnits($this->tokenBalance($address, $symbol), $this->token($symbol)['decimals']);
    }

    /** Native-coin balance in wei, as a decimal string. Needed to pay for gas. */
    public function nativeBalance(string $address): string
    {
        return $this->client()->nativeBalance($address);
    }

    /**
     * Transfer ERC-20 tokens.
     *
     * $amount is a human-readable decimal string ("12.34"), scaled with string arithmetic --
     * never a float.
     */
    public function transferToken(
        #[SensitiveParameter] string $privateKey,
        string $to,
        string $symbol,
        string $amount,
    ): SentTransaction {
        $client = $this->client();
        $client->assertChainId();

        if (! KeyPair::isValidAddress($to)) {
            throw new InvalidArgumentException("Refusing to send to a malformed address: {$to}");
        }

        $token = $this->token($symbol);
        $keys = KeyPair::fromPrivateKey($privateKey);
        $from = $keys->address();

        $baseUnits = Erc20::toBaseUnits($amount, $token['decimals']);
        $data = Erc20::transfer($to, $baseUnits);

        // Check the balance before spending gas on a transaction that must revert.
        $balance = $this->tokenBalance($from, $symbol);
        if (gmp_cmp(gmp_init($balance, 10), gmp_init($baseUnits, 10)) < 0) {
            throw new InvalidArgumentException(sprintf(
                'Insufficient %s: holding %s, need %s.',
                $symbol,
                Erc20::fromBaseUnits($balance, $token['decimals']),
                $amount
            ));
        }

        $gasLimit = $this->paddedGasLimit($client->estimateGas($from, $token['address'], $data));
        [$maxFee, $tip] = $this->feeParameters($client);

        // Fail here rather than have the node reject it: an under-funded sender is the most
        // common failure for a fresh wallet, and the RPC error for it is opaque.
        $this->assertCanAffordGas($client, $from, $gasLimit, $maxFee);

        $transaction = new EIP1559Transaction(
            $this->toHex($client->nextNonce($from)),
            $this->toHex($tip),
            $this->toHex($maxFee),
            $this->toHex($gasLimit),
            $token['address'],
            '0x0',              // ERC-20 transfers carry no native value
            $data
        );

        $hash = $client->sendRawTransaction($transaction->getRaw($keys->privateKey(), $this->network['chain_id']));

        return new SentTransaction(
            hash: $hash,
            from: $from,
            to: $to,
            token: $symbol,
            amount: $amount,
            baseUnits: $baseUnits,
            network: $this->networkName,
            explorerUrl: rtrim($this->network['explorer'] ?? '', '/') . '/tx/' . $hash,
        );
    }

    /**
     * Largest amount of $symbol that can be sent, given the gas this transfer will cost.
     *
     * For an ERC-20 the gas is paid in the native coin, so the token balance is spendable in
     * full -- provided the wallet holds enough native coin to cover gas at all. Returning
     * the token balance while gas is unaffordable would be a lie the user only discovers on
     * submit, so that case returns "0".
     */
    public function maximumSpendable(string $address, string $symbol): string
    {
        $token = $this->token($symbol);
        $client = $this->client();

        $balance = $this->tokenBalance($address, $symbol);

        if ($balance === '0') {
            return '0';
        }

        $gasLimit = $this->paddedGasLimit(
            // Estimate against a transfer to self: same code path and cost shape, no funds move.
            $client->estimateGas($address, $token['address'], Erc20::transfer($address, $balance))
        );
        [$maxFee] = $this->feeParameters($client);

        $needed = gmp_mul(gmp_init((string) $gasLimit, 10), gmp_init($maxFee, 10));
        $native = gmp_init($client->nativeBalance($address), 10);

        return gmp_cmp($native, $needed) < 0
            ? '0'
            : Erc20::fromBaseUnits($balance, $token['decimals']);
    }

    /**
     * EIP-1559 fee parameters, in wei.
     *
     * maxFee = baseFee * multiplier + tip. The base fee can rise up to 12.5% per block, so
     * padding it is what keeps a transaction includable through a few blocks of congestion
     * instead of stalling in the mempool. Unused padding is refunded -- the sender is only
     * charged baseFee + tip -- so over-padding costs nothing but under-padding costs a stuck
     * transaction, and a stuck transaction blocks every later nonce from that wallet.
     *
     * @return array{0: string, 1: string} [maxFeePerGas, maxPriorityFeePerGas]
     */
    private function feeParameters(EvmClient $client): array
    {
        $tipMultiplier = (float) ($this->gas['tip_multiplier'] ?? 1.2);
        $baseMultiplier = (float) ($this->gas['base_fee_multiplier'] ?? 2.0);

        $tip = gmp_init($this->scale($client->maxPriorityFeePerGas(), $tipMultiplier), 10);
        $base = gmp_init($this->scale($client->baseFeePerGas(), $baseMultiplier), 10);

        return [gmp_strval(gmp_add($base, $tip)), gmp_strval($tip)];
    }

    private function assertCanAffordGas(EvmClient $client, string $from, int $gasLimit, string $maxFee): void
    {
        $needed = gmp_mul(gmp_init((string) $gasLimit, 10), gmp_init($maxFee, 10));
        $native = gmp_init($client->nativeBalance($from), 10);

        if (gmp_cmp($native, $needed) < 0) {
            throw new InvalidArgumentException(sprintf(
                'Wallet %s cannot cover gas on %s: needs up to %s wei, holds %s wei.',
                $from,
                $this->networkName,
                gmp_strval($needed),
                gmp_strval($native)
            ));
        }
    }

    private function paddedGasLimit(int $estimate): int
    {
        $buffer = (int) ($this->gas['gas_limit_buffer_percent'] ?? 20);

        return (int) ceil($estimate * (1 + $buffer / 100));
    }

    /** Multiply a wei value by a float without going through float arithmetic on the value itself. */
    private function scale(string $wei, float $multiplier): string
    {
        // Scale by a rational (multiplier * 1000 / 1000) so the wei value stays an integer
        // throughout -- casting wei to float loses precision well below 1e18.
        $numerator = (int) round($multiplier * 1000);

        return gmp_strval(gmp_div_q(gmp_mul(gmp_init($wei, 10), gmp_init((string) $numerator, 10)), gmp_init('1000', 10)));
    }

    private function toHex(int|string $value): string
    {
        return '0x' . gmp_strval(gmp_init((string) $value, 10), 16);
    }

    /**
     * @return array{address: string, decimals: int}
     */
    private function token(string $symbol): array
    {
        $symbol = strtoupper($symbol);

        if (! isset($this->tokens[$symbol])) {
            throw new InvalidArgumentException(
                "Token [{$symbol}] is not configured for network [{$this->networkName}]. "
                . 'Add its verified contract address to config/crypto-wallet.php -- token '
                . 'addresses are intentionally not shipped as defaults.'
            );
        }

        $token = $this->tokens[$symbol];

        if (empty($token['address']) || ! isset($token['decimals'])) {
            throw new InvalidArgumentException("Token [{$symbol}] needs both 'address' and 'decimals'.");
        }

        return $token;
    }
}
