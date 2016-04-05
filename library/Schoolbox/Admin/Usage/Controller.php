<?php
/**
 * Course builder admin page
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\Usage;

use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Usage\All\AjaxFlot as AllAjaxFlot;
use Schoolbox\Usage\Specific\AjaxFlot as SpecificAjaxFlot;
use Schoolbox\Usage\All\UsageMapper;
use Schoolbox\User;
use Schoolbox\Resource\Storage;
use Schoolbox\UserMapper;

class Controller extends Base
{
    /** @var UserMapper $userMapper */
    protected $userMapper;

    /**
     * @return UserMapper
     */
    public function getUserMapper() {
        return $this->userMapper;
    }

    /**
     * @param UserMapper $userMapper
     * @return $this
     */
    public function setUserMapper($userMapper) {
        $this->userMapper = $userMapper;
        return $this;
    }

    /**
     * Controller constructor.
     */
    public function __construct() {
        $this->setUserMapper(UserMapper::getInstance());
    }

    /**
     * @param null|Integer $id
     * @throws \Exception
     */
    public function getIndex($id = null)
    {
        $user = null;
        $mapper = new UsageMapper();

        if ($id != null && $id != 'all') {
            try {
                $user = $this->getUserMapper()->findById($id);
            } catch (\Exception $e) {
                ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_RESOURCE_NOT_FOUND);
            }
        }

        // Render this thing out

        $this->getResponse()->content->set(
            array(
                'template' => '@adminHome/index.twig',
                'data'     => array(
                    'pageCurrent' => basename($this->getRequest()->query->get('page')),
                    'numUsers' => $mapper->numUsers(),
                    'user' => $user
                )
            )
        );
    }

    public function getAjaxAll($type, $orderBy = null, $order = null, $index = null, $numPerPage = null)
    {
        $usage = new AllAjaxFlot();

        switch($type) {
            case 'total':
                $data = $usage->findTotalUsage();
                break;
            case 'group':
                $data = $usage->findGroupUsage();
                break;
            case 'type':
                $data = $usage->findTypeUsage();
                break;
            case 'user':
                $data = json_encode($usage->findUserUsage($orderBy, $order, $index, $numPerPage));
                break;
            default:
                $data = [];
                break;
        }

        $this->getResponse()->content->set(array(
            'format' => 'file',
            'data'   => $data
        ));
    }

    public function getAjaxSpecific($id)
    {
        $usage = new SpecificAjaxFlot($id);

        switch($this->getRequest()->query->get['type']) {
            case 'total':
                $data = $usage->findTotalUsage();
                break;
            case 'group':
                $data = $usage->findGroupUsage();
                break;
            case 'type':
                $data = $usage->findTypeUsage();
                break;
            case 'user':
                $data = json_encode($usage->findUserUsage(
                    $this->getRequest()->query->get['orderBy'],
                    $this->getRequest()->query->get['order'],
                    $this->getRequest()->query->get['index'],
                    $this->getRequest()->query->get['numPerPage']
                ));
                break;
            default:
                $data = [];
                break;
        }

        $this->getResponse()->content->set(array(
            'format' => 'json',
            'data'   => $data
        ));
    }

}
