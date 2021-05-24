<?php

/**
 * Output handling class that streams a file, either from the contents of a
 * variable or directly from the filesystem.
 *
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Web\Output;

class Stream extends Base
{

    /**
     * @var \Twig\Environment
     */
    protected $templateEngine;

    /**
     * Outputs the response body from the response object.
     *
     * This method assumes the output is an array with following keys:
     *      "template"  Repeating template to render the streamed data into
     *      "callback"  Generates data to render into repeating template
     *      "param_arr" Data to pass to the callback
     *      "data"      optional. Data for header and footer
     *      "header"    optional. Header template to render data into
     *      "footer"    optional. Footer template to render data into
     */
    protected function outputResponseBody()
    {
        $response  = $this->getResponse()->content->get();
        $callback  = $response['callback'];
        $param_arr = $response['param_arr'];

        // Enable data-generating function to track its progress
        $param_arr['counter'] = 0;

        // Render Heading
        if (isset($response['header'])) {
            echo $this->getTemplateEngine()->render($response['header'], $response['data']);
        }

        // Render content
        while ($data = call_user_func_array($callback, $param_arr)) {
            // Should we increment counter?
            if (!isset($data['increment_counter']) || !$data['increment_counter']) {
                $param_arr['counter']++;
            }

            // Should we render this item?
            if (!isset($data['render_item']) || $data['render_item']) {
                echo $this->getTemplateEngine()->render($response['template'], $data);
            }
        }

        // Render Footer
        if (isset($response['footer'])) {
            echo $this->getTemplateEngine()->render($response['footer'], $response['data']);
        }
    }

    /**
     * @param  \Twig\Environment $templateEngine
     * @return $this
     */
    public function setTemplateEngine($templateEngine)
    {
        $this->templateEngine = $templateEngine;
        return $this;
    }

    /**
     * @return \Twig\Environment
     */
    public function getTemplateEngine()
    {
        return $this->templateEngine;
    }
}
