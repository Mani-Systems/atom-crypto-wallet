<?php

namespace ManiSystems\CryptoWallet\Drivers\Evm\Support;

use Elliptic\EC;
use InvalidArgumentException;
use kornrunner\Keccak;
use RuntimeException;
use SensitiveParameter;

/**
 * A secp256k1 keypair and its EVM address.
 *
 * Keys are generated independently per wallet rather than derived from one BIP-32 master
 * seed. That is a deliberate trade: HD derivation gives you seed-phrase recovery, but it also
 * means a single leaked seed drains every user at once. Independent keys make the blast
 * radius of any one disclosure a single wallet, and the recovery story becomes an encrypted
 * database backup -- which this application already has to keep correct anyway.
 */
final class KeyPair
{
    /** The order of the secp256k1 curve. A valid private key is in [1, n-1]. */
    private const CURVE_ORDER = '115792089237316195423570985008687907852837564279074904382605163141518161494337';

    /**
     * Secrets live OUTSIDE the object, keyed by instance.
     *
     * A private property is still a property: var_export() ignores __debugInfo() and prints
     * it verbatim, and so do serialize(), json_encode() on a cast, and several logger
     * "dump the context" paths. Holding the key in a WeakMap means the object genuinely has
     * no field containing it, so there is nothing for those to find. The entry is collected
     * with the instance, so this does not leak memory.
     */
    private static ?\WeakMap $secrets = null;

    private function __construct(
        #[SensitiveParameter] string $privateKey,
        private readonly string $address,
    ) {
        self::$secrets ??= new \WeakMap();
        self::$secrets[$this] = $privateKey;
    }

    /**
     * Generate a new keypair from a CSPRNG.
     */
    public static function generate(): self
    {
        // random_bytes() throws rather than returning weak output if the system CSPRNG is
        // unavailable, which is the behaviour we want -- a predictable key here is a total loss.
        do {
            $privateKey = bin2hex(random_bytes(32));
        } while (! self::isInCurveRange($privateKey));

        return new self($privateKey, self::addressFromPrivateKey($privateKey));
    }

    /**
     * Rebuild a keypair from a stored private key.
     */
    public static function fromPrivateKey(#[SensitiveParameter] string $privateKey): self
    {
        $privateKey = self::normalise($privateKey);

        if (! self::isInCurveRange($privateKey)) {
            throw new InvalidArgumentException('Private key is not a valid secp256k1 scalar.');
        }

        return new self($privateKey, self::addressFromPrivateKey($privateKey));
    }

    /** Hex private key, no 0x prefix. Treat as a secret: never log it. */
    public function privateKey(): string
    {
        return self::$secrets[$this];
    }

    /** EIP-55 checksummed address. */
    public function address(): string
    {
        return $this->address;
    }

    /**
     * Derive the address: keccak256 of the uncompressed public key (minus its 0x04 prefix),
     * taking the low 20 bytes.
     */
    private static function addressFromPrivateKey(#[SensitiveParameter] string $privateKey): string
    {
        $public = (new EC('secp256k1'))->keyFromPrivate($privateKey)->getPublic(false, 'hex');

        // getPublic(false, ...) returns the uncompressed form, which is 0x04 || X || Y. The
        // 04 marker is not part of the hashed material.
        $body = substr($public, 2);

        $hash = Keccak::hash(hex2bin($body), 256);

        return self::toChecksumAddress('0x' . substr($hash, -40));
    }

    /**
     * EIP-55 mixed-case checksum. Worth doing even though nothing here requires it: a
     * checksummed address makes a typo or truncation detectable by any wallet or explorer
     * the address is later pasted into.
     */
    public static function toChecksumAddress(string $address): string
    {
        $address = strtolower(self::normalise($address));

        if (strlen($address) !== 40) {
            throw new InvalidArgumentException('An EVM address must be 20 bytes.');
        }

        $hash = Keccak::hash($address, 256);
        $out = '';

        for ($i = 0; $i < 40; $i++) {
            $char = $address[$i];
            // Digits have no case to carry the checksum; only a-f are up/down-cased.
            $out .= ctype_digit($char) ? $char : (hexdec($hash[$i]) >= 8 ? strtoupper($char) : $char);
        }

        return '0x' . $out;
    }

    /**
     * Verify an address against its own EIP-55 checksum. An all-lower or all-upper address
     * carries no checksum and is accepted as unverifiable rather than rejected.
     */
    public static function isValidAddress(string $address): bool
    {
        $stripped = self::normalise($address);

        if (! preg_match('/^[0-9a-fA-F]{40}$/', $stripped)) {
            return false;
        }

        if ($stripped === strtolower($stripped) || $stripped === strtoupper($stripped)) {
            return true;
        }

        return self::toChecksumAddress($stripped) === '0x' . $stripped;
    }

    private static function isInCurveRange(string $privateKey): bool
    {
        if (! preg_match('/^[0-9a-fA-F]{64}$/', $privateKey)) {
            return false;
        }

        $value = gmp_init($privateKey, 16);

        // Zero is not a valid scalar, and anything >= the curve order wraps -- both would
        // produce a key that does not behave as its bytes suggest.
        return gmp_cmp($value, 0) > 0 && gmp_cmp($value, gmp_init(self::CURVE_ORDER, 10)) < 0;
    }

    private static function normalise(string $hex): string
    {
        return str_starts_with($hex, '0x') || str_starts_with($hex, '0X')
            ? substr($hex, 2)
            : $hex;
    }

    /**
     * Keep secrets out of dumps, stack traces and logs. var_dump()/print_r() on this object,
     * or on any exception trace carrying it, must not surface the private key.
     */
    public function __debugInfo(): array
    {
        return ['address' => $this->address, 'privateKey' => '[redacted]'];
    }

    /**
     * var_export() has no interception hook, so the only defence is the WeakMap above --
     * it can export nothing it cannot see. This is here to say that out loud, because the
     * obvious "private property plus __debugInfo" version of this class leaks.
     */

    public function __serialize(): array
    {
        throw new RuntimeException('KeyPair must not be serialized; store the encrypted private key instead.');
    }
}
