<?php

/**
 * Component_Eportfolio_List
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Component\Eportfolio;

abstract class ListItem
{

    /**
     * Primary key
     *
     * @var int
     */
    public $id;

    /**
     * List to which this list item belongs
     *
     * @var int
     */
    public $component_instance_id;

    /**
     * List item contents
     *
     * @var string
     */
    public $content;

    /**
     * Order of the list item
     *
     * @var int
     */
    public $sequence;

    /**
     * Set component instance id
     *
     * @param int $component_instance_id
     */
    public function setComponentInstanceId($component_instance_id)
    {
        $this->component_instance_id = $component_instance_id;
    }

    /**
     * Get component instance id
     *
     * @return int
     */
    public function getComponentInstanceId()
    {
        return $this->component_instance_id;
    }

    /**
     * Set content
     *
     * @param string $content
     */
    public function setContent($content)
    {
        $this->content = $content;
    }

    /**
     * Get content
     *
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Set list item id
     *
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Get list item id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set sequence
     *
     * @param int $sequence
     */
    public function setSequence($sequence)
    {
        $this->sequence = $sequence;
    }

    /**
     * Get sequence
     *
     * @return int
     */
    public function getSequence()
    {
        return $this->sequence;
    }
}
