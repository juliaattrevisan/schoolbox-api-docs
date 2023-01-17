<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\DueWork;

use Schoolbox\ISoftDeletable;
use Schoolbox\Sequential;
use Schoolbox\Mapper\Traits\Model as ModelTrait;

class State implements Sequential, ISoftDeletable
{
    use ModelTrait\HasSequence;
    use ModelTrait\UpdatedBy;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;

    /**
     * @var int|null
     */
    protected ?int $id;

    /**
     * @var string|null
     */
    protected ?string $code = null;

    /**
     * @var string|null
     */
    protected ?string $displayAs = null;

    /**
     * @var float|null $normaliseAs
     */
    protected $normaliseAs = null;

    /**
     * @param string|null $code
     *
     * @return self
     */
    public function setCode(?string $code): self
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getCode(): ?string
    {
        return $this->code;
    }

    /**
     * @param string|null $displayAs
     *
     * @return static
     */
    public function setDisplayAs(?string $displayAs): self
    {
        $this->displayAs = $displayAs;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getDisplayAs(): ?string
    {
        return $this->displayAs;
    }

    /**
     * @param int|null $id
     *
     * @return static
     */
    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param float|null $normaliseAs
     *
     * @return static
     */
    public function setNormaliseAs($normaliseAs): self
    {
        $this->normaliseAs = $normaliseAs;
        return $this;
    }

    /**
     * @return float|null
     */
    public function getNormaliseAs()
    {
        return $this->normaliseAs;
    }
}
