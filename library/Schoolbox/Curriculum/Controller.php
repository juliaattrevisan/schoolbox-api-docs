<?php
/**
 * Controller for the base of the curriculum module.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Curriculum;

use DateTime;
use Alaress\Paginator\IUrlPaginator;
use Alaress\Web\Controller\Base;
use Schoolbox\Collection;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\RoleMapper;
use Schoolbox\StorageMapper;

class Controller extends Base
{
    public function browse()
    {
        $this->getResponse()->content->set(
            array(
                'title'    => 'Curriculum',
                'template' => '@curriculum/browse.twig',
                'data'     => array(),
            )
        );
    }
}
