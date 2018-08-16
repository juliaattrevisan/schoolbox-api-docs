<?php
/**
 * Topic for school news
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\News;

use Alaress\Json\SerializeTrait;
use Schoolbox\Mapper\Traits\Model\Id;

class Topic implements \JsonSerializable
{
    use Id;
    use SerializeTrait;

    /** @var string */
    private $name;

    /** @var string */
    private $slug;

    /** @var int */
    private $sequence;

    /**
     * @param $name
     * @return Topic
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
     * Serialized representation
     *
     * @return array|mixed
     */
    public function getDefaultJsonMap()
    {
        return [
            'id',
            'name',
            'sequence',
        ];
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
     * @param int $sequence
     * @return $this
     */
    public function setSequence($sequence): self
    {
        $this->sequence = $sequence;
        return $this;
    }

    /**
     * Sequence may not always be set if you just...accept the defaults
     *
     * @return int|null
     */
    public function getSequence(): ?int
    {
        return $this->sequence;
    }
}