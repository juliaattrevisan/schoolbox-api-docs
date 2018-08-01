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
use Schoolbox\Comms\News\Audience\Segment;
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
     *
     * @api /comms/news/audiencebrowser/search
     */
    public function search()
    {
        $keyword = '%' . $this->getRequest()->query['query'] . '%';
        $searchResult = [];

        $registry = new Segment\SearchRegistry($this->getUser(), $this->folderMapper);
        $translator = new Segment\Translator($this->folderMapper->getDatabase(), $this->getUser(), $this->folderMapper);

        $statement = $this->userMapper->getDatabase()->prepare($registry->getSearchSql());

        $statement->execute([
            'keyword' => $keyword,
        ]);

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $searchResult[] = [
                'id' => Segment\Codec::encode($row['entity_type'], $row['id'], Model::RELATIONSHIP_SELF),
                'name' => $translator->assembleSegment($row['name'], $row['entity_type'], Model::RELATIONSHIP_SELF)
            ];

            /* Only provide the Parents and Teachers modifiers to strats which support them
             * We exclude Parents and Teachers modifiers from Users due to privacy reasons
             */
            if ($registry->hasModifier($row['entity_type'])) {
                $searchResult[] = [
                    'id' => Segment\Codec::encode($row['entity_type'], $row['id'], Model::RELATIONSHIP_PARENTS_OF),
                    'name' => $translator->assembleSegment($row['name'], $row['entity_type'], Model::RELATIONSHIP_PARENTS_OF)
                ];

                $searchResult[] = [
                    'id' => Segment\Codec::encode($row['entity_type'], $row['id'], Model::RELATIONSHIP_TEACHERS_OF),
                    'name' => $translator->assembleSegment($row['name'], $row['entity_type'], Model::RELATIONSHIP_TEACHERS_OF)
                ];
            }
        }

        $this->getResponse()->content->set($this->buildJsonOutput($searchResult));
    }


    /**
     * @param Collection $collection
     * @param string     $entityType
     * @return \Alaress\Web\Output\Json
     */
    private function generate(Collection $collection, string $entityType): \Alaress\Web\Output\Json
    {
        $items = [];

        foreach ($collection as $item) {
            $tuple = $item->findValueLabelPair();

            /* Encode the value because Year Level is a user-configurable string
             */
            $items[] = [
                'id' => Segment\Codec::encode($entityType, $tuple['value'], Model::RELATIONSHIP_SELF),
                'name' => $tuple['label']
            ];
        }

        return $this->buildJsonOutput([
            'success' => true,
            'data' => $items
        ]);
    }


    /**
     * Get (id, name) tuples for every Campus
     *
     * @api /comms/news/audiencebrowser/campuses
     */
    public function getCampuses()
    {
        $this->getResponse()->content->set($this->generate($this->campusMapper->findAll()->sortBy('name', 'asc'), Model::ENTITY_TYPE_CAMPUS));
    }


    /**
     * Get (id, name) tuples for every Role
     *
     * @api /comms/news/audiencebrowser/roles
     */
    public function getRoles()
    {
        $this->getResponse()->content->set($this->generate($this->roleMapper->findAll()->sortBy('name', 'asc'), Model::ENTITY_TYPE_ROLE));
    }


    /**
     * Get (id, name) tuples for every Pastoral Group
     *
     * @api /comms/news/audiencebrowser/pastoralgroups
     */
    public function getPastoralGroups()
    {
        $this->getResponse()->content->set($this->generate($this->pastoralGroupMapper->findAll()->sortBy('name', 'asc'), Model::ENTITY_TYPE_PASTORAL_GROUP));
    }


    /**
     * Get (id, name) tuples for every House
     *
     * @api /comms/news/audiencebrowser/houses
     */
    public function getHouses()
    {
        $this->getResponse()->content->set($this->generate($this->houseMapper->findAll()->sortBy('house', 'asc'), Model::ENTITY_TYPE_SCHOOL_HOUSE));
    }


    /**
     * Get (id, name) tuples for every folder
     *
     * @api /comms/news/audiencebrowser/folders
     */
    public function getFolders()
    {
        $this->getResponse()->content->set($this->generate($this->folderMapper->findForUser($this->getUser())->sortBy('title', 'asc'), Model::ENTITY_TYPE_FOLDER));
    }


    /**
     * Get all year levels
     *
     * @api /comms/news/audiencebrowser/yearlevels
     *
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getYearLevels()
    {
        $years = array_filter($this->userMapper->findDistinct('year'));

        sort($years);

        $items = [];

        foreach ($years as $year) {

            /* Encode the value because Year Level is a user-configurable string
             */
            $items[] = [
                'id' => Segment\Codec::encode(Model::ENTITY_TYPE_YEAR, $year, Model::RELATIONSHIP_SELF),
                'name' => \is_numeric($year) ? "Year {$year}" : $year
            ];
        }

        $this->getResponse()->content->set($this->buildJsonOutput([
            'success' => true,
            'data' => $items
        ]));
    }
}
