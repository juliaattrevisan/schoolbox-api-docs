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
use Schoolbox\Collection;
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


    /**
     * @param Collection $collection
     * @return \Alaress\Web\Output\Json
     */
    private function generate(Collection $collection): \Alaress\Web\Output\Json
    {
        foreach($collection as $item) {
            $items[] = array_combine(['id', 'name'], $item->findValueLabelPair());
        }

        return $this->buildJsonOutput([
            'success' => true,
            'data' => $items
        ]);
    }


    /**
     * Get (id, name) tuples for every Campus
     */
    public function getCampuses()
    {
        $this->getResponse()->content->set($this->generate($this->campusMapper->findAll()));
    }


    /**
     * Get (id, name) tuples for every Role
     */
    public function getRoles()
    {
        $this->getResponse()->content->set($this->generate($this->roleMapper->findAll()));
    }

    /**
     * Get (id, name) tuples for every House
     */
    public function getHouses()
    {
        $this->getResponse()->content->set($this->generate($this->houseMapper->findAll()));
    }

    public function getClasses()
    {
        echo 'testClasses';
    }

    public function getGroups()
    {

        echo 'testGroups';
    }

    /**
     * Get (id, name) tuples for every Pastoral Group
     */
    public function getPastoralGroups()
    {
        $this->getResponse()->content->set($this->generate($this->pastoralGroupMapper->findAll()));
    }
}
