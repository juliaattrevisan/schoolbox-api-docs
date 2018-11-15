<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Web\Output\DataGenerator;

use Alaress\Web\Output\ThemedHtml;

/**
 * Interface for web output data generators.
 */
interface IDataGenerator
{
    /**
     * Generates data for injection into a web template.
     *
     * @param ThemedHtml $output The output generator. Contains tabs, title,
     *                           other things.
     * @return array
     */
    public function generate(ThemedHtml $output): array;
}
