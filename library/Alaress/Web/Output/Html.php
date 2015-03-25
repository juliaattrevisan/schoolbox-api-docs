<?php
/**
 * Output handling class that renders a Twig template without any theme.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Web\Output;

use Aura\Web\Response;

class Html extends Base {

    /**
     * @var \Twig_Environment
     */
    protected $templateEngine;

    /**
     * Outputs the response body from the response object.
     *
     * This method assumes the output is an array with "template" and "data"
     * keys.
     */
    protected function outputResponseBody() {
        $response = $this->getResponse()->content->get();
        echo $this->getTemplateEngine()->render($response['template'], $response['data']);
    }

    /**
     * Returns the contents of the responseBody from the response object
     * as a string
     *
     * @return string
     */
    public function getAsString() {
        $response = $this->getResponse()->content->get();
        return $this->getTemplateEngine()->render($response['template'], $response['data']);
    }

    /**
     * @param  \Twig_Environment $templateEngine
     * @return $this
     */
    public function setTemplateEngine($templateEngine) {
        $this->templateEngine = $templateEngine;
        return $this;
    }

    /**
     * @return \Twig_Environment
     */
    public function getTemplateEngine() {
        return $this->templateEngine;
    }

}
