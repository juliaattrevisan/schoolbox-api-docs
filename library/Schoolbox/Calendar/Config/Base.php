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
     * Should never be an array, but may have been serialized as one so need to
     * handle this case for now.
     *
     * @var Collection|array
     */
    protected Collection|array $data;

    /**
     * @return Collection
     */
    public function getData(): Collection
    {
        return $this->data instanceof Collection
            ? $this->data
            : new Collection($this->data);
    }

    /**
     * @return Collection
     */
    public function jsonSerialize(): Collection
    {
        return $this->getData();
    }
}
