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
    protected $label = '';

    /**
     * Twig template to represent controls in writable config screen
     * Also, the input name
     *
     * (you can't and shouldn't easily mix things up inadvertently)
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
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
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