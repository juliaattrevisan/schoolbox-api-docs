<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox;

use \InvalidArgumentException;
use Schoolbox\Mapper as BaseMapper;

class MapperFactory
{
    /**
     * Get instance of specified mapper
     *
     * @param string $mapperName
     * @throws InvalidArgumentException If mapper class not found
     * @return BaseMapper
     */
    public function get($mapperName)
    {
        if (!class_exists($mapperName)) {
            throw new InvalidArgumentException('Mapper class not found: ' . $mapperName);
        }
        return $mapperName::getInstance();
    }
}