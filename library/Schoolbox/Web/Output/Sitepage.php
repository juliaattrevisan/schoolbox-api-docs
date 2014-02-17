<?php
/**
 * Output handling class - shoves Twig-rendered response body into a good old
 * Sitepage object.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Web\Output;

use Aura\Web\Response;

class Sitepage extends Base {

    /**
     * @var \SitePage
     */
    protected $sitepage;

    /**
     * Outputs the response body from the response object.
     *
     * This method assumes the output is simply a string.
     */
    protected function outputResponseBody() {
        $page = $this->getSitepage();
        $page->setFile(TEMPLATE_content, 'output.html');
        $page->setVar('OUTPUT', $this->getResponse()->content->get());
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