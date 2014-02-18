<?php
/**
 * Output handling class that renders a Twig template and data into a theme.
 *
 * Currently, theming is handled by the good old Sitepage object, but this could
 * change.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Web\Output;

use Aura\Web\Response;

class ThemedHtml extends Base {

    /**
     * @var \SitePage
     */
    protected $sitepage;

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
        $page = $this->getSitepage();
        $page->setFile(TEMPLATE_content, 'output.html');
        $response = $this->getResponse()->content->get();
        $page->setVar('OUTPUT', $this->getTemplateEngine()->render($response['template'], $response['data']));
        $page->buildPage();
        $page->outputPage(true);
    }

    /**
     * @param  \SitePage $sitepage
     * @return $this
     */
    public function setSitepage($sitepage) {
        $this->sitepage = $sitepage;
        return $this;
    }

    /**
     * @return \SitePage
     */
    public function getSitepage() {
        return $this->sitepage;
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