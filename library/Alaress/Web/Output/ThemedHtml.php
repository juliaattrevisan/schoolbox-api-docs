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

class ThemedHtml extends Html
{
    /**
     * The tab that is selected for this response.
     *
     * If not set via `setTab`, it uses the version from the response.
     *
     * @var string
     */
    protected $tab = null;

    /**
     * The value of the `<title>` tag of this response.
     *
     * If not set via `setTitle`, it uses the version from the response.
     *
     * @var string
     */
    protected $title = null;

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
    protected function outputResponseBody()
    {
        $page     = $this->getSitepage();
        $template = $this->getTemplate();
        $data     = $this->getData();
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
            $tab = $this->getTab();
            if ($tab) {
                $page->setTab($tab);
            }
            $page->setFile(TEMPLATE_content, 'output.html');
            $page->buildPage();
            $page->setVar('OUTPUT', $this->getTemplateEngine()->render($template, $data));

            $page->setTitle($this->getTitle());
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

    /**
     * @return string
     */
    protected function getTab()
    {
        return isset($this->tab)
            ? $this->tab
            : $this->retrieveFieldFromResponse('tab');
    }

    /**
     * @param  string $tab
     * @return $this
     */
    public function setTab($tab)
    {
        $this->tab = $tab;
        return $this;
    }

    /**
     * @return string
     */
    protected function getTitle()
    {
        return isset($this->title)
            ? $this->title
            : $this->retrieveFieldFromResponse('title');
    }

    /**
     * @param  string $title
     * @return $this
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }
}
