<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
                'pageCurrent' => 'import',
                'allNavigation'  => $this->buildNavigation($this->getRouter())->getAllNodes(),
            ],
        ]);
    }
}
