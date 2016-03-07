<?php
/**
 * Placeholder for customisation of top-menu bar
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\TopMenu;

use Alaress\Web\Controller\Base;
use Schoolbox\User;
use Schoolbox\Resource\Storage;
use Schoolbox\TopMenu\Mapper as TopMenuMapper;

class Controller extends Base
{
    public function getIndex()
    {
        // Render this thing out

        $this->getResponse()->content->set(
            array(
                'template' => '@adminTopMenu/index.twig',
                'data'     => array(
                    'pageCurrent' => basename($_GET["page"]),
                    'items' => TopMenuMapper::getInstance()->findAll()->sortBy("order", "asc"),
                ),
            )
        );
    }

}
