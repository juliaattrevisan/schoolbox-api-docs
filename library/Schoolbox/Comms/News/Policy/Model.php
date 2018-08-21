<?php
/**
 * Base
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Policy;

use Alaress\Json\SerializeTrait;
use Schoolbox\Mapper\Traits\Model\Id;

class Model implements \Alaress\Json\Serializable
{
    use Id;
    use SerializeTrait;


    /**
     * @return array
     */
    public function getDefaultJsonMap(): array
    {
        return [
            'identifier',
            'value'
        ];
    }


    /**
     * A unique identifier (not the primary key) that identifies this input element
     *
     * @var string
     */
    private $identifier;

    /**
     * The value that was set for this policy
     *
     * @var string
     */
    private $value;

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
     * @return $this
     */
    public function setIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;
        return $this;
    }


    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }


    /**
     * @param string $value
     * @return $this
     */
    public function setValue(string $value): self
    {
        $this->value = $value;
        return $this;
    }
}
