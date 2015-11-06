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
        $page     = $this->getSitepage();
        $response = $this->getResponse()->content->get();
        $data     = $response['data'] ?: array();
        $template = $response['template'];
        if (empty($template)) {
            // No template? I can't generate a standard response,
            // so return an error response.
            $code = $this->getResponse()->status->getCode();
            if ($code == 404) {
                $page->pageNotFound();
            } else {
                $page->error(null, 'Template not found', $code ?: 500);
            }
        } else {
            $page->setFile(TEMPLATE_content, 'output.html');
            $page->buildPage();
            $page->setVar('OUTPUT', $this->getTemplateEngine()->render($template, $data));
            $page->setTitle($response['title']);
            $page->outputPage(true);
        }
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
