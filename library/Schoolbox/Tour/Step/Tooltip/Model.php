<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Tour\Step\Tooltip;

use Schoolbox\Tour\Step\AbstractModel;

class Model extends AbstractModel
{
    /**
     * @var string|null
     */
    protected $javascriptSelector;

    /**
     * @return string|null
     */
    public function getJavascriptSelector(): ?string
    {
        return $this->javascriptSelector;
    }

    /**
     * @param string|null $javascriptSelector
     *
     * @return self
     */
    public function setJavascriptSelector(?string $javascriptSelector): self
    {
        $this->javascriptSelector = $javascriptSelector;
        return $this;
    }
}