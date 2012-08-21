<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class View_JSON implements View_Interface {

    private $headers = array();

    public function getDefaultHeaders() {
        return array('Content-Type: application/json; charset=UTF-8');
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

    public function render(Response_HTTP $rdo) {

        $this->sendHeaders();

        if($rdo->hasError()) {
            $send = $rdo->getError();
        }

        else {
            $send = $rdo->getResponse();
        }

        echo json_encode($send);
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
