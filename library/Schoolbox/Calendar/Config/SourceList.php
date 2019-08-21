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
     * SourceList constructor.
     * @param IConfigLoader $configMapper
     */
    public function __construct(IConfigLoader $configMapper)
    {
        $this->configMapper = $configMapper;
    }

    /**
     * @return $this
     */
    public function generate(): self
    {
        $this->data = new Collection(
            Calendar_Api_Config_Mapper::getInstance()->getSourceListForCalendar(new ModuleChecker($this->configMapper))
        );

        return $this;
    }
}
