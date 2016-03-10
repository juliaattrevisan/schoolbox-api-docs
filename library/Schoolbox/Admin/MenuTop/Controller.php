<?php
/**
 * Placeholder for customisation of top-menu bar
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\MenuTop;

use Alaress\Web\Controller\Base;
use Schoolbox\User;
use Schoolbox\Resource\Storage;
use Schoolbox\MenuTop\Mapper as MenuTopMapper;

class Controller extends Base
{
    public function getIndex()
    {
        // Render this thing out

        $this->getResponse()->content->set(
            array(
                'template' => '@adminMenuTop/index.twig',
                'data'     => array(
                    'pageCurrent' => basename($this->getRequest()->query->get('page')),
                    'items' => MenuTopMapper::getInstance()->findAll()->sortBy("order", "asc"),
                ),
            )
        );
    }

}
