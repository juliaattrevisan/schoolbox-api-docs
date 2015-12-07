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

        // Is there a post-output callback?
        // Buffer response body and output it later
        if ($this->hasPostOutputCallbacks()) {
            ob_start();
        }
        if (!isset($location)) {
            $this->outputResponseBody();
        }
        if ($this->hasPostOutputCallbacks()) {
            $this->runPostOutputCallbacks(ob_get_clean());
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
     * Gets post-output callbacks out of the response content.
     *
     * @todo put them somewhere else, as they don't belong in content.
     *
     * @return array
     */
    protected function getPostOutputCallbacks()
    {
        $content = $this->getResponse()->content->get();
        if (is_array($content) && isset($content['postOutput'])) {
            return $content['postOutput'];
        }
        return [];
    }

    /**
     * Does this have any post-output callbacks?
     *
     * @return bool
     */
    protected function hasPostOutputCallbacks()
    {
        return count($this->getPostOutputCallbacks()) > 0;
    }

    /**
     * Runs post-output callbacks, but not before sending output to the client.
     *
     * Processing of callbacks will run in the background.
     *
     * @param string $output
     */
    protected function runPostOutputCallbacks($output = '')
    {
        $callbacks = $this->getPostOutputCallbacks();
        if (!empty($callbacks)) {
            // Output now
            // No content encoding because gzip interferes with content length
            echo $output;
            header('Content-Encoding: none');
            header("Content-Length: ".strlen($output));
            header('Connection: close');
            ignore_user_abort();
            flush();
            session_write_close();

            // Callbacks
            foreach ($callbacks as $callback) {
                if (is_callable($callback)) {
                    $callback();
                }
            }
        }
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
