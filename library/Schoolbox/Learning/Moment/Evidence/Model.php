<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence;

use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\Resource\File\IFile;
use Schoolbox\Resource\File\StorableTrait;
use Schoolbox\User;

class Model implements IFile
{
    use StorableTrait;
    use ModelTrait\CreatedAtDate;


    /**
     * @var \Schoolbox\Learning\Moment\Model|null
     */
    private ?\Schoolbox\Learning\Moment\Model $moment = null;


    /**
     * @var string|null
     */
    private ?string $type = null;


    /**
     * @var string|null
     */
    private ?string $hash = null;


    /**
     * @return User
     */
    public function getOwner(): User
    {
        return $this->moment->getCreator();
    }


    /**
     * @return \Schoolbox\Learning\Moment\Model|null
     */
    public function getMoment(): ?\Schoolbox\Learning\Moment\Model
    {
        return $this->moment;
    }


    /**
     * @param \Schoolbox\Learning\Moment\Model|null $moment
     * @return $this
     */
    public function setMoment(?\Schoolbox\Learning\Moment\Model $moment): self
    {
        $this->moment = $moment;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getType(): ?string
    {
        return $this->type;
    }


    /**
     * @param string|null $type
     * @return $this
     */
    public function setType(?string $type): self
    {
        $this->type = $type;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getHash(): ?string
    {
        return $this->hash;
    }


    /**
     * @param string|null $hash
     * @return $this
     */
    public function setHash(?string $hash): self
    {
        $this->hash = $hash;
        return $this;
    }
}
