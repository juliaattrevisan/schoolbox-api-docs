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
class Base implements \JsonSerializable
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
     * @return mixed|Collection
     */
    public function jsonSerialize()
    {
        return $this->data;
    }
}
