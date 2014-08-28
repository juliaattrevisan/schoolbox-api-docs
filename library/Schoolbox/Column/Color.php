<?php
/**
 * Color column type.
 *
 * Accepts anything recognized by ColorFactory::build().
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Column;

use Alaress\Color\Color as ColorObject;
use Alaress\Color\ColorFactory;

class Color implements IColumnType
{
    /**
     * @var ColorFactory
     */
    protected $colorFactory;

    public function __construct()
    {
        $this->colorFactory = new ColorFactory();
    }

    public function hydrate($value, $mapper = null)
    {
        return $this->colorFactory->build($value);
    }

    public function dehydrate($value, $mapper = null)
    {
        if ($value instanceof ColorObject) {
            return $value->getAsHexAlpha();
        }
        return $this->colorFactory->build($value)->getAsHexAlpha();
    }
}
