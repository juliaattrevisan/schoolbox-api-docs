<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Browser;


use Exception;

interface NodeInterface {


    /**
     * Constructor
     *
     * $nodeId is the primary key of the category whose contents
     * you wish to present
     *
     * @param int    $nodeId
     * @param string $mimeFilter
     * @throws Exception
     */
    public function __construct($nodeId, $mimeFilter = null);


    /**
     * Render it out in a json format in case you need it
     */
    public function ajax();


    /**
     * Render it out so phplib can do it
     *
     * @param bool $noFiles
     * @return array
     */
    public function render($noFiles = false);

} 