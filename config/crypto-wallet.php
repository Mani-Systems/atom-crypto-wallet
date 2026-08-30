<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Driver
    |--------------------------------------------------------------------------
    |
    | Which wallet driver WalletManager::driver() resolves when none is named.
    |
    | "evm"   -- self-managed keys, signed locally, broadcast over JSON-RPC. Cost per
    |            transaction is gas only (fractions of a cent on Base), with no custodian
    |            fee. The tradeoff is that this application holds the private keys.
    | "bitgo" -- keys held by BitGo. No key-management burden, but BitGo charges per
    |            wallet and per transaction.
    |
    */

    'default' => env('CRYPTO_WALLET_DRIVER', 'evm'),

    'drivers' => [

        'evm' => [

            /*
            |------------------------------------------------------------------
            | Default Network
            |------------------------------------------------------------------
            |
            | Base is the cheapest of the Paycrest-supported EVM chains by a wide
            | margin -- roughly $0.002 per ERC-20 transfer against ~$0.15 on BNB
            | Smart Chain. Prefer it unless a user's funds are already elsewhere.
            |
            */

            'default_network' => env('EVM_DEFAULT_NETWORK', 'base'),

            /*
            |------------------------------------------------------------------
            | Networks
            |------------------------------------------------------------------
            |
            | chain_id is used for EIP-155 replay protection and MUST match the RPC
            | endpoint -- signing for one chain and broadcasting to another is how a
            | transaction becomes replayable. The driver verifies this on connect.
            |
            | These four are the networks Paycrest currently settles from.
            |
            */

            'networks' => [
                'base' => [
                    'chain_id' => 8453,
                    'rpc'      => env('EVM_RPC_BASE', 'https://mainnet.base.org'),
                    'explorer' => 'https://basescan.org',
                ],
                'polygon' => [
                    'chain_id' => 137,
                    // polygon-rpc.com now answers 401 ("API key disabled, tenant disabled"),
                    // so it is no longer a usable default.
                    'rpc'      => env('EVM_RPC_POLYGON', 'https://polygon-bor-rpc.publicnode.com'),
                    'explorer' => 'https://polygonscan.com',
                ],
                'arbitrum-one' => [
                    'chain_id' => 42161,
                    'rpc'      => env('EVM_RPC_ARBITRUM', 'https://arb1.arbitrum.io/rpc'),
                    'explorer' => 'https://arbiscan.io',
                ],
                'bnb-smart-chain' => [
                    'chain_id' => 56,
                    'rpc'      => env('EVM_RPC_BSC', 'https://bsc-dataseed.binance.org'),
                    'explorer' => 'https://bscscan.com',
                ],
            ],

            /*
            |------------------------------------------------------------------
            | Token Contracts
            |------------------------------------------------------------------
            |
            | DELIBERATELY EMPTY. Token addresses are not shipped as defaults because a
            | wrong address here does not throw -- it sends real funds to a contract
            | nobody controls, or to a look-alike token with the same symbol. Every
            | chain has counterfeit USDC/USDT deployments trading on that exact mistake.
            |
            | Populate from the issuer's own documentation (Circle for USDC, Tether for
            | USDT) -- not from a block explorer search, and not from this file. Verify
            | each address on the chain's explorer before putting it in production, and
            | confirm `decimals` against the contract rather than assuming 6.
            |
            |   'base' => [
            |       'USDC' => ['address' => '0x...', 'decimals' => 6],
            |   ],
            |
            */

            'tokens' => [
                'base'            => [],
                'polygon'         => [],
                'arbitrum-one'    => [],
                'bnb-smart-chain' => [],
            ],

            /*
            |------------------------------------------------------------------
            | Gas
            |------------------------------------------------------------------
            |
            | tip_multiplier pads the node's suggested priority fee; base_fee_multiplier
            | pads the block base fee so a transaction stays valid across a few blocks of
            | rising demand. gas_limit_buffer_percent pads eth_estimateGas, which returns
            | the cost of the transaction as simulated against the CURRENT state -- an
            | ERC-20 transfer to an address holding zero balance costs more than to one
            | already holding some, so the estimate can be short by the time it lands.
            |
            */

            'gas' => [
                'tip_multiplier'           => (float) env('EVM_GAS_TIP_MULTIPLIER', 1.2),
                'base_fee_multiplier'      => (float) env('EVM_GAS_BASE_FEE_MULTIPLIER', 2.0),
                'gas_limit_buffer_percent' => (int) env('EVM_GAS_LIMIT_BUFFER', 20),
            ],

            /*
            |------------------------------------------------------------------
            | RPC
            |------------------------------------------------------------------
            */

            'rpc' => [
                'timeout' => (int) env('EVM_RPC_TIMEOUT', 15),
                'retries' => (int) env('EVM_RPC_RETRIES', 2),
            ],
        ],

        'bitgo' => [
            /*
            |------------------------------------------------------------------
            | Use Mocks
            |------------------------------------------------------------------
            |
            | This option determines if the application should use mocks for Bitgo
            | API calls. This is useful for testing purposes.
            |
            */
            'use_mocks' => env('BITGO_USE_MOCKS', true),

            /*
            |------------------------------------------------------------------
            | Testnet
            |------------------------------------------------------------------
            |
            | This option determines if the application should use the Bitgo testnet
            | instead of the mainnet. Set this to true for testing and development.
            |
            */
            'testnet' => env('BITGO_TESTNET', true),

            /*
            |------------------------------------------------------------------
            | API Key
            |------------------------------------------------------------------
            |
            | This option sets the API key for the Bitgo API.
            |
            */
            'api_key' => env('BITGO_API_KEY', null),

            /*
            |------------------------------------------------------------------
            | Express API URL
            |------------------------------------------------------------------
            |
            | This option sets the Express API URL for the Bitgo API.
            |
            */
            'express_api_url' => env('BITGO_EXPRESS_API_URL', 'http://localhost:3080/api/v2/'),

            /*
            |------------------------------------------------------------------
            | Default Coin
            |------------------------------------------------------------------
            |
            | This option sets the default coin for Bitgo API calls.
            |
            */
            'default_coin' => env('BITGO_DEFAULT_COIN', 'tbtc4'),

            /*
            |------------------------------------------------------------------
            | Webhook Callback URL
            |------------------------------------------------------------------
            |
            | This option sets the webhook callback URL for the Bitgo API.
            |
            */
            'webhook_callback_url' => env('BITGO_WEBHOOK_CALLBACK', null),
        ],
    ],

];
