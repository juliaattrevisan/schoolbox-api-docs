<?php
/**
 * Controller for Meriden's pastoral page.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Meriden\Pastoral;

use Alaress\Web\Controller\Base;

class Controller extends Base
{
    /**
     * Index for Meriden's pastoral page
     */
    public function index()
    {
        $this->getResponse()->content->set(
            array(
                'template' => '@customMeridenPastoral/index.twig',
                'data'     => array(),
            )
        );
    }
}
