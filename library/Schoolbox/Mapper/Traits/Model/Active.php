<?php
/**
 * Trait for model classes - adds a active field.
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Mapper\Traits\Model;

trait Active
{
    /**
     * @var bool|null
     */
    protected $active;

    /**
     * @return bool|null
     */
    public function getActive(): ?bool
    {
        return $this->active;
    }

    /**
     * @param bool|null $active
     *
     * @return self|$this
     */
    public function setActive(?bool $active): self
    {
        $this->active = $active;
        return $this;
    }
}
