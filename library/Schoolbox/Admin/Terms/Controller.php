<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\Terms;

use Alaress\Web\Controller\Base;

/**
 * Controller for managing school terms through the admin section.
 *
 * @package Schoolbox\Admin\Terms
 */
class Controller extends Base
{
    /**
     * Renders index
     */
    public function getIndex()
    {
        $this->getResponse()->content->set(
            array(
                'template' => '@adminTerms/index.twig',
                'data'     => [
                    'pageCurrent' => basename($this->getRequest()->query->get('page')),
                ],
                'title' => 'Admin - Terms'
            )
        );
    }
}
