<?php

namespace ManiSystems\CryptoWallet\Drivers\Evm;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use ManiSystems\CryptoWallet\Drivers\Evm\Exceptions\EvmRpcException;

/**
 * A thin JSON-RPC client for EVM nodes.
 *
 * Deliberately not a general-purpose web3 library: it exposes only the calls this driver
 * makes, so there is no surface area doing something surprising with money.
 */
class EvmClient
{
    private ?int $verifiedChainId = null;

    private int $requestId = 0;

    public function __construct(
        private readonly string $rpcUrl,
        private readonly int $expectedChainId,
        private readonly Client $http,
        private readonly int $retries = 2,
    ) {
    }

    public static function make(string $rpcUrl, int $expectedChainId, int $timeout = 15, int $retries = 2): self
    {
        return new self(
            $rpcUrl,
            $expectedChainId,
            new Client(['timeout' => $timeout, 'connect_timeout' => $timeout]),
            $retries
        );
    }

    /**
     * Confirm the node is on the chain we think it is, once per client.
     *
     * This is the single most important check in the driver. chainId is what makes an
     * EIP-155 signature valid on one chain and only that chain; if the configured RPC
     * quietly points somewhere else -- a testnet URL left in .env, a provider defaulting to
     * mainnet -- every transaction is signed for a chain it is not being broadcast to. The
     * failure is not a clean rejection, it is funds moving on a chain nobody is watching.
     */
    public function assertChainId(): void
    {
        if ($this->verifiedChainId !== null) {
            return;
        }

        $actual = hexdec($this->request('eth_chainId'));

        if ($actual !== $this->expectedChainId) {
            throw new EvmRpcException(
                "RPC endpoint reports chain {$actual} but this network is configured as "
                . "{$this->expectedChainId}. Refusing to sign: an EIP-155 signature is only "
                . 'valid for the chain it names.'
            );
        }

        $this->verifiedChainId = $actual;
    }

    /** Next nonce for an address, counting pending transactions. */
    public function nextNonce(string $address): int
    {
        // "pending" rather than "latest": with "latest" a second transaction sent before the
        // first is mined reuses its nonce and one of them is dropped.
        return (int) hexdec($this->request('eth_getTransactionCount', [$address, 'pending']));
    }

    /** Native-coin balance in wei, as a decimal string. */
    public function nativeBalance(string $address): string
    {
        return $this->hexToDecimal($this->request('eth_getBalance', [$address, 'latest']));
    }

    /**
     * Read-only contract call. Returns hex-encoded return data.
     *
     * $from is optional but rarely irrelevant. eth_call executes the contract for real, so
     * anything the contract decides based on msg.sender decides it here too -- and omitting
     * the sender means msg.sender is the ZERO ADDRESS. An ERC-20 asked to simulate a transfer
     * that way reverts with "transfer from the zero address", which reads like a broken
     * contract and is really a missing argument.
     *
     * That makes this the cheapest way to answer "would this transfer work": real bytecode,
     * real balances, no gas, nothing broadcast. Worth having when a wallet cannot yet afford
     * to send anything.
     */
    public function call(string $to, string $data, ?string $from = null): string
    {
        $params = ['to' => $to, 'data' => $data];

        if ($from !== null) {
            $params['from'] = $from;
        }

        return $this->request('eth_call', [$params, 'latest']);
    }

    /** Estimated gas units for a call, as an integer. */
    public function estimateGas(string $from, string $to, string $data, string $value = '0x0'): int
    {
        return (int) hexdec($this->request('eth_estimateGas', [[
            'from' => $from,
            'to' => $to,
            'data' => $data,
            'value' => $value,
        ]]));
    }

    /** Current block base fee, in wei, as a decimal string. */
    public function baseFeePerGas(): string
    {
        $block = $this->request('eth_getBlockByNumber', ['latest', false]);

        if (! isset($block['baseFeePerGas'])) {
            throw new EvmRpcException('Node returned a block with no baseFeePerGas; chain may be pre-EIP-1559.');
        }

        return $this->hexToDecimal($block['baseFeePerGas']);
    }

    /** Suggested priority fee (tip), in wei, as a decimal string. */
    public function maxPriorityFeePerGas(): string
    {
        try {
            return $this->hexToDecimal($this->request('eth_maxPriorityFeePerGas'));
        } catch (EvmRpcException) {
            // Not universally implemented. 1 gwei is a safe, unremarkable default; the
            // caller's multiplier is applied on top.
            return '1000000000';
        }
    }

