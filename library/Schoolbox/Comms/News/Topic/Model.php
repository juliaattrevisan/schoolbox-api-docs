<?php

/**
 * Topic for school news
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Topic;

use Alaress\Json\Serializable;
use Alaress\Json\SerializeTrait;
use Schoolbox\ISoftDeletable;
use Schoolbox\Sequential;
use Schoolbox\Mapper\Traits\Model as ModelTrait;

class Model implements Serializable, Sequential, ISoftDeletable
{
    use ModelTrait\Id;
    use ModelTrait\HasSequence;
    use SerializeTrait;
    use ModelTrait\UpdatedBy;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;


    /** @var string */
    private $name;

    /** @var string */
    private $slug;

    /**
     * @param string $name
     * @return Model
     * @throws \DomainException
     * @throws \InvalidArgumentException
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }


    /**
     * @return null|string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param null|string $slug
     * @return Model $this
     */
    public function setSlug(?string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    /**
     * Returns the slug version of the name
     * @return string
     */
    public function getSlug(): ?string
    {
        return $this->slug;
    }

    /**
     * @return array
     */
    public function getDefaultJsonMap(): array
    {
        return [
            'id',
            'name',
            'sequence',
        ];
    }
}
