<?php
/**
 * @copyright   Copyright 2017, Alaress Pty Ltd
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
     * @param string $key The option's abstract key
     * @param string $page
     * @param string $roleType
     * @return bool
     */
    protected function getInitialSelectedState(string $key, string $page, string $roleType): bool
    {
        // No options checked
        return false;
    }


    /**
     * @param string            $customAbstractKey
     * @param Filter\Page\Model $pageFilter
     * @return bool
     */
    public function getInitialAvailability(string $customAbstractKey, Filter\Page\Model $pageFilter): bool
    {
        // by default, all YearsDue are available
        return true;
    }


    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Assessment/YearDue/templates';
    }
}
