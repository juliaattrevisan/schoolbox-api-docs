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
     * @param $name
     * @return Model
     * @throws \DomainException
     * @throws \InvalidArgumentException
     */
    public function setName($name): self
    {
        $this->name = $name;

        // This should not happen. Just to make sure that this will not break admin news page.
        // Slugs cannot be derived from an empty name value, throwing an InvalidArgumentException
        if (!empty($name)) {
            $url = new \Url($name);
            $this->setSlug($url->getSlug());
        }

        return $this;
    }


    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $slug
     * @return $this
     */
    public function setSlug($slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    /**
     * Returns the slug version of the name
     * @return string
     */
    public function getSlug()
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
