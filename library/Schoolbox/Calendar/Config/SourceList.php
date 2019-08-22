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
use Schoolbox\IConfigLoader;

/**
 * Class SourceList
 * @package Schoolbox\Calendar\Config
 */
class SourceList extends Base
{
    /**
     * @var IConfigLoader
     */
    private $configMapper;

    /**
     * @var Calendar_Api_Config_Mapper
     */
    private $calApiConfigMapper;

    /**
     * SourceList constructor.
     * @param IConfigLoader              $configMapper
     * @param Calendar_Api_Config_Mapper $calApiConfigMapper
     */
    public function __construct(IConfigLoader $configMapper, Calendar_Api_Config_Mapper $calApiConfigMapper)
    {
        $this->configMapper         = $configMapper;
        $this->calApiConfigMapper   = $calApiConfigMapper;
    }

    /**
     * @return $this
     */
    public function generate(): self
    {
        $this->data = new Collection(
            $this->calApiConfigMapper->getSourceListForCalendar(new ModuleChecker($this->configMapper))
        );

        return $this;
    }
}
