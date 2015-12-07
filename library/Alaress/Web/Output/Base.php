<?php
/**
 * Output handling class - accepts a web response object, and figures out how
 * to output it.
 *
 * Depending on the output content, it may output a JSON array, a JSON/HTML
 * object, HTML wrapped in a skin or HTML with no template.
 *
 * It expects that
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Web\Output;

use Aura\Web\Response;

class Base {
    /**
     * @var Response
     */
    protected $response;

    /**
     * Outputs a response and its headers in the most basic fashion.
     *
     * Copied from https://github.com/auraphp/Aura.Web/blob/develop-2/README-RESPONSE.md
     */
    public function output() {
        $this->preprocessOutput();
        $this->outputResponseCode();
        $this->outputHeaders();
        $this->outputCookies();
        // Don't send response body if there is a redirect.
        $location = $this->getResponse()->headers->get('location');
        if (!isset($location)) {
            $this->outputResponseBody();
        } else {
            // Output post callbacks
            $content = $this->getResponse()->content->get();
            if (is_array($content) && isset($content['postRedirect'])) {
                // Output now
                header('Content-Encoding: none');
                header("Content-Length: 0");
                header('Connection: close');
                ignore_user_abort();
                flush();
                session_write_close();

                // Callbacks
                foreach ($content['postRedirect'] as $callback) {
                    if (is_callable($callback)) {
                        $callback();
                    }
                }
            }
        }
    }

    /**
     * Called before any output is sent.
     *
     * This method can be overridden to mangle the response object, headers, etc.
     */
    public function preprocessOutput() {}

    /**
     * Outputs the response code header from the response.
     */
    protected function outputResponseCode() {
        header($this->getResponse()->status->get(), true, $this->getResponse()->status->getCode());
    }

    /**
     * Outputs other headers from the response (besides the response code, which
     * is output by outputResponseCode())
     */
    protected function outputHeaders() {
        foreach ($this->getResponse()->headers->get() as $label => $value) {
            header("{$label}: {$value}");
        }
    }

    /**
     * Outputs cookies from the response object.
     */
    protected function outputCookies() {
        foreach ($this->getResponse()->cookies->get() as $name => $cookie) {
            setcookie(
                $name,
                $cookie['value'],
                $cookie['expire'],
                $cookie['path'],
                $cookie['domain'],
                $cookie['secure'],
                $cookie['httponly']
            );
        }
    }

    /**
     * Outputs the response body from the response object.
     *
     * This method assumes the output is simply a string.
     */
    protected function outputResponseBody() {
        echo $this->getResponse()->content->get();
    }

    /**
     * @param  \Aura\Web\Response $response
     * @return $this
     */
    public function setResponse($response) {
        $this->response = $response;
        return $this;
    }

    /**
     * @return \Aura\Web\Response
     */
    public function getResponse() {
        return $this->response;
    }

}
