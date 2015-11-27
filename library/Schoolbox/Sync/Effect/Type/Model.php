<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Effect\Type;

use \Schoolbox\Sync\Effect\Type\Mapper as EffectTypeMapper;

class Model {

    /** @var int */
    protected $id;

    /** @var string */
    protected $name;

    /**
     * Container Template file for Skip Effect
     * @var string
     */
    private $effectSkipTemplate = '@syncEffect/container-skip.twig';

    /**
     * Container Template file for regular other Effects
     *
     * @var string
     */
    private $effectTemplate = '@syncEffect/container.twig';

    /**
     * Decide which Container to use for this Effect
     *
     * @return string
     */
    public function getTemplate() {
        if ($this->isSkip()) {
            return $this->getEffectSkipTemplate();
        }

        return $this->getEffectTemplate();
    }

    /**
     * Is this a Skip Effect?
     *
     * @return bool
     */
    public function isSkip() {
        if ($this->getId() == EffectTypeMapper::EFFECT_TYPE_SKIP) {
            return true;
        }

        return false;
    }

    /* Getters/Setters
     */

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return static
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return static
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Set Effect Skip Template
     * @param string $effectSkipTemplate
     */
    private function setEffectSkipTemplate($effectSkipTemplate)
    {
        $this->effectSkipTemplate = $effectSkipTemplate;
    }

    /**
     * Get Effect Skip Template
     *
     * @return string
     */
    private function getEffectSkipTemplate()
    {
        return $this->effectSkipTemplate;
    }

    /**
     * Set Effect Template
     *
     * @param string $effectTemplate
     */
    private function setEffectTemplate($effectTemplate)
    {
        $this->effectTemplate = $effectTemplate;
    }

    /**
     * Get Effect Template
     *
     * @return string
     */
    private function getEffectTemplate()
    {
        return $this->effectTemplate;
    }


}