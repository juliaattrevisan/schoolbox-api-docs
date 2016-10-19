<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\User;

use Alaress\Web\Controller\Base;
use HouseMapper;
use HouseResidentialMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\User\Title\Mapper as UserTitleMapper;
use Schoolbox\RoleMapper;
use Schoolbox\CampusMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\User\Log\Mapper as UserLogMapper;
use Schoolbox\CMS\Log\Mapper as CMSLogMapper;

/**
 * Controller for managing school users through the admin section.
 *
 * @package Schoolbox\Admin\Users
 */
class Controller extends Base
{
    /**
     * @var FolderMapper
     */
    protected $folderMapper;

    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * @var UserAccessMapper
     */
    protected $userAccessMapper;

    /**
     * @var UserTitleMapper
     */
    protected $userTitleMapper;

    /**
     * @var CampusMapper
     */
    protected $campusMapper;

    /**
     * @var RoleMapper
     */
    protected $roleMapper;

    /**
     * @var \HouseResidentialMapper
     */
    protected $houseResidentialMapper;

    /**
     * @var \HouseMapper
     */
    protected $houseMapper;

    /**
     * @var UserLogMapper;
     */
    protected $userLogMapper;

    /**
     * @var CMSLogMapper;
     */
    protected $cmsLogMapper;

    /**
     * Controller constructor.
     * @param CMSLogMapper $cmsLogMapper
     * @param UserLogMapper $userLogMapper
     * @param FolderMapper $folderMapper
     * @param UserMapper $userMapper
     * @param UserAccessMapper $userAccessMapper
     * @param UserTitleMapper $userTitleMapper
     * @param CampusMapper $campusMapper
     * @param RoleMapper $roleMapper
     * @param HouseResidentialMapper $houseResidentialMapper
     * @param HouseMapper $houseMapper
     */
    public function __construct(
        CMSLogMapper $cmsLogMapper,
        UserLogMapper $userLogMapper,
        FolderMapper $folderMapper,
        UserMapper $userMapper,
        UserAccessMapper $userAccessMapper,
        UserTitleMapper $userTitleMapper,
        CampusMapper $campusMapper,
        RoleMapper $roleMapper,
        HouseResidentialMapper $houseResidentialMapper,
        HouseMapper $houseMapper)
    {
        $this->cmsLogMapper = $cmsLogMapper;
        $this->userLogMapper = $userLogMapper;
        $this->folderMapper = $folderMapper;
        $this->userMapper = $userMapper;
        $this->userAccessMapper = $userAccessMapper;
        $this->userTitleMapper = $userTitleMapper;
        $this->campusMapper = $campusMapper;
        $this->roleMapper = $roleMapper;
        $this->houseResidentialMapper = $houseResidentialMapper;
        $this->houseMapper = $houseMapper;
    }

    /**
     * Modify user screen
     * @param User $user
     */
    public function getModify($user)
    {
        if (\Cfg::get('external_enable') && \Cfg::get('external_type') == 'synergetic' && $user->getExternalId() > 0) {
            $synuser = new \External_Synergetic_User();
            $fileSemester = $synuser->getCurrentSemester($user->getExternalId());
            $fileYear = $synuser->getCurrentYear($user->getExternalId());
        }
        else {
            $fileSemester = '';
            $fileYear = '';
        }

        $this->getResponse()->content->set([
            'template' => '@adminUser/modify.twig',
            'data'     => [
                'pageCurrent'       => basename($this->getRequest()->query->get('page')),
                'campuses'          => $this->campusMapper->findAll(),
                'roles'             => $this->roleMapper->findAll(),
                'externalDatabase'  => ucfirst(\Cfg::get('external_type')),
                'residentialHouses' => $this->houseResidentialMapper->findAll(),
                'houses'            => $this->houseMapper->findAll(),
                'titles'            => $this->userTitleMapper->findAll(),
                'fileYear'          => $fileYear,
                'fileSemester'      => $fileSemester,
                'folders'           => $this->folderMapper->findAll()->sortBy('name', 'asc'),
                'user'              => $user,
                'userAccess'        => $this->userAccessMapper->findAllGroupsForUser($user),
                'userPermissions'   => $this->userAccessMapper->findAllForUser($user),
                'userLog'           => $this->userLogMapper->findByColumn('username', $user->getUsername())->sortBy('date', 'desc')->limit(0, 10),
                'cmsLog'            => $this->cmsLogMapper->findByUser($user, 10)->sortBy('time', 'desc')
            ],
            'title' => 'Admin - Users'
        ]);
    }
}
