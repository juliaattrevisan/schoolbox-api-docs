<?php
/**
 * Trait for model classes - adds a javascript event field.
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Mapper\Traits\Model;

trait JavascriptEvent
{
    /**
     * @var string|null
     */
    protected $javascriptEvent;

    /**
     * @return string|null
     */
    public function getJavascriptEvent(): ?string
    {
        return $this->javascriptEvent;
    }

    /**
     * @param string|null $javascriptEvent
     *
     * @return self
     */
    public function setJavascriptEvent(?string $javascriptEvent): self
    {
        $this->javascriptEvent = $javascriptEvent;
        return $this;
    }
}
