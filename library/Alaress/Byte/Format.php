<?php
/**
 * User: jleckie
 * Date: 9/19/11
 * Time: 8:10 PM
 * To change this template use File | Settings | File Templates.
 */

/**
 * Alaress library for working with computer storage sizes commonly called bytes.  Can be used for converting from and to bytes
 *
 * By default we interpret the prefix as 2^10=1024 rather than 10^3=1000.
 * Technically, the IEC form (eg Mi) always refers to 2^X but the SI form (eg M) may mean 2^X or 10^X depending on context.
 *
 * @see https://en.wikipedia.org/wiki/Binary_prefix
 */
class Alaress_Byte_Format {

    /**
     * Converts a size in bytes to human readable size
     *
     * @param int|float|string $size
     * @param int              $precision
     * @param bool             $useIEC               Use SI or IEC multiplier
     * @param bool             $renderIecExplicitly  If using IEC, should we generate this explicitly
     * @return string
     */
	public static function bytesToString($size, int $precision = 2, bool $useIEC = true, bool $renderIecExplicitly = false): string
    {
        $sign = $size < 0 ? '-' : '';
        $size = abs($size);

        // IEC uses 2^X, SI uses 10^X
        $multiplier = $useIEC ? 1024 : 1000;

        // If using IEC and explicit notation, do render 'i' car
        $a = $useIEC && $renderIecExplicitly
            ? ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB']
            : ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        for ($i = 0; $size >= $multiplier; $i++) {
			$size /= $multiplier;
		}

		return $sign . round($size,$precision) . ' ' . $a[$i];
	}

    /**
     * Converts human readable size into an integer
     *
     * @param string $size
     * @param bool   $assumeIecIfAmbiguous Should we assume IEC or SI standard if string is ambiguous?
     * @return int
     */
	public static function stringToBytes(string $size, bool $assumeIecIfAmbiguous = true): int
    {
        $trimmed = \trim($size);

        // All numeric or Numeric with 'B'
        if (preg_match('/^([\d\.]+)\s?B?$/i', $trimmed, $match)) {
            $mantissa    = $match[1];
            $exponent    = 'B';
            $explicitIEC = false;
        } elseif (preg_match('/^([\d\.]+)\s?([KMGT])(i)?B?$/i', $trimmed, $match)) {
            array_shift($match);
            [$mantissa, $exponent] = $match;
            $explicitIEC = count($match) === 3;
        } else {
            // TODO If parse fails this should throw exception so caller can decide appropriate recovery action
            return 0;
        }

        // What multiple should we use if prefix is ambiguous?
        // eg KiB is explicitly 1024, but K may be 1000 or 1024 depending on use case
	    $multiplier = $explicitIEC || $assumeIecIfAmbiguous ? 1024 : 1000;

	    $power = array_search(strtoupper($exponent), ['B','K','M','G','T']);

        return (int)round($mantissa * ($multiplier ** $power), 0);
	}
}
