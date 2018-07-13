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
use Schoolbox\Resource;
use Schoolbox\RoleMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;


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
     * @var FolderMapper
     */
    private $folderMapper;

    /**
     * @var Pastoral\Group\Mapper
     */
    private $pastoralGroupMapper;


    /**
     * Controller constructor.
     * @param CampusMapper          $campusMapper
     * @param RoleMapper            $roleMapper
     * @param HouseMapper           $houseMapper
     * @param FolderMapper          $folderMapper
     * @param Pastoral\Group\Mapper $pastoralGroupMapper
     */
    public function __construct(
        CampusMapper $campusMapper,
        RoleMapper $roleMapper,
        HouseMapper $houseMapper,
        FolderMapper $folderMapper,
        Pastoral\Group\Mapper $pastoralGroupMapper
    ) {
        $this->campusMapper = $campusMapper;
        $this->roleMapper = $roleMapper;
        $this->houseMapper = $houseMapper;
        $this->folderMapper = $folderMapper;
        $this->pastoralGroupMapper = $pastoralGroupMapper;
    }


    /**
     * @param Collection $collection
     * @return \Alaress\Web\Output\Json
     */
    private function generate(Collection $collection): \Alaress\Web\Output\Json
    {
        $items = [];

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


    /**
     * Get (id, name) tuples for every folder
     */
    public function getFolders()
    {
        $folders = $this->folderMapper->findForUser($_SESSION['user']);

        $items = [];

        foreach ($folders as $folder) {
            $items[] = array_combine(['id', 'name'], $folder->findValueLabelPair());
        }

        $this->getResponse()->content->set($this->buildJsonOutput([
            'success' => true,
            'data' => $items
        ]));
    }


    /**
     * Get all year levels
     * @return string
     */
    public function getYearLevels()
    {
        $this->getResponse()->content->set($this->buildJsonOutput([
            'success' => true,
            'data' => [
                [
                    'id' => 1,
                    'name' => 'Junior Kindergarten',
                ],
                [
                    'id' => 2,
                    'name' => 'Senior Kindergarten',
                ],
                [
                    'id' => 3,
                    'name' => 'Prep',
                ],
                [
                    'id' => 4,
                    'name' => 'Year 1',
                ],
                [
                    'id' => 5,
                    'name' => 'Year 2',
                ],
                [
                    'id' => 6,
                    'name' => 'Year 3',
                ],
                [
                    'id' => 7,
                    'name' => 'Year 4',
                ],
                [
                    'id' => 8,
                    'name' => 'Year 5',
                ],
                [
                    'id' => 9,
                    'name' => 'Year 6',
                ],
                [
                    'id' => 10,
                    'name' => 'Year 7',
                ],
                [
                    'id' => 11,
                    'name' => 'Year 8',
                ],
                [
                    'id' => 12,
                    'name' => 'Year 9',
                ],
                [
                    'id' => 13,
                    'name' => 'Year 10',
                ],
                [
                    'id' => 14,
                    'name' => 'Year 11',
                ],
                [
                    'id' => 15,
                    'name' => 'Year 12',
                ]
            ]
        ]));
    }


    /**
     * Get (id, name) tuples for every Pastoral Group
     */
    public function getPastoralGroups()
    {
        $this->getResponse()->content->set($this->generate($this->pastoralGroupMapper->findAll()));
    }
}
