<?php

/**
 * IBIS msgID generator
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\InternationalBaccalaureate;

use Closure;
use InvalidArgumentException;
use RuntimeException;

class MsgIdGenerator
{
    public function __construct(
        private ?Closure $plainTextGenerator = null
    ) {
    }

    public function generate(string $secretKeyHex): string
    {
        $secretKeyHex = strtoupper(trim($secretKeyHex));
        if (strlen($secretKeyHex) !== 32 || !ctype_xdigit($secretKeyHex)) {
            throw new InvalidArgumentException('IBIS secret key must be a 32-character hexadecimal string.');
        }

        $key = hex2bin($secretKeyHex);
        if ($key === false) {
            throw new InvalidArgumentException('IBIS secret key must be valid hexadecimal.');
        }

        $plainText = $this->generatePlainText();
        $encryptedData = openssl_encrypt($plainText, 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        if ($encryptedData === false) {
            throw new RuntimeException('Unable to encrypt IBIS msgID plaintext.');
        }

        return $plainText . strtoupper(hash('sha256', base64_encode($encryptedData)));
    }

    private function generatePlainText(): string
    {
        if ($this->plainTextGenerator !== null) {
            $plainText = strtoupper(($this->plainTextGenerator)());
        } else {
            $plainText = strtoupper(bin2hex(random_bytes(16)));
        }

        if (strlen($plainText) !== 32 || !ctype_xdigit($plainText)) {
            throw new InvalidArgumentException('IBIS msgID plaintext must be a 32-character hexadecimal string.');
        }

        return $plainText;
    }
}
