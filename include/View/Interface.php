<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
interface View_Interface {

    /**
     * Implementing classes must indicate their default headers to be sent
     * The assumption is that default can be overridden
     * @abstract
     */
    public function getDefaultHeaders();

    /**
     * Implementing classes must be able to add headers to their default list
     * @abstract
     * @param $header
     */
    public function addHeader($header);

    /**
     * Implementing classes are responsible for sending their headers ahead of data output
     * @abstract
     */
    public function sendHeaders();


    /**
     * Implementing classes may choose to setup page-specific variables, which is done here
     * @abstract
     */
    public function generatePage();

    /**
     * Implementing classes must output their data according to their nature
     * @abstract
     * @param Core_ResponseDataObject $rdo
     * @return array
     */
    public function render(Response_HTTP $rdo);

    /**
     * Get the Name of the view.
     * @abstract
     */
    public function getName();

}
