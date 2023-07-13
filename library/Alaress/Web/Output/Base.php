<?php

/**
 * Output handling class - accepts a web response object, and figures out how
 * to output it.
 *
 * Depending on the output content, it may output a JSON array, a JSON/HTML
 * object, HTML wrapped in a skin or HTML with no template.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Web\Output;

use Aura\Web\Response;

class Base implements OutputInterface
{
    /** @var Response $response */
    protected $response;

    /**
     * The data to set in the template.
     *
     * If not set via `setData`, it uses the version from the response.
     *
     * @var string
     */
    protected $data = null;

    /**
     * Temporary home for post-output callbacks.
     *
     * @var array<callable>
     */
    private array $postOutputCallbacks = [];

    /**
     * Outputs a response and its headers in the most basic fashion.
     *
     * Copied from https://github.com/auraphp/Aura.Web/blob/develop-2/README-RESPONSE.md
     *
     * @param Response $response
     */
    public function output(Response $response)
    {
        $this->setResponse($response);
        $this->preprocessOutput();
        $this->outputResponseCode();
        $this->outputHeaders();
        $this->outputCookies();

        // Is there a post-output callback?
        // Buffer response body and output it later
        if ($this->hasPostOutputCallbacks()) {
            ob_start();
        }

        $this->outputResponseBody();

        if ($this->hasPostOutputCallbacks()) {
            $this->runPostOutputCallbacks(ob_get_clean());
        }
    }

    /**
     * Called before any output is sent.
     *
     * This method can be overridden to mangle the response object, headers, etc.
     */
    public function preprocessOutput()
    {
        $this->response->headers->add('X-Puppet-Cert-Name', getenv('puppet_cert_name'));
    }

    /**
     * Outputs the response code header from the response.
     */
    protected function outputResponseCode()
    {
        header(
            $this->getResponse()->status->get(),
            true,
            $this->getResponse()->status->getCode()
        );
    }

    /**
     * Outputs other headers from the response (besides the response code, which
     * is output by outputResponseCode())
     */
    protected function outputHeaders()
    {
        /** @var array $headers */
        $headers = $this->getResponse()->headers->get();

        foreach ($headers as $label => $value) {
            header("{$label}: {$value}");
        }
    }

    /**
     * Outputs cookies from the response object.
     */
    protected function outputCookies()
    {
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
     * This method assumes the output is simply a string, or was set in `$this->setData`.
     */
    protected function outputResponseBody()
    {
        $data = $this->getData();

        if (isset($data)) {
            echo $data;
        } else {
            echo $this->getResponse()->content->get();
        }
    }

    /**
     * @param array<callable> $postOutputCallbacks
     * @return $this
     */
    public function setPostOutputCallbacks(array $postOutputCallbacks): self
    {
        $this->postOutputCallbacks = $postOutputCallbacks;
        return $this;
    }

    /**
     * Does this have any post-output callbacks?
     *
     * @return bool
     */
    protected function hasPostOutputCallbacks()
    {
        return count($this->postOutputCallbacks) > 0;
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
        if ($this->hasPostOutputCallbacks()) {
            // Output now
            // No content encoding because gzip interferes with content length
            header('Content-Encoding: none');
            header("Content-Length: " . strlen($output));
            echo $output;

            // Finish the request
            fastcgi_finish_request();

            // Allow the process however long it needs to finish:
            // 30 minutes should be more than enough.
            set_time_limit(60 * 30);

            // Callbacks
            foreach ($this->postOutputCallbacks as $callback) {
                if (is_callable($callback)) {
                    $callback();
                }
            }

            // Close session once done
            // (actions in post-output may require session data)
            session_write_close();
        }
    }

    /**
     * Helper function for getting a field value out of this output's response object.
     *
     * Assumes that `$this->response->content` is an array or array-like object.
     *
     * @param string $field
     * @param mixed $default
     * @return mixed Value of $field in $response, or $default if not set.
     */
    protected function retrieveFieldFromResponse($field, $default = null)
    {
        if ($this->response instanceof Response) {
            $content = $this->getResponse()->content->get();

            // $content could be this same object (as it's set via $response->set)
            if (is_array($content) || $content instanceof \ArrayAccess) {
                return isset($content[$field]) ? $content[$field] : $default;
            }
        }

        return $default;
    }

    /**
     * @param Response $response
     * @return $this
     */
    public function setResponse($response)
    {
        $this->response = $response;
        return $this;
    }

    /**
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return string
     */
    protected function getData()
    {
        return isset($this->data) ? $this->data : $this->retrieveFieldFromResponse('data', []);
    }

    /**
     * @param  string|array $data
     * @return $this
     */
    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }
}
