<?php
/**
 *
 * HTTP Response encapsulation class.
 *
 * This class is designed to be a container for all the information the Mediator requires in order to be able to
 * convert Model-layer data into client-presentable view.
 *
 * For now, it will primarily store arrays as presented by the Handler libraries, but later will store proper objects
 * when the data layer starts to return them instead.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Response_HTTP implements Response_Interface {

    /* HTTP RESPONSE CODES, AND THEIR EXPECTED BEHAVIOURS */

    // go forwards: may contain notifications / warnings
    const HTTP_OK           = 'HTTP/1.1 200 OK';
    // go back: maybe not enough data submitted to the form?
    const HTTP_BAD_REQUEST  = 'HTTP/1.1 400 Bad Request';
    // reroute to login page
    const HTTP_UNAUTHORIZED = 'HTTP/1.1 401 Unauthorized';
    // reroute to 403 page
    const HTTP_FORBIDDEN    = 'HTTP/1.1 403 Forbidden';
    // reroute to 404 page
    const HTTP_NOT_FOUND    = 'HTTP/1.1 404 Not Found';
    // go back: something went horribly wrong
    const HTTP_SERVER_ERROR = 'HTTP/1.1 500 Internal Server Error';

    private $status;
    private $headers;
    private $cookies;
    private $data;

    /**
     * Construction with no arguments instantiates with a HTTP_OK status by default
     * @param string $statusCode
     */
    public function __construct($statusCode = Response_HTTP::HTTP_OK) {
        $this->status = $statusCode;
        $this->headers = array();
        $this->data = array();
        $this->cookies = array();
        $this->template = '';
    }

    /* Aux Functions */

    public function hasError() {
        return (
            $this->getStatusCode() != Response_HTTP::HTTP_OK ||
            $this->datumSet('error')
        );
    }


    /* Header Functions */

    public function setStatusCode($statusCode) {
        $this->status = $statusCode;
    }

    public function getStatusCode() {
        return $this->status;
    }

    public function addHeader($field, $contents) {
        $this->headers[$field] = "$field: $contents";
    }

    public function getHeaders() {
        return $this->headers;
    }

    public function getHeader($field) {
        if(isset($this->headers[$field]))
            return $this->headers[$field];
        return null;
    }

    public function addInternalRedirectHeader($destination) {
        $this->addHeader('Location', Mediator::getProtocol() . '://' . $_SERVER['HTTP_HOST'] . '/m/' . $destination);
    }

    /**
     * Send headers. This function must only be called immediately prior to data being output,
     * unless the headers contain a redirect.
     */
    public function sendHeaders() {
        // send status header - always first
        header($this->getStatusCode());

        // send all additional headers
        foreach($this->getHeaders() as $header) {
            header($header);
        }
    }


    /* Cookie Functions */

    public function setCookie($key, $value) {
        $this->cookies[$key] = $value;
    }

    public function getCookie($key) {
        if(isset($this->cookies[$key]))
            return $this->cookies[$key];
        return null;
    }

    public function getCookies() {
        return $this->cookies;
    }

    public function removeCookie($key) {
        unset($this->cookies[$key]);
    }


    /**
     * If this function is called, it must be before any data is output
     */
    public function sendCookies() {
        foreach($this->getCookies() as $k => $v) {
            $_COOKIE[$k] = $v;
        }
    }

    /* Data Functions */

    /**
     * Private function to manipulate the data in a free way.
     * Specific wrapper functions call it for data setting
     * @param $base
     * @param $key
     * @param $value
     */
    private function set($base, $key, $value) {
        $this->data[$base][$key] = $value;
    }

    public function setCore($key, $value) {
        $this->set('core', $key, $value);
    }

    public function setSite($key, $value) {
        $this->set('site', $key, $value);
    }

    public function setPage($key, $value) {
        $this->set('page', $key, $value);
    }

    public function setError($key, $value) {
        $this->set('error', $key, $value);
    }

    public function setResponse($key, $value) {
        $this->set('response', $key, $value);
    }


    private function get($base, $key = '') {
        if(isset($this->data[$base])) {
            if($key == '') {
                return $this->data[$base];
            }
            if(isset($this->data[$base][$key])) {
                return $this->data[$base][$key];
            }
        }
        return null;
    }

    public function getCore($key = '') {
        return $this->get('core', $key);
    }

    public function getSite($key = '') {
        return $this->get('site', $key);
    }

    public function getPage($key = '') {
        return $this->get('page', $key);
    }

    public function getError($key = '') {
        return $this->get('error', $key);
    }

    public function getResponse($key = '') {
        return $this->get('response', $key);
    }

    public function getData() {
        return $this->data;
    }

    private function datumSet($base, $key = '') {
        if($key != '') {
            return isset($this->data[$base][$key]);
        }
        return isset($this->data[$base]);
    }

    /* just in case it's needed */
    private function clear($base) {
        $this->data[$base] = array();
    }

    /* Template Functions */

    public function overrideTemplate($templateFile) {
        $this->set('page', 'template', $templateFile);
    }

    public function getTemplate() {
        return $this->get('page', 'template');
    }

    public function isTemplateOverridden() {
        return $this->datumSet('page', 'template');
    }
}
