<?php

namespace ManiSystems\CryptoWallet\Tests\Unit\Evm;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use ManiSystems\CryptoWallet\Drivers\Evm\EvmClient;
use ManiSystems\CryptoWallet\Drivers\Evm\Exceptions\EvmRpcException;
use ManiSystems\CryptoWallet\Drivers\Evm\Modules\Wallet;
use ManiSystems\CryptoWallet\Drivers\Evm\Support\Erc20;
use ManiSystems\CryptoWallet\Drivers\Evm\Support\KeyPair;
use PHPUnit\Framework\TestCase;

/**
 * The EVM driver moves real funds, so these cover the parts where being wrong is expensive:
 * chain identity, amount scaling, address validity, and the refusals that stop a doomed
 * transaction before it burns gas.
 */
class EvmWalletTest extends TestCase
{
    private const BASE_CHAIN_ID = 8453;

    /** A published test key; never used for real funds. */
    private const TEST_KEY = '4c0883a69102937d6231471b5dbb6204fe5129617082792ae468d01a3f362318';

    private const TEST_ADDRESS = '0x2c7536e3605d9c16a7a3d7b1898e529396a65c23';

    private const USDC = '0x0000000000000000000000000000000000000abc';

    private function config(array $overrides = []): array
    {
        return array_replace_recursive([
            'networks' => [
                'base' => ['chain_id' => self::BASE_CHAIN_ID, 'rpc' => 'http://stub', 'explorer' => 'https://basescan.org'],
            ],
            'tokens' => [
                'base' => ['USDC' => ['address' => self::USDC, 'decimals' => 6]],
            ],
            'gas' => ['tip_multiplier' => 1.2, 'base_fee_multiplier' => 2.0, 'gas_limit_buffer_percent' => 20],
        ], $overrides);
    }

