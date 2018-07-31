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
use Schoolbox\Comms\News\Audience\Segment\Search\Campus;
use Schoolbox\Comms\News\Audience\Segment\Search\Folder;
use Schoolbox\Comms\News\Audience\Segment\Search\House;
use Schoolbox\Comms\News\Audience\Segment\Search\PastoralGroup;
use Schoolbox\Comms\News\Audience\Segment\Search\Role;
use Schoolbox\Comms\News\Audience\Segment\Search\User;
use Schoolbox\Comms\News\Audience\Segment\Search\Year;
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

        $campusSql = (new Campus())->getSearchSql();
        $roleSql = (new Role())->getSearchSql();
        $folderSql = (new Folder($this->folderMapper->getDatabase()))->getSearchSql();
        $houseSql = (new House())->getSearchSql();
        $userSql = (new User())->getSearchSql();
        $yearSql = (new Year())->getSearchSql();
        $pastoralGroupSql = (new PastoralGroup())->getSearchSql();

        $statement = $this->userMapper->getDatabase()->prepare("
            {$campusSql}
            
            UNION

            {$roleSql}
            
            UNION 
            
            {$houseSql}
            
            UNION

            {$folderSql}

            UNION
                        
            {$userSql}
            
            UNION
            
            {$yearSql}
            
            UNION
            
            {$pastoralGroupSql}
        ");

        $statement->execute([
            'keyword' => $keyword,
        ]);

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $searchResult[] = [
                'id' => SegmentCodec::encode($row['entity_type'], $row['id'], Model::RELATIONSHIP_SELF),
                'name' => "{$row['name']} ({$row['entity_type']})"
            ];
        }

        $this->getResponse()->content->set($this->buildJsonOutput($searchResult));
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
        $this->getResponse()->content->set($this->generate($this->folderMapper->findForUser($this->getUser())->sortBy('title', 'asc')));
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
