<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\YearDue;

use Schoolbox\Filter;

/**
 * Available options:
 */
class Definition extends Filter\AbstractDefinition
{

    // The name to expose in the UI
    const NAME = 'Year Due';


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
    public function getLabel($key): string
    {
        return $key;
    }


    /**
     * @param string $key The option's abstract key
     * @param string $page
     * @param string $roleType
     * @return bool
     */
    protected function getInitialSelectedState($key, $page, $roleType): bool
    {
        // No options checked
        return false;
    }


    /**
     * @param string            $customAbstractKey
     * @param Filter\Page\Model $pageFilter
     * @return bool
     */
    public function getInitialAvailability($customAbstractKey, Filter\Page\Model $pageFilter): bool
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
