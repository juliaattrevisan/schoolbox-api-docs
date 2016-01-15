<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Import;

use Alaress\Web\Controller\Base;

/**
 * Controller for admin importer introduction.
 *
 * @package Schoolbox\Import
 */
class IntroController extends Base
{
    use ImportNavigation;

    /**
     * Intro action
     */
    public function intro()
    {
        $this->getResponse()->content->set([
            'template' => '@adminImport/intro.twig',
            'data'     => [
                'allBreadcrumbs' => $this->buildBreadcrumbs($this->getRouter())->getAllNodes(),
                'allNavigation'  => $this->buildNavigation($this->getRouter())->getAllNodes(),
            ],
        ]);
    }
}
