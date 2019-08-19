<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Calendar\Config;

/**
 * Class Base
 * @package Schoolbox\Calendar\Config
 */
class Base
{
    /**
     * @var \Schoolbox\Collection
     */
    protected $data;

    /**
     * @return \Schoolbox\Collection
     */
    public function getData(): \Schoolbox\Collection
    {
        return $this->data;
    }

    /**
     * @return false|string
     */
    public function toJson()
    {
        return json_encode($this->data);
    }
}
