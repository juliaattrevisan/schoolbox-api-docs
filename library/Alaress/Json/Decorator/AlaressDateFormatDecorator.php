<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Json\Decorator;

use Alaress_Date_Format;
use InvalidArgumentException;

/**
 * Post-decorator specifically for `Alaress_Date_Format` fields: converts them
 * from their current serialized format to a RFC3339 string.
 *
 * TODO: this should be removed once `Alaress_Date_Format::jsonSerialize` returns a RFC3339 string
 */
class AlaressDateFormatDecorator implements IDecorator
{
    /**
     * Iterates through `$json`, and replaces anything that looks like a
     * default serialized format `Alaress_Date_Format` into RFC3339.
     *
     * @param array  $json
     * @param object $model
     * @return array
     */
    public function decorate(array $json, $model): array
    {
        foreach ($json as $key => $value) {
            if ($value['database'] ?? null) {
                try {
                    $json[$key] = Alaress_Date_Format::newDatabaseDate($value['database'])->toRfc3339String();
                } catch (InvalidArgumentException $e) {
                    // wasn't a date after all. That's okay
                    continue;
                }
            }
        }
        return $json;
    }
}
