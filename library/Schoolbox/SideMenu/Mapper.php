<?php
/**
 * Mapper for side menu items in Schoolbox.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\SideMenu;

use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\User;

class Mapper extends BaseMapper
{
    public function mapping()
    {
        $this->hasTable('sideMenu');
        $this->hasModel('\\Schoolbox\\SideMenu\\Model');
        $this->hasMap(
            array(
                'column'     => 'id',
                'property'   => 'id',
                'type'       => self::TYPE_INTEGER,
                'identifier' => true
            )
        );
        $this->hasMap(
            array(
                'column'   => 'parent',
                'property' => 'parent',
                'type'     => self::TYPE_MODEL,
                'join'     => 'parent',
                'join_def' => array(
                    'other_key' => 'id',
                    'mapper'    => '\\Schoolbox\\SideMenu\\Mapper',
                ),
            )
        );
        $this->hasMap(
            array(
                'column'   => 'txt',
                'property' => 'text',
                'type'     => self::TYPE_STRING,
            )
        );
        $this->hasMap(
            array(
                'column'   => 'url',
                'property' => 'url',
                'type'     => self::TYPE_STRING
            )
        );
        $this->hasMap(
            array(
                'column'   => 'target',
                'property' => 'target',
                'type'     => self::TYPE_STRING
            )
        );
        $this->hasMap(
            array(
                'column'   => 'sequence',
                'property' => 'order',
                'type'     => self::TYPE_INTEGER
            )
        );
    }

    /**
     * Puts the entire tree in a root node.
     *
     * @param Collection|Model[] $tree A tree of side menu items, built with
     *                                 $this->getCollectionAsTree()
     * @param string             $text
     *
     * @return Collection
     * @throws BaseMapper\Exception
     */
    public function placeInRootNode(Collection $tree, $text = 'Top Level')
    {
        $base = $this->createModel();
        $base
            ->setId(0)
            ->setText($text)
            ->setChildren($tree);

        $newCollection = new Collection();
        $newCollection->add($base);
        return $newCollection;
    }

    /**
     * Finds all side menus for a particular user.
     *
     * @param User $user
     *
     * @return Collection|Model[] Will be empty if $user isn't a User object
     * @throws BaseMapper\Exception if there's a multi-join somewhere in the
     *                              mapping? I don't think there ever is here.
     */
    public function findByUser($user)
    {
        if (!($user instanceof User)) {
            return new Collection();
        }
        if ($user->isSuperuser()) {
            return $this->findAll(array('order' => array('sequence' => 'asc')));
        } else {
            // Get all for the user's campus and groups
            // No campus? Return nothing
            $campusList = array();
            foreach ($user->getCampusCollection() as $campus) {
                $campusList[] = $campus->getId();
            }
            if (empty($campusList)) {
                // Load the user's campuses?
                return new Collection();
            }

            $query = "
                SELECT DISTINCT(sideMenu.id),
                    parent,
                    txt,
                    url,
                    target
                FROM sideMenu
                INNER JOIN sideMenuCampus ON sideMenu.id = sideMenuCampus.id
                INNER JOIN sideMenuGroup ON sideMenu.id = sideMenuGroup.id
                WHERE sideMenuCampus.campus IN " . $this->getArrayPlaceholders($campusList, 'campus') . "
                AND sideMenuGroup.gid = :groupId
                ORDER BY sequence ASC
            ";
            $params = $this->getArrayParameters($campusList, 'campus');
            $params['groupId'] = $user->getRole()->getId();

            $statement = $this->getDatabase()->prepare($query);
            $statement->execute($params);
            return $this->hydrateList($statement);
        }
    }
}
