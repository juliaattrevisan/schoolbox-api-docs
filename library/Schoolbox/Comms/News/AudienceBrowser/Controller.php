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
use Schoolbox\Comms\News\Audience\Model;
use Schoolbox\Comms\News\Audience\SegmentCodec;
use Schoolbox\Comms\News\Audience\Segments;
use Schoolbox\Pastoral;
use Schoolbox\RoleMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\UserMapper;


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
     * @var UserMapper
     */
    private $userMapper;

    /**
     * Controller constructor.
     * @param CampusMapper          $campusMapper
     * @param RoleMapper            $roleMapper
     * @param HouseMapper           $houseMapper
     * @param FolderMapper          $folderMapper
     * @param Pastoral\Group\Mapper $pastoralGroupMapper
     * @param UserMapper            $userMapper
     */
    public function __construct(
        CampusMapper $campusMapper,
        RoleMapper $roleMapper,
        HouseMapper $houseMapper,
        FolderMapper $folderMapper,
        Pastoral\Group\Mapper $pastoralGroupMapper,
        UserMapper $userMapper
    ) {
        $this->campusMapper = $campusMapper;
        $this->roleMapper = $roleMapper;
        $this->houseMapper = $houseMapper;
        $this->folderMapper = $folderMapper;
        $this->pastoralGroupMapper = $pastoralGroupMapper;
        $this->userMapper = $userMapper;
    }


    /**
     * Look for all the audience pieces that might somehow match $keyword
     */
    public function search()
    {
        $keyword = '%' . $this->getRequest()->query['query'] . '%';
        $searchResult = [];

        /* Any old mapper will do
         */
        $statement = $this->userMapper->getDatabase()->prepare("
            (SELECT id, name, 'campus' AS entity_type
            FROM luCampus
            WHERE `name` LIKE :keyword)
            
            UNION
            
            (SELECT id, house AS name, 'house' AS entity_type
            FROM house
            WHERE house LIKE :keyword)
            
            UNION
            
            (SELECT id, CONCAT(firstname, ' ', lastname) AS name, 'user' AS entity_type
            FROM `user`
            WHERE firstname LIKE :keyword OR lastname LIKE :keyword)
             
            UNION
            
            (SELECT id, 
                CASE
                WHEN CONCAT('', 1*`user`.year) = `user`.year
                THEN CONCAT('Year ', `user`.year)
                ELSE `user`.year
                END
             AS name, 'year' AS entity_type
            FROM `user`
            WHERE year LIKE :keyword)
            
            UNION
            
            (SELECT id, name, 'role' AS entity_type
            FROM groups
            WHERE `name` LIKE :keyword)
            
            UNION 
            
            (SELECT id, name COLLATE utf8mb4_general_ci, 'pastoral_group' AS entity_type 
            FROM `pastoral_group`
            WHERE `name` LIKE :keyword)
        ");

        $statement->execute([
            'keyword' => $keyword,
        ]);


        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $segment = null;

            switch ($row['entity_type']) {
                case 'campus':
                case 'house':
                case 'pastoral_group':
                case 'role':
                case 'user':
                case 'year':
                    $segment = SegmentCodec::encode($row['entity_type'], $row['id'], Model::RELATIONSHIP_SELF);
                    break;

            }

            $searchResult[] = [
                'id' => $segment,
                'name' => "{$row['name']} ({$row['entity_type']})"
            ];
        }

        new \Alaress_Probe($searchResult);

        die();
//
//
//        /* Folders
//         */
//        $folderResults = $this->folderMapper->search($this->getUser(), $keyword);
//
//        foreach ($folderResults->getResult() as $folderResult) {
//            $searchResult[] = [
//                'id' => SegmentCodec::encode('folder', $folderResult->getId(), Model::RELATIONSHIP_SELF),
//                'name' => "{$folderResult->getNameWithCodes()} (Folder)"
//            ];
//        }
//
//        /* Pastoral Groups
//         */
//        $this->getResponse()->content->set($this->buildJsonOutput($searchResult));
    }


    /**
     * @param Collection $collection
     * @return \Alaress\Web\Output\Json
     */
    private function generate(Collection $collection): \Alaress\Web\Output\Json
    {
        $items = [];

        foreach($collection as $item) {
            $data = array_combine(['id', 'name'], $item->findValueLabelPair());

            /* base64_encode the value because Year Level is a user-configurable string
             */
            $data['id'] = base64_encode($data['id']);

            $items[] = $data;
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
     * Get (id, name) tuples for every Pastoral Group
     */
    public function getPastoralGroups()
    {
        $this->getResponse()->content->set($this->generate($this->pastoralGroupMapper->findAll()));
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
        $this->getResponse()->content->set($this->generate($this->folderMapper->findForUser($_SESSION['user'])->sortBy('title', 'asc')));
    }


    /**
     * Get all year levels
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getYearLevels()
    {
        $years = array_filter($this->userMapper->findDistinct('year'));

        sort($years);

        $data = [];

        foreach ($years as $year) {
            $data[] = [
                'id' => base64_encode($year),
                'name' => \is_numeric($year) ? "Year {$year}" : $year
            ];
        }

        $this->getResponse()->content->set($this->buildJsonOutput([
            'success' => true,
            'data' => $data
        ]));
    }
}
