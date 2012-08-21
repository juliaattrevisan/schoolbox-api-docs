<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class View_File implements View_Interface {

    private $headers = array();

    public function getDefaultHeaders() {
        return array('Content-Type: text/plain; charset=UTF-8');
    }

    public function addHeader($header) {
        $this->headers[] = $header;
    }

    public function sendHeaders() {
        if(empty($this->headers))
            $this->headers = $this->getDefaultHeaders();

        foreach($this->headers as $header) {
            header($header);
        }
    }

    public function render(Response_HTTP $response) {

        // need to know the mime-type of the object in $rdo and output headers accordingly
        //$this->addHeader('Content-Disposition: attachment; filename="' . $response->getCore('methodName') . '.txt"');
        $this->headers = $response->getHeaders();
        $this->sendHeaders();
        $dataArray = $response->getData();
        echo $dataArray['response']['filedata'];

        // and just in case we have any output in prior-stacked classes that might try to output anything
        die();

    }

    /**
     * Implementing classes may choose to setup page-specific variables, which is done here
     */
    public function generatePage() {
        // TODO: Implement generatePage() method.
    }

    public function getName() {
        return get_called_class();
    }
}
