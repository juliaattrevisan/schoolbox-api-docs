<?php

/**
 * @author      Matthew Schembri <matt.schembri@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Tour\Step\Type\Modal;

use Schoolbox\Tour\Step\Type\AbstractModel;
use Schoolbox\Tour\Step;

class Model extends AbstractModel
{
    /**
     * @var string|null
     */
    protected $title;

    /**
     * Specify data which should be serialized to JSON
     *
     * @link  http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return [
            'title'     => $this->getTitle(),
            'css_class' => $this->getCssClass(),
            'body'      => $this->getBody(),
            'type'      => Step\Mapper::TYPE_MODAL
        ];
    }

    /**
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param string|null $title
     *
     * @return self
     */
    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }
}
