<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Database\Test\Resource\Folder;

use Alaress;
use Alaress\IDatabase;
use OverflowException;
use Schoolbox;
use Schoolbox\Collection;
use Schoolbox\Resource\Folder\Mapper;
use PHPUnit\Framework\TestCase;

/**
 * Class MapperTest
 * @package Schoolbox\Database\Test\Resource\Folder
 */
class MapperTest extends TestCase
{
    use Alaress\Database\Test\TestConnectorTrait;

    /**
     * @var Alaress\IDatabase
     */
    private $database;

    /** @var array */
    private static $tree = [
        1 => [
            6 => [
                21 => 'TWENTY-ONE'
            ],
            7 => [
                22 => 'TWENTY-TWO'
            ],
            8 => [
                23 => 'TWENTY-THREE'
            ],
        ],
        2 => [
            9 => [
                24 => 'TWENTY-FOUR'
            ],
            10 => [
                25 => 'TWENTY-FIVE'
            ],
            11 => [
                26 => 'TWENTY-SIX'
            ],
        ],
        3 => [
            12 => [
                27 => 'TWENTY-SEVEN'
            ],
            13 => [
                28 => 'TWENTY-EIGHT'
            ],
            14 => [
                29 => 'TWENTY-NINE'
            ],
        ],
        4 => [
            15 => [
                30 => 'THIRTY'
            ],
            16 => [
                31 => 'THIRTY-ONE'
            ],
            17 => [
                32 => 'THIRTY-TWO'
            ],
        ],
        5 => [
            18 => [
                33 => 'THIRTY-THREE'
            ],
            19 => [
                34 => 'THIRTY-FOUR'
            ],
            20 => [
                35 => 'THIRTY-FIVE'
            ],
        ],
    ];


    /**
     * Load a known dataset into the DB
     */
    public function setUp(): void
    {
        $this->connectSchoolbox();
        $this->database = \Database::getInstance();
        $this->database->beginTransaction();
        $this->database->query("UPDATE `folder` SET deleted_at = '1970-01-01 00:00:00'");
        $this->database->query('DELETE FROM `folder_code`');

        self::iterate($this->database, 0, self::$tree);
    }

    /**
     * Roll back any changes we made
     */
    public function tearDown(): void
    {
        $this->database->rollback();
    }

    /**
     * @param IDatabase $db
     * @param int       $parent
     * @param array     $folders
     */
    private static function iterate(IDatabase $db, $parent, array $folders)
    {
        foreach ($folders as $id => $sub) {
            $params = [
                'id' => $id,
                'nm' => "Folder #$id",
                'pa' => $parent
            ];

            try {
                $db->query('INSERT INTO `folder` (`id`, `name`, `parent`) VALUES (:id, :nm, :pa)', $params);
            } catch (\PDOException $e) {
                $db->query('UPDATE `folder` SET `name` = :nm, `parent` = :pa, deleted_at = NULL WHERE id = :id', $params);
            }

            if (is_array($sub)) {
                self::iterate($db, $id, $sub);
            } else {
                $db->query('INSERT INTO `folder_code` VALUES (:id, :code, NULL)', [
                    'id'   => $id,
                    'code' => $sub
                ]);
            }
        }
    }


    /**
     * @param Collection $folders
     * @return int[]
     */
    private function getSortedIds(Collection $folders)
    {
        $folderIds = $folders->getColumnValues('id');
        sort($folderIds);

        return $folderIds;
    }


    /**
     * @return array
     */
    public function dataFindByParent()
    {
        return [
            'parent ID = 3'  => [ 3, [ 12, 13, 14 ] ],
            'parent ID = 5'  => [ 5, [ 18, 19, 20 ] ],
            'parent ID = 16' => [ 16, [ 31 ] ],
        ];
    }


    /**
     * Folder\Mapper::findByParentId() is a shim through to this method
     *
     * @dataProvider dataFindByParent
     * @param int   $parentId
     * @param int[] $childrenIds
     */
    public function testFindByParent($parentId, $childrenIds)
    {
        $folderIds = $this->getSortedIds(Mapper::getInstance()->findByParent($parentId));

        $this->assertEquals($childrenIds, $folderIds);
    }


    /**
     *
     */
    public function testFindAllChildrenNoRecursion()
    {
        $topLevel = Mapper::getInstance()->findAllChildren(0, false);

        $folderIds = $this->getSortedIds($topLevel);

        $this->assertEquals([ 1, 2, 3, 4, 5 ], $folderIds);
    }

    /**
     * This method returns all descendants in a FLAT collection. Note that this is
     * different to findByParent which, if recursed, returns a tree structure.
     */
    public function testFindAllChildrenWithRecursion()
    {
        $everything = Mapper::getInstance()->findAllChildren(0, true);

        $allIds = $this->getSortedIds($everything);

        $this->assertEquals(range(1, 35), $allIds);
    }


    /**
     *
     */
    public function testSearchByName()
    {
        $folders = Mapper::getInstance()->searchByName('der #2');

        $expected = [2,20,21,22,23,24,25,26,27,28,29];

        $this->assertEquals($expected, $folders->getColumnValues('id'));
    }


    /**
     *
     */
    public function testFindByCode()
    {
        $code = 'THIRTY';

        $folder = Mapper::getInstance()->findByCode($code);

        $this->assertEquals(30, $folder->getId());
        $this->assertEquals($code, $folder->getCodeString());
        $this->assertEquals(15, $folder->getParent()->getId());
    }


    /**
     *
     */
    public function testFindAllBySubjectList()
    {
        $codes = [
            22 => [ 'code' => 'TWENTY-TWO',   'campusCode' => '' ],
            23 => [ 'code' => 'TWENTY-THREE', 'campusCode' => '' ],
            24 => [ 'code' => 'TWENTY-FOUR',  'campusCode' => '' ],
        ];

        $folderIds = $this->getSortedIds(Mapper::getInstance()->findAllBySubjectList($codes));

        $this->assertEquals(array_keys($codes), $folderIds);
    }


    /**
     *
     */
    public function testFindById()
    {
        $folder = Mapper::getInstance()->findById(21);

        $this->assertEquals(21, $folder->getId());
        $this->assertEquals('TWENTY-ONE', $folder->getCodeString());
        $this->assertEquals(6, $folder->getParent()->getId());
        $this->assertNull($folder->getParent()->getName());
    }


    /**
     * @return array[]
     */
    public function dataFindParents()
    {
        return [
            'all, cheaply'       => [ 29, null, true,  [ 3, 14 ] ],
            'partial, cheaply'   => [ 29, 3,    true,  [ 14 ] ],
            'all, expensive'     => [ 29, null, false, [ 3, 14 ] ],
            'partial, expensive' => [ 29, 3,    false, [ 14 ] ]
        ];
    }


    /**
     * @dataProvider dataFindParents
     *
     * @param int   $folderId
     * @param int   $stopFolderId
     * @param bool  $cheaply
     * @param array $expected
     * @throws OverflowException
     */
    public function testFindParents($folderId, $stopFolderId, $cheaply, array $expected)
    {
        $folder     = Mapper::getInstance()->findById($folderId);
        $stopFolder = $stopFolderId ? Mapper::getInstance()->findById($stopFolderId) : null;

        $lineage = Mapper::getInstance()->findParents($folder, $stopFolder, $cheaply);

        // no sorting
        $this->assertEquals($expected, $lineage->getColumnValues('id'));
    }
}
