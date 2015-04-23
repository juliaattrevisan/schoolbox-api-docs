<?php
/**
 * Topic for school news
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\News;

class Topic
{

    /** @var int */
    protected $id;

    /** @var string */
    protected $name;

    /** @var  string */
    protected $slug;

    /** @var  int */
    protected $sequence;

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
        $url = new \Url($name);
        $this->setSlug($url->getSlug());
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
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;
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
     */
    public function setSequence($sequence)
    {
        $this->sequence = $sequence;
    }

    /**
     * @return int
     */
    public function getSequence()
    {
        return $this->sequence;
    }


} 