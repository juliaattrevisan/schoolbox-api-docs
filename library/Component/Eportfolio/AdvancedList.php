<?php

/**
 * Component_Eportfolio_AdvancedList
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Component\Eportfolio;

class AdvancedList extends ListItem
{
    /**
     * Title of the list item
     *
     * @var string
     */
    public $title = '';

    /**
     * Storage hash for an arbitrarily uploaded image
     *
     * @var string|null
     */
    public $imagehash = null;

    /* Getters/Setters
     */

    /**
     * Set image hash
     *
     * @param string|null $imagehash
     */
    public function setImagehash($imagehash)
    {
        $this->imagehash = $imagehash;
    }

    /**
     * Get image hash
     *
     * @return string|null
     */
    public function getImagehash()
    {
        return $this->imagehash;
    }

    /**
     * Set title
     *
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
