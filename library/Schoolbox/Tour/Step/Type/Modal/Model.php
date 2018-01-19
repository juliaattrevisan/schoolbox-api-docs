<?php
/**
 * @author      Matthew Schembri <matt.schembri@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Tour\Step\Type\Modal;

use Schoolbox\Tour\Step\Type\AbstractModel;
use Schoolbox\Tour\Step;

class Model extends AbstractModel
{
    const TYPE = Step\Mapper::TYPE_MODAL;

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
            'type'      => $this::TYPE
        ];
    }
}