<?php
/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\Config\Policy;

class Base
{

    /**
     * Policy name
     *
     * @var string
     */
    protected $name = '';

    /**
     * The input element's name attribute
     *
     * @var string
     */

    protected $identifier = '';

    /**
     * Config twig file
     *
     * @var string
     */
    protected $template = 'checkbox';

    /**
     * This configuration is on (active)
     *
     * @var bool
     */
    protected $isActive = false;

    /**
     * Strings to describe states
     *
     * @var array
     */
    protected $options = [];

    /**
     * Activate this policy
     *
     * @return $this
     */
    public function activate()
    {
        $this->isActive = true;
        return $this;
    }

    /**
     * Deactivate this policy
     *
     * @return $this
     */
    public function deactivate()
    {
        $this->isActive = false;
        return $this;
    }

    /* Getters/Setters
     */

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }


    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }


    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }


    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }


    /**
     * @return string
     */
    public function getTemplate(): string
    {
        return $this->template;
    }
}