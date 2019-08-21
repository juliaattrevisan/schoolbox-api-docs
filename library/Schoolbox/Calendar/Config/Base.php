<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Calendar\Config;

use Schoolbox\Collection;

/**
 * Class Base
 * @package Schoolbox\Calendar\Config
 */
class Base
{
    /**
     * @var Collection
     */
    protected $data;

    /**
     * @return Collection
     */
    public function getData(): Collection
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
