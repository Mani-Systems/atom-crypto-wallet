<?php

namespace ManiSystems\CryptoWallet\Drivers\Evm\Support;

use InvalidArgumentException;
use kornrunner\Keccak;

/**
 * Minimal ABI encoding for the ERC-20 calls this driver needs.
 *
 * Selectors are computed from the canonical signature rather than pasted in as magic
 * constants -- a mistyped selector does not fail loudly, it calls a different function or
 * falls through to the contract's fallback, which for a transfer means losing the funds.
 */
final class Erc20
{
    /** transfer(address,uint256) */
    public static function transfer(string $to, string $amount): string
    {
        return self::selector('transfer(address,uint256)')
            . self::encodeAddress($to)
            . self::encodeUint($amount);
    }

    /** balanceOf(address) */
    public static function balanceOf(string $owner): string
    {
        return self::selector('balanceOf(address)') . self::encodeAddress($owner);
    }

    /** decimals() */
    public static function decimals(): string
    {
        return self::selector('decimals()');
    }

    /**
     * First 4 bytes of the keccak-256 hash of the canonical signature.
     */
    public static function selector(string $signature): string
    {
        return '0x' . substr(Keccak::hash($signature, 256), 0, 8);
    }

    /**
     * Addresses are left-padded to 32 bytes. The high 12 bytes MUST be zero -- anything
     * else is a malformed address, and silently truncating it would send to the wrong place.
     */
    public static function encodeAddress(string $address): string
    {
        $stripped = str_starts_with(strtolower($address), '0x') ? substr($address, 2) : $address;

        if (! preg_match('/^[0-9a-fA-F]{40}$/', $stripped)) {
            throw new InvalidArgumentException("Not a valid EVM address: {$address}");
        }

        return str_pad(strtolower($stripped), 64, '0', STR_PAD_LEFT);
    }

    /**
     * uint256, left-padded to 32 bytes. Accepts a decimal string so callers never have to
     * put a token amount through a float -- 0.1 USDC is not representable in binary
     * floating point, and rounding a value that is about to move money is not acceptable.
     */
    public static function encodeUint(string $value): string
    {
        if (! preg_match('/^[0-9]+$/', $value)) {
            throw new InvalidArgumentException("uint256 must be a non-negative integer string, got: {$value}");
        }

        $hex = gmp_strval(gmp_init($value, 10), 16);

        if (strlen($hex) > 64) {
            throw new InvalidArgumentException('Value exceeds uint256.');
        }

        return str_pad($hex, 64, '0', STR_PAD_LEFT);
    }

    /**
     * Scale a human amount ("12.34") to base units for a token with $decimals places,
     * as a decimal string. Done with string arithmetic on purpose: bcmath/float rounding
     * at this boundary is how you send the wrong amount.
     */
    public static function toBaseUnits(string $amount, int $decimals): string
    {
        if (! preg_match('/^([0-9]+)(?:\.([0-9]*))?$/', trim($amount), $m)) {
            throw new InvalidArgumentException("Not a valid decimal amount: {$amount}");
        }

        $whole = $m[1];
        $fraction = $m[2] ?? '';

        if (strlen($fraction) > $decimals) {
            // Refuse rather than round: the caller asked to move a precision this token
            // cannot represent, and quietly truncating it loses the remainder.
            if (rtrim(substr($fraction, $decimals), '0') !== '') {
                throw new InvalidArgumentException(
                    "Amount {$amount} has more precision than the token's {$decimals} decimals."
                );
            }
            $fraction = substr($fraction, 0, $decimals);
        }

        $fraction = str_pad($fraction, $decimals, '0');

        return ltrim($whole . $fraction, '0') ?: '0';
    }

    /**
     * Inverse of toBaseUnits(), for display.
     */
    public static function fromBaseUnits(string $baseUnits, int $decimals): string
    {
        if (! preg_match('/^[0-9]+$/', $baseUnits)) {
            throw new InvalidArgumentException("Base units must be an integer string, got: {$baseUnits}");
        }

        if ($decimals === 0) {
            return $baseUnits;
        }

        $padded = str_pad($baseUnits, $decimals + 1, '0', STR_PAD_LEFT);
        $whole = substr($padded, 0, -$decimals);
        $fraction = rtrim(substr($padded, -$decimals), '0');

        return $fraction === '' ? $whole : "{$whole}.{$fraction}";
    }
}
