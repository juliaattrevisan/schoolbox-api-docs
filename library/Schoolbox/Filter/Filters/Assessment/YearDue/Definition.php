<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\YearDue;

use Schoolbox\Filter;

/**
 * Available options:
 */
class Definition extends Filter\AbstractDefinition
{

    // The name to expose in the UI
    protected const NAME = 'Year Due';


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
     * @param string $key
     * @return string
     */
    public function getLabel(string $key): string
    {
        return $key;
    }

    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Assessment/YearDue/templates';
    }
}
