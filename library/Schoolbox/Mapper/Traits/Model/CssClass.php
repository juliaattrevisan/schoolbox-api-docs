<?php
/**
 * Trait for model classes - adds a javascript selector field.
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Mapper\Traits\Model;

trait CssClass
{
    /**
     * @var string|null
     */
    protected $cssClass;

    /**
     * @return string|null
     */
    public function getCssClass(): ?string
    {
        return $this->cssClass;
    }

    /**
     * @param string|null $cssClass
     *
     * @return self
     */
    public function setCssClass(?string $cssClass): self
    {
        $this->cssClass = $cssClass;
        return $this;
    }
}
