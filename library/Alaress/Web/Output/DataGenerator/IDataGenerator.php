<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Web\Output\DataGenerator;

/**
 * Interface for web output data generators.
 */
interface IDataGenerator
{
    /**
     * Generates data for injection into a web template.
     *
     * @return array
     */
    public function generate(): array;
}
