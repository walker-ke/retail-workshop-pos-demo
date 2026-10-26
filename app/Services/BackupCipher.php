<?php
namespace App\Services;

use RuntimeException;

class BackupCipher
{
    private const MAGIC = "RETAILDEMO-BACKUP-1\n";
    private const CHUNK = 1048576;

    public function encrypt(string $source, string $target, string $key): void
    {
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        $in = fopen($source, 'rb'); $out = fopen($target, 'xb');
        if (!$in || !$out) throw new RuntimeException('Cannot open backup files.');
        chmod($target, 0600);
        try {
            fwrite($out, self::MAGIC.$header);
            while (!feof($in)) {
                $data = fread($in, self::CHUNK);
                $tag = feof($in) ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : 0;
                $cipher = sodium_crypto_secretstream_xchacha20poly1305_push($state, $data, '', $tag);
                $frame = pack('N', strlen($cipher)).$cipher;
                if (fwrite($out, $frame) !== strlen($frame)) throw new RuntimeException('Not enough disk space to save backup.');
            }
        } finally { fclose($in); fclose($out); }
    }

    public function decrypt(string $source, string $target, string $key): void
    {
        $in = fopen($source, 'rb'); $out = fopen($target, 'xb');
        if (!$in || !$out) throw new RuntimeException('Cannot open backup files.');
        chmod($target, 0600);
        try {
            if (fread($in, strlen(self::MAGIC)) !== self::MAGIC) throw new RuntimeException('Unsupported backup format.');
            $header = fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            if (strlen($header) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES) throw new RuntimeException('Damaged backup.');
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
            while (!feof($in)) {
                $size = fread($in, 4);
                if (strlen($size) !== 4) throw new RuntimeException('Backup is incomplete.');
                $length = unpack('N', $size)[1];
                if ($length < 17 || $length > self::CHUNK + 17) throw new RuntimeException('Invalid backup frame.');
                $cipher = '';
                while (strlen($cipher) < $length && !feof($in)) $cipher .= fread($in, $length - strlen($cipher));
                $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher);
                if ($result === false) throw new RuntimeException('Backup authentication failed.');
                [$data, $tag] = $result;
                if (fwrite($out, $data) !== strlen($data)) throw new RuntimeException('Not enough disk space.');
                if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    if (fread($in, 1) !== '') throw new RuntimeException('Unexpected trailing backup data.');
                    return;
                }
            }
            throw new RuntimeException('Backup is incomplete.');
        } finally { fclose($in); fclose($out); }
    }
}
