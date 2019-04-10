<?php
/**
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\Folder;

use Schoolbox\Filter\AbstractDefinition;
use Schoolbox\Filter;

abstract class Definition extends AbstractDefinition
{
    const KEY_CLASSES = 'classes';
    const KEY_GROUPS = 'groups';

    /**
     * @inheritdoc
     */
    protected function getInitialSelectedState(string $key, string $page, string $roleType): bool
    {
        // No options checked
        return false;
    }


    /**
     * @inheritdoc
     */
    public function getInitialAvailability(string $customAbstractKey, Filter\Page\Model $pageFilter): bool
    {
        // Initial config is both classes and groups are available for all pages
        return true;
    }
}
