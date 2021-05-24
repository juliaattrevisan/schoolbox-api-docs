<?php

namespace Alaress\Mail;

class Codec
{
    public const COMMA_REPLACEMENT = 0x1B;

    /**
     * @param string $decodedEmail
     * @return string
     */
    public static function encode(string $decodedEmail): string
    {
        return str_replace(',', chr(self::COMMA_REPLACEMENT), $decodedEmail);
    }

    /**
     * @param string $encodedEmail
     * @return string
     */
    public static function decode(string $encodedEmail): string
    {
        return str_replace(chr(self::COMMA_REPLACEMENT), ',', $encodedEmail);
    }
}
