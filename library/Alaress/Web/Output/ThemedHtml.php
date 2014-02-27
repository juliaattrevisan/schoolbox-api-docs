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

class ThemedHtml extends Html {

    /**
     * @var \SitePage
     */
    protected $sitepage;

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
        $page->setTitle($response['title']);
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

}
