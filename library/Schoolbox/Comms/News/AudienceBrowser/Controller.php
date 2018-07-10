<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News\AudienceBrowser;

use Alaress\Web\Controller\Base;
use HouseMapper;
use Schoolbox\CampusMapper;
use Schoolbox\Pastoral;
use Schoolbox\RoleMapper;


class Controller extends Base
{

    /**
     * @var CampusMapper
     */
    private $campusMapper;

    /**
     * @var RoleMapper
     */
    private $roleMapper;

    /**
     * @var \HouseMapper
     */
    private $houseMapper;

    /**
     * @var Pastoral\Group\Mapper
     */
    private $pastoralGroupMapper;

    /**
     * Controller constructor.
     * @param CampusMapper          $campusMapper
     * @param RoleMapper            $roleMapper
     * @param HouseMapper           $houseMapper
     * @param Pastoral\Group\Mapper $pastoralGroupMapper
     */
    public function __construct(
        CampusMapper $campusMapper,
        RoleMapper $roleMapper,
        HouseMapper $houseMapper,
        Pastoral\Group\Mapper $pastoralGroupMapper
    ) {
        $this->campusMapper = $campusMapper;
        $this->roleMapper = $roleMapper;
        $this->houseMapper = $houseMapper;
        $this->pastoralGroupMapper = $pastoralGroupMapper;
    }

    public function getCampuses()
    {
        echo 'testCampus';
    }

    public function getRoles()
    {
        echo 'testRoles';
    }

    public function getHouses()
    {
        echo 'testHouses';
    }

    public function getClasses()
    {
        echo 'testClasses';
    }

    public function getGroups()
    {
        echo 'testGroups';
    }

    public function getPastoralGroups()
    {
        echo 'testPastoralGroups';
    }
}
