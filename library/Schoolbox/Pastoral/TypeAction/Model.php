<?php

/**
 *
 * @copyright   Copyright 2022, Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\TypeAction;

use Schoolbox\Mapper\Traits\Model as ModelTraits;
use Schoolbox\Pastoral\Action\Model as Action;

/**
 * Class Model
 *
 * @package Schoolbox\Pastoral\RecordAction
 */
class Model
{
    use ModelTraits\Id;

    private ?int $typeId = null;

    private ?int $subtypeId = null;

    /**
     * @var Action|null
     */
    private ?Action $action = null;

    /**
     * Completion status
     * @var bool
     */
    private bool $default = false;

    /**
     * @return int|null
     */
    public function getTypeId(): ?int
    {
        return $this->typeId;
    }

    /**
     * @param int|null $typeId
     * @return static
     */
    public function setTypeId(?int $typeId): self
    {
        $this->typeId = $typeId;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getSubtypeId(): ?int
    {
        return $this->subtypeId;
    }

    /**
     * @param int|null $subtypeId
     * @return static
     */
    public function setSubtypeId(?int $subtypeId): self
    {
        $this->subtypeId = $subtypeId;
        return $this;
    }

    /**
     * @return Action|null
     */
    public function getAction(): ?Action
    {
        return $this->action;
    }

    /**
     * @param Action|null $action
     * @return static
     */
    public function setAction(?Action $action): self
    {
        $this->action = $action;
        return $this;
    }

    /**
     * @return bool
     */
    public function getDefault(): bool
    {
        return $this->default;
    }

    /**
     * @param bool $default
     * @return static
     */
    public function setDefault(bool $default): self
    {
        $this->default = $default;
        return $this;
    }
}
