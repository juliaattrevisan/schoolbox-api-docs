<?php

/**
 * Most basic column type - doesn't perform any translation on hydration or
 * dehydration, besides ensuring that values are stored in valid UTF-8.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Column;

class Base implements IColumnType
{
    /**
     * Maximum character length of a TINYTEXT field.
     */
    public const TINYTEXT_MAX_LENGTH = 255;

    /**
     * Maximum character length of a TEXT field.
     */
    public const TEXT_MAX_LENGTH = 65535;

    /**
     * Maximum character length of a MEDIUMTEXT field.
     */
    public const MEDIUMTEXT_MAX_LENGTH = 16777215;

    /**
     * Maximum character length of a LONGTEXT field.
     */
    public const LONGTEXT_MAX_LENGTH = 4294967295;

    public function hydrate($value, $mapper = null)
    {
        return $value;
    }


    public function dehydrate($value, $mapper = null)
    {
        // ensure data going into the database (or rabbit, or redis) is UTF-8 friendly
        // explicit NULL values stay null, though.
        if (!isset($value)) {
            return $value;
        }
        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }


    /**
     * @inheritDoc
     */
    public function isScalar(): bool
    {
        return true;
    }
}
