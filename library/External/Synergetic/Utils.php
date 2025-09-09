<?php

namespace External\Synergetic;

class Utils
{
    /**
     * Convert a string containing a mixture of Unix and Dos style line endings (LF or CRLF) to Dos style only (CRLF).
     * @param string $string
     * @return string
     */
    public static function convertLineEndingsUnixToDos(string $string): string
    {
        // First convert all CRLF and CR to LF.
        $string = str_replace(["\r\n", "\r"], "\n", $string);

        // Then convert all LF to CRLF.
        return str_replace("\n", "\r\n", $string);
    }
}
