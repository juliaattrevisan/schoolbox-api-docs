<?php

/**
 * Component_Eportfolio_LinkList
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Component\Eportfolio;

class LinkList extends ListItem
{

    /**
     * Human readable version of the link
     * $content is the actual url itself
     *
     * @var string
     */
    public $title = '';

    /* Getters/Setters
     */

    /**
     * Set title
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * Get title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }
}
