<?php
/**
 * Trait for model classes - adds a urlRegex field.
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Mapper\Traits\Model;

trait UrlRegex
{
    /**
     * @var string|null
     */
    protected $urlRegex;

    /**
     * @return string|null
     */
    public function getUrlRegex(): ?string
    {
        return $this->urlRegex;
    }

    /**
     * @param string|null $urlRegex
     *
     * @return self
     */
    public function setUrlRegex(?string $urlRegex): self
    {
        $this->urlRegex = $urlRegex;
        return $this;
    }
}
