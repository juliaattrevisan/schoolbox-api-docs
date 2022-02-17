<?php

/**
 * Output handling class that renders a Twig template without any theme.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Web\Output;

class Html extends Base
{
    /**
     * The template to use.
     *
     * If not set via `setTemplate`, it uses the version from the response.
     *
     * @var string
     */
    protected $template = null;

    /**
     * @var \Twig\Environment
     */
    protected $templateEngine;

    /**
     * Outputs the response body from the response object.
     *
     * This method assumes the output is an array with "template" and "data"
     * keys.
     */
    protected function outputResponseBody()
    {

        echo $this->getAsString();
    }

    /**
     * Returns the contents of the responseBody from the response object
     * as a string
     *
     * @return string
     */
    public function getAsString()
    {
        $template = $this->getTemplate();
        $data     = $this->getData();
        return $this->getTemplateEngine()->render($template, $data);
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

    /**
     * @return string
     */
    protected function getTemplate()
    {
        return isset($this->template)
            ? $this->template
            : $this->retrieveFieldFromResponse('template');
    }

    /**
     * @param  string $template
     * @return $this
     */
    public function setTemplate($template)
    {
        $this->template = $template;
        return $this;
    }
}
