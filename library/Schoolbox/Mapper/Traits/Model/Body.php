<?php
/**
 * Trait for model classes - adds a body enum field.
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Mapper\Traits\Model;

trait Body
{
    /**
     * @var string|null
     */
    protected $body;

    /**
     * @return string|null
     */
    public function getBody(): ?string
    {
        return $this->body;
    }

    /**
     * @param string|null $body
     *
     * @return self
     */
    public function setBody(?string $body): self
    {
        $this->body = $body;
        return $this;
    }
}