    /** Build a client whose RPC responses are scripted, in order. */
    private function clientReturning(array $results): EvmClient
    {
        $responses = array_map(
            fn ($r) => new Response(200, [], json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => $r])),
            $results
        );

        $http = new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);

        return new EvmClient('http://stub', self::BASE_CHAIN_ID, $http, 0);
    }

    private function wallet(EvmClient $client, array $overrides = []): Wallet
    {
        return (new Wallet('base', $this->config($overrides)))->withClient($client);
    }

    public function test_it_refuses_to_sign_when_the_node_is_on_a_different_chain(): void
    {
        // The defining safety property: an EIP-155 signature is only valid for the chain it
        // names, so signing against a node serving a different chain must never happen.
        $client = $this->clientReturning(['0x1']);   // mainnet, not Base

        $this->expectException(EvmRpcException::class);
        $this->expectExceptionMessageMatches('/reports chain 1 .*configured as 8453/s');

        $client->assertChainId();
    }

    public function test_it_accepts_a_node_on_the_expected_chain(): void
    {
        $client = $this->clientReturning([dechex(self::BASE_CHAIN_ID)]);

        $client->assertChainId();

        $this->assertTrue(true, 'assertChainId passed for a matching chain');
    }

    public function test_it_rejects_a_malformed_destination_address(): void
    {
        $wallet = $this->wallet($this->clientReturning([dechex(self::BASE_CHAIN_ID)]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/malformed address/');

        $wallet->transferToken(self::TEST_KEY, '0xdeadbeef', 'USDC', '1.00');
    }

    public function test_it_refuses_a_transfer_larger_than_the_balance(): void
    {
        $client = $this->clientReturning([
            dechex(self::BASE_CHAIN_ID),                                        // eth_chainId
            '0x' . str_pad(dechex(1_000_000), 64, '0', STR_PAD_LEFT),           // balanceOf -> 1 USDC
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Insufficient USDC: holding 1, need 5/');

        $this->wallet($client)->transferToken(self::TEST_KEY, self::TEST_ADDRESS, 'USDC', '5');
    }

    public function test_it_refuses_when_the_wallet_cannot_cover_gas(): void
    {
        // A funded token balance with no native coin is the classic fresh-wallet failure;
        // catching it here beats an opaque node rejection.
        $client = $this->clientReturning([
            dechex(self::BASE_CHAIN_ID),                                        // eth_chainId
            '0x' . str_pad(dechex(10_000_000), 64, '0', STR_PAD_LEFT),          // balanceOf -> 10 USDC
            '0xcb28',                                                           // estimateGas
            '0x3b9aca00',                                                       // maxPriorityFeePerGas
            ['baseFeePerGas' => '0x3b9aca00'],                                  // block
            '0x0',                                                              // native balance: nothing
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/cannot cover gas/');

        $this->wallet($client)->transferToken(self::TEST_KEY, self::TEST_ADDRESS, 'USDC', '1');
    }

    public function test_it_reports_an_unconfigured_token_rather_than_guessing(): void
    {
        // Token addresses are deliberately not shipped as defaults; a wrong one sends real
        // funds to a contract nobody controls.
        //
        // Built explicitly rather than via config() overrides: array_replace_recursive MERGES,
        // so an empty 'tokens' array would leave the configured USDC in place and this would
        // silently assert nothing.
        $wallet = (new Wallet('base', [
            'networks' => ['base' => ['chain_id' => self::BASE_CHAIN_ID, 'rpc' => 'http://stub']],
            'tokens' => ['base' => []],
            'gas' => [],
        ]))->withClient($this->clientReturning([]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not configured for network \[base\].*not shipped as defaults/s');

        $wallet->tokenBalance(self::TEST_ADDRESS, 'USDC');
    }

    public function test_balance_of_against_a_non_contract_is_an_error_not_a_zero_balance(): void
    {
        // eth_call to a plain address returns "0x", which would otherwise read as zero and
        // hide a misconfigured token address.
        $client = $this->clientReturning(['0x']);

        $this->expectException(EvmRpcException::class);
        $this->expectExceptionMessageMatches('/probably not a contract/');

        $this->wallet($client)->tokenBalance(self::TEST_ADDRESS, 'USDC');
    }

    public function test_it_reads_a_token_balance_without_integer_overflow(): void
    {
        // 1e24 base units exceeds PHP_INT_MAX; an int cast here would silently corrupt it.
        $huge = gmp_strval(gmp_pow(gmp_init('10'), 24));
        $hex = str_pad(gmp_strval(gmp_init($huge, 10), 16), 64, '0', STR_PAD_LEFT);

        $balance = $this->wallet($this->clientReturning(['0x' . $hex]))
            ->tokenBalance(self::TEST_ADDRESS, 'USDC');

        $this->assertSame($huge, $balance);
        $this->assertGreaterThan(PHP_INT_MAX, (float) $balance);
    }

    public function test_maximum_spendable_is_zero_when_gas_is_unaffordable(): void
    {
        // Reporting the token balance as spendable while gas is unaffordable is a lie the
        // user only discovers on submit.
        $client = $this->clientReturning([
            '0x' . str_pad(dechex(10_000_000), 64, '0', STR_PAD_LEFT),          // balanceOf -> 10 USDC
            '0xcb28',                                                           // estimateGas
            '0x3b9aca00',                                                       // maxPriorityFeePerGas
            ['baseFeePerGas' => '0x3b9aca00'],                                  // block
            '0x0',                                                              // native balance: nothing
        ]);

        $this->assertSame('0', $this->wallet($client)->maximumSpendable(self::TEST_ADDRESS, 'USDC'));
    }

    public function test_generated_keypairs_are_unique_and_well_formed(): void
    {
        $seen = [];

        for ($i = 0; $i < 25; $i++) {
            $pair = KeyPair::generate();

            $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $pair->privateKey());
            $this->assertTrue(KeyPair::isValidAddress($pair->address()));
            $this->assertSame($pair->address(), KeyPair::fromPrivateKey($pair->privateKey())->address());

            $seen[$pair->privateKey()] = true;
        }

        $this->assertCount(25, $seen, 'every generated key must be distinct');
    }

    public function test_a_keypair_never_leaks_its_private_key(): void
    {
        $pair = KeyPair::generate();

        $this->assertStringNotContainsString($pair->privateKey(), print_r($pair, true));
        $this->assertStringContainsString('[redacted]', print_r($pair, true));
    }

    public function test_transfer_calldata_matches_the_erc20_abi(): void
    {
        $data = Erc20::transfer(self::TEST_ADDRESS, Erc20::toBaseUnits('12.34', 6));

        $this->assertStringStartsWith('0xa9059cbb', $data);
        // selector (8 hex) + address word (64) + uint word (64), plus the 0x.
        $this->assertSame(2 + 8 + 64 + 64, strlen($data));
        $this->assertStringEndsWith(str_pad(dechex(12_340_000), 64, '0', STR_PAD_LEFT), $data);
    }

    // --- reading logs ---------------------------------------------------------

    public function test_the_transfer_topic_matches_the_canonical_hash(): void
    {
        // Derived rather than pasted. The well-known constant is easy to get one character
        // wrong, and a filter with a wrong topic does not error -- it matches nothing,
        // forever, silently. This pins the derivation against the published value.
        $this->assertSame(
            '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef',
            Erc20::transferEventTopic()
        );
    }

    public function test_an_address_topic_is_left_padded_to_32_bytes(): void
    {
        // A 20-byte address in a 32-byte topic slot matches nothing and reports no error,
        // which is the worst possible combination for a deposit scanner.
        $topic = EvmClient::topicForAddress('0xAb5801a7D398351b8bE11C439e05C5B3259aeC9B');

        $this->assertSame(66, strlen($topic));
        $this->assertSame(
            '0x000000000000000000000000ab5801a7d398351b8be11c439e05c5b3259aec9b',
            $topic
        );
    }

    public function test_an_address_survives_a_round_trip_through_a_topic(): void
    {
        $address = '0xab5801a7d398351b8be11c439e05c5b3259aec9b';

        $this->assertSame($address, Erc20::addressFromTopic(EvmClient::topicForAddress($address)));
    }

    public function test_a_transfer_value_is_decoded_without_losing_precision(): void
    {
        // hexdec() returns a FLOAT above PHP_INT_MAX and quietly loses digits, which for an
        // 18-decimal token is most real amounts. This is a little over 1000 tokens at 18dp.
        $data = '0x000000000000000000000000000000000000000000000036356633bd1e2d2000';

        $this->assertSame('1000000000000000000000', Erc20::decodeTransferValue(
            '0x00000000000000000000000000000000000000000000003635c9adc5dea00000'
        ));

        // And a small one, at 6 decimals.
        $this->assertSame('1000000', Erc20::decodeTransferValue(
            '0x00000000000000000000000000000000000000000000000000000000000f4240'
        ));

        // Zero is zero, not an empty string.
        $this->assertSame('0', Erc20::decodeTransferValue('0x' . str_repeat('0', 64)));
    }

    public function test_get_logs_asks_for_the_range_and_topics_it_was_given(): void
    {
        // The request shape is the whole point: one call covering every address, via a topic
        // array the node treats as OR. Getting this wrong means either a request per address
        // or a filter that matches nothing.
        $container = [];
        $history = \GuzzleHttp\Middleware::history($container);

        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => []])),
        ]));
        $stack->push($history);

        $client = new EvmClient('http://stub', self::BASE_CHAIN_ID, new Client(['handler' => $stack]), 0);

        $client->getLogs(100, 200, '0xtoken', [Erc20::transferEventTopic(), null, ['0xtopicA', '0xtopicB']]);

        $sent = json_decode((string) $container[0]['request']->getBody(), true);
        $filter = $sent['params'][0];

        $this->assertSame('eth_getLogs', $sent['method']);
        $this->assertSame('0x64', $filter['fromBlock']);
        $this->assertSame('0xc8', $filter['toBlock']);
        $this->assertSame('0xtoken', $filter['address']);
        $this->assertSame(['0xtopicA', '0xtopicB'], $filter['topics'][2]);
        // The second position stays null -- constraining `from` would drop every deposit that
        // did not come from one named sender.
        $this->assertNull($filter['topics'][1]);
    }

    public function test_the_head_block_is_returned_as_an_integer(): void
    {
        $this->assertSame(19088743, $this->clientReturning(['0x1234567'])->blockNumber());
    }

    public function test_a_call_carries_the_sender_when_one_is_given(): void
    {
        // Without `from`, msg.sender inside the contract is the ZERO ADDRESS, and an ERC-20
        // asked to simulate a transfer that way reverts with "transfer from the zero address"
        // -- which reads like a broken contract and is really a missing argument. Simulating
        // a transfer before a wallet can afford to send one depends on this.
        $container = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => '0x1'])),
            new Response(200, [], json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => '0x1'])),
        ]));
        $stack->push(\GuzzleHttp\Middleware::history($container));

        $client = new EvmClient('http://stub', self::BASE_CHAIN_ID, new Client(['handler' => $stack]), 0);

        $client->call('0xtoken', '0xdata', '0xsender');
        $withFrom = json_decode((string) $container[0]['request']->getBody(), true)['params'][0];

        $this->assertSame('0xsender', $withFrom['from']);

        // And omitted entirely when not given, rather than sent as null or an empty string:
        // some nodes reject a malformed `from` outright.
        $client->call('0xtoken', '0xdata');
        $without = json_decode((string) $container[1]['request']->getBody(), true)['params'][0];

        $this->assertArrayNotHasKey('from', $without);
    }
}
