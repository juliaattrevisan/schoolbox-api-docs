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

/**
 * Class SourceList
 * @package Schoolbox\Calendar\Config
 */
class SourceList extends Base
{
    /**
     * @var Calendar_Api_Config_Mapper
     */
    private $calApiConfigMapper;

    /**
     * @var ModuleChecker
     */
    private $moduleChecker;

    /**
     * SourceList constructor.
     * @param Calendar_Api_Config_Mapper $calApiConfigMapper
     * @param ModuleChecker              $moduleChecker
     */
    public function __construct(Calendar_Api_Config_Mapper $calApiConfigMapper, ModuleChecker $moduleChecker)
    {
        $this->calApiConfigMapper   = $calApiConfigMapper;
        $this->moduleChecker        = $moduleChecker;
    }

    /**
     * @return $this
     */
    public function generate(): self
    {
        $this->data = new Collection(
            $this->calApiConfigMapper->getSourceListForCalendar($this->moduleChecker)
        );

        return $this;
    }
}
