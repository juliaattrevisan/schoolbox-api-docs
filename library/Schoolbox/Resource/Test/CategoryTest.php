<?php
/**
 * Unit tests for Schoolbox\Resource\Folder\Model class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Resource\Test;

use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Group;
use Schoolbox\Test\TestCase;

class CategoryTest extends TestCase
{

    /**
     * The folder that's being tested.
     *
     * @var Folder\Model
     */
    protected $object;

    protected $config_vars = [
        ['file_dir', '/tmp'],
    ];


    public function setUp()
    {
        parent::setUp();
        $this->object = new Folder\Model();
    }


    public function testCodes()
    {
        $this->markTestIncomplete();
    }


    public function testIsGroup()
    {
        $folder = new Folder\Model();
        $folder->setGroup((new Group())->setType(Group::PRIVATE_GROUP));
        $this->assertTrue($folder->isGroup());
    }


    public function testIsNotGroup()
    {
        $folder = new Folder\Model();
        $folder->setGroup(null);
        $this->assertFalse($folder->isGroup());
    }


    public function testIsClass()
    {
        $folder = new Folder\Model();
        $folder->setCodes(new GenericCollection(Folder\Code\Model::class, [new Folder\Code\Model()]));
        $this->assertTrue($folder->isClass());
    }


    public function testIsNotClass()
    {
        $folder = new Folder\Model();
        $folder->setCodes(new GenericCollection(Folder\Code\Model::class));
        $this->assertFalse($folder->isClass());
    }


    public function testIsNotClassDeletedCodes()
    {
        // Not a class because all folder codes are deleted
        $deletedCode = new Folder\Code\Model();
        $deletedCode->setDeletedAt(new \Alaress_Date_Format('2000-01-01', \Alaress_Date_Format::DATABASE_DATE_FORMAT));

        $folder = new Folder\Model();
        $folder->setCodes(new GenericCollection(Folder\Code\Model::class, [$deletedCode]));
        $this->assertFalse($folder->isClass());
    }
}
