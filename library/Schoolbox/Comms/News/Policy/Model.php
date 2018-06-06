<?php
/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Policy;

class Model
{

    /**
     * The input element's name attribute
     *
     * @var string
     */

    protected $identifier = 'global-moderation';


    /**
     * This configuration is on (active)
     *
     * @var bool
     */
    protected $isActive = false;

    /* Getters/Setters
     */

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }


    /**
     * @param string $identifier
     */
    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }


    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }


    /**
     * @param bool $isActive
     */
    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }



}