<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
     * @param array|null $chromeData Global template data available to all data
     *                               generators.
     *                               May contain the keys:
     *                               * title: a page title
     *                               * tab:   the currently selected tab
     * @return array
     */
    public function generate(?array $chromeData = []): array;
}
