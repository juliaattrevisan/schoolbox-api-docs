<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Sync\Effect\Fragment;

use \Schoolbox\Collection;

/**
 * Class Fragment
 *
 * An Effect Fragment definition. The $filename is its twig template
 * and $data is the key/value set which the twig template utilises
 *
 * @package Schoolbox\Sync\Effect\Fragment
 */
class Fragment
{

    /**
     * Template filename
     *
     * @var string
     */
    private $filename = '';


    /**
     * A Collection of ValueLabelInterface instances
     *
     * @var Collection
     */
    private $data = null;


    /**
     * If this is of type "Collection", it might use Selectize.
     * That uses an ajax url to retrieve data from a controller.
     *
     * This is the ajax url type, such as "groups" or "campus".
     *
     * This is an optional var, and is only used on Collections
     *
     * @var null
     */
    private $ajaxUrl = null;


    /**
     * Constructor
     *
     * @param string $filename
     * @param Collection $data
     */
    public function __construct($filename, $data = null) {
        $this->filename = $filename;

        if ($data instanceof Collection) {
            $this->data = $data;
        }
        else {
            $this->data = new Collection();
        }
    }

    /* Getters/Setters
     */

    /**
     * Get data
     *
     * @return Collection
     */
    public function getData()
    {
        return $this->data;
    }


    /**
     * Get filename
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }


    /**
     * Get ajax url
     *
     * @return null
     */
    public function getAjaxUrl()
    {
        return $this->ajaxUrl;
    }


    /**
     * Set ajax url
     *
     * @param null $ajaxUrl
     */
    public function setAjaxUrl($ajaxUrl)
    {
        $this->ajaxUrl = $ajaxUrl;
    }
}
