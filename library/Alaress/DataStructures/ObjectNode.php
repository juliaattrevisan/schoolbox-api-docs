<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Alaress\DataStructures;

/**
 * Simple node to hold an element of any type.
 */
class ObjectNode
{

    /** @var object */
    private $element;

    /** @var self[] */
    private $children;


    /**
     * @param object $element
     * @param self[] $children
     */
    public function __construct($element, array $children)
    {
        $this->element  = $element;
        $this->children = $children;
    }


    /**
     * @return object
     */
    public function getElement()
    {
        return $this->element;
    }


    /**
     * @return self[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }
}
