<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar\Config;

use Calendar\Api\ModuleChecker;
use Calendar_Api_Config_Mapper;
use Schoolbox\Collection;
use Schoolbox\User;

/**
 * Class SourceList
 * @package Schoolbox\Calendar\Config
 */
class SourceList extends Base
{
    /**
     * SourceList constructor.
     * @param Calendar_Api_Config_Mapper $calApiConfigMapper
     * @param ModuleChecker              $moduleChecker
     * @param User                       $user
     */
    public function __construct(private Calendar_Api_Config_Mapper $calApiConfigMapper, private ModuleChecker $moduleChecker, private User $user)
    {
    }

    /**
     * @return $this
     */
    public function generate(): self
    {
        $this->data = new Collection(
            $this->calApiConfigMapper->getSourceListForCalendar($this->moduleChecker, $this->user)
        );

        return $this;
    }
}
