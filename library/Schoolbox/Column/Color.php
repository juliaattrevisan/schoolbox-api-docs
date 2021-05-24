<?php

/**
 * Color column type.
 *
 * Accepts anything recognized by ColorFactory::build().
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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

    /**
     * Builds a value from the color factory. If that fails, default to black.
     *
     * @param  string $value
     * @return ColorObject
     */
    public function buildFromValue($value)
    {
        try {
            return $this->colorFactory->build($value);
        } catch (\InvalidArgumentException $e) {
            return $this->colorFactory->build('000000');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hydrate($value, $mapper = null)
    {
        if (is_null($value)) {
            return null;
        }
        return $this->buildFromValue($value);
    }

    /**
     * {@inheritdoc}
     */
    public function dehydrate($value, $mapper = null)
    {
        if (is_null($value)) {
            return null;
        }

        if ($value instanceof ColorObject) {
            return $value->getAsHexAlpha();
        }
        return $this->buildFromValue($value)->getAsHexAlpha();
    }


    /**
     * @inheritDoc
     */
    public function isScalar(): bool
    {
        return false;
    }
}