    /** Broadcast a signed transaction. Returns the transaction hash. */
    public function sendRawTransaction(string $signedHex): string
    {
        $this->assertChainId();

        return $this->request('eth_sendRawTransaction', [
            str_starts_with($signedHex, '0x') ? $signedHex : '0x' . $signedHex,
        ]);
    }

    /** The head block number, as an integer. */
    public function blockNumber(): int
    {
        return (int) hexdec($this->request('eth_blockNumber'));
    }

    /**
     * Event logs in a block range.
     *
     * Reading, not writing, but the shape of the request is what makes this usable at all.
     * `topics` is positional and each position accepts an ARRAY, which the node treats as OR:
     *
     *     [$transferTopic, null, [$addrA, $addrB, ...]]
     *
     * asks for "Transfer events whose third topic is any of these addresses" in ONE call, for
     * every address at once. Filtering client-side instead would mean a request per address
     * and a bill that grows with the user table; done this way the cost grows with the number
     * of chains, which is a number you choose.
     *
     * Topics are 32 bytes. An address is 20, so it has to be left-padded to match or it
     * silently matches nothing -- the node does not consider a short topic an error, it
     * considers it a filter that nothing satisfies. Use topicForAddress().
     *
     * @param array<int, string|array<int, string>|null> $topics
     * @return array<int, array<string, mixed>>
     */
    public function getLogs(int $fromBlock, int $toBlock, string|array|null $address = null, array $topics = []): array
    {
        $filter = [
            'fromBlock' => '0x' . dechex($fromBlock),
            'toBlock' => '0x' . dechex($toBlock),
        ];

        if ($address !== null) {
            $filter['address'] = $address;
        }

        if ($topics !== []) {
            $filter['topics'] = $topics;
        }

        return $this->request('eth_getLogs', [$filter]) ?: [];
    }

    /**
     * An address as a 32-byte topic.
     *
     * Left-padded to 32 bytes because that is what an indexed `address` parameter occupies in
     * a log topic. Passing the bare 20-byte address matches nothing and reports no error.
     */
    public static function topicForAddress(string $address): string
    {
        return '0x' . str_pad(strtolower(ltrim($address, '0x')), 64, '0', STR_PAD_LEFT);
    }

    /** Receipt for a transaction, or null while it is still pending. */
    public function transactionReceipt(string $hash): ?array
    {
        return $this->request('eth_getTransactionReceipt', [$hash]) ?: null;
    }

    /**
     * Issue a JSON-RPC call, retrying only on transport failure.
     *
     * A JSON-RPC error object is NOT retried: "insufficient funds" or "nonce too low" are
     * deterministic answers, and re-sending is at best noise and at worst a double spend.
     */
    private function request(string $method, array $params = []): mixed
    {
        $attempt = 0;

        while (true) {
            try {
                $response = $this->http->post($this->rpcUrl, [
                    'json' => [
                        'jsonrpc' => '2.0',
                        'id' => ++$this->requestId,
                        'method' => $method,
                        'params' => $params,
                    ],
                ]);

                $body = json_decode((string) $response->getBody(), true);

                if (! is_array($body)) {
                    throw new EvmRpcException("Malformed JSON-RPC response for {$method}.");
                }

                if (isset($body['error'])) {
                    throw new EvmRpcException(sprintf(
                        '%s failed: %s (code %s)',
                        $method,
                        $body['error']['message'] ?? 'unknown error',
                        $body['error']['code'] ?? '?'
                    ));
                }

                return $body['result'] ?? null;
            } catch (GuzzleException $e) {
                if ($attempt++ >= $this->retries) {
                    throw new EvmRpcException("{$method} failed after {$attempt} attempt(s): {$e->getMessage()}", 0, $e);
                }
                // Linear backoff; these are sub-second node hiccups, not rate limits.
                usleep(250_000 * $attempt);
            }
        }
    }

    /**
     * Hex quantity to a decimal string. Never to an int: wei values on a 64-bit platform
     * exceed PHP_INT_MAX (1 ETH is 1e18, and PHP_INT_MAX is ~9.2e18), so an int cast
     * silently overflows on any meaningful balance.
     */
    private function hexToDecimal(string $hex): string
    {
        return gmp_strval(gmp_init(str_starts_with($hex, '0x') ? substr($hex, 2) : $hex, 16), 10);
    }
}
