<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Badge\CurriculumNode;

use Schoolbox\Filter\AbstractDefinition;

/**
 * Definition for the badge curriculum node filter.
 */
class Definition extends AbstractDefinition
{
    /**
     * @inheritdoc
     */
    protected const NAME = 'Curriculum Code';


    /**
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
        return [];
    }


    /**
     * @inheritdoc
     */
    public static function getPages(): array
    {
        return [];
    }


    /**
     * @inheritdoc
     */
    public function getLabel(string $key): string
    {
        return '';
    }
}
