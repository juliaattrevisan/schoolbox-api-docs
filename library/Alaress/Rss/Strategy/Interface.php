<?php

/**
 * Alaress_Rss_Strategy_Interface
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
interface Alaress_Rss_Strategy_Interface
{

    /**
     * Get a list of $limit RSS items
     *
     * Output should consist of an array containing
     * title, link, description, publish date
     *
     * @param  \Xml $source
     * @param  int  $limit
     * @return array
     */
    public function get(\Xml $source, $limit);

    /**
     * Get the title of the stream
     *
     * @param  $input
     * @return string
     */
    public function getTitle($input);

    /**
     * Get the link of the stream
     *
     * @param  $input
     * @return string
     */
    public function getLink($input);
}
