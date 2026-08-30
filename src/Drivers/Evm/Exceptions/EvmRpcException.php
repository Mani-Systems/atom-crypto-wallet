<?php

namespace ManiSystems\CryptoWallet\Drivers\Evm\Exceptions;

use RuntimeException;

/**
 * Raised when an EVM node call fails, or when the node disagrees with our configuration
 * about which chain it is serving.
 */
class EvmRpcException extends RuntimeException
{
}
