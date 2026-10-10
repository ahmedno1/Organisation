<?php

namespace App\Services;

class TotpService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $length = 32): string
    {
        $alphabet = self::BASE32_ALPHABET;
        $max = strlen($alphabet) - 1;
        $secret = '';

        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[random_int(0, $max)];
        }

        return $secret;
    }

    public function provisioningUri(string $accountName, string $secret, string $issuer): string
    {
        $label = rawurlencode($issuer.':'.$accountName);

        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            $label,
            rawurlencode($secret),
            rawurlencode($issuer)
        );
    }

    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $normalized = preg_replace('/\s+/', '', $code) ?? '';
        if (! preg_match('/^\d{6}$/', $normalized)) {
            return false;
        }

        $timeSlice = (int) floor(time() / 30);
        for ($offset = -$window; $offset <= $window; $offset++) {
            $otp = $this->hotp($secret, $timeSlice + $offset);
            if (hash_equals($otp, $normalized)) {
                return true;
            }
        }

        return false;
    }

    private function hotp(string $secret, int $counter): string
    {
        $binarySecret = $this->base32Decode($secret);
        $counterBytes = pack('N*', 0).pack('N*', $counter);
        $hash = hash_hmac('sha1', $counterBytes, $binarySecret, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncatedHash = substr($hash, $offset, 4);
        $value = unpack('N', $truncatedHash)[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        $alphabet = array_flip(str_split(self::BASE32_ALPHABET));
        $bits = '';

        foreach (str_split($secret) as $char) {
            if (! array_key_exists($char, $alphabet)) {
                continue;
            }

            $bits .= str_pad(decbin($alphabet[$char]), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $binary .= chr(bindec($chunk));
            }
        }

        return $binary;
    }
}
