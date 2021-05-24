<?php

/**
 * Unit tests for Schoolbox\Resource\Folder\Model class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Test;

use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Group;
use Schoolbox\Test\TestCase;

class FolderTest extends TestCase
{

    /**
     * The folder that's being tested.
     *
     * @var Folder\Model
     */
    protected $object;


    public function setUp(): void
    {
        parent::setUp();
        $this->object = new Folder\Model();
    }


    /**
     * Test whether we can positively identify a folder as being a group
     */
    public function testIsGroup()
    {
        $folder = new Folder\Model();
        $folder->setGroup((new Group())->setType(Group::PRIVATE_GROUP));
        $this->assertTrue($folder->isGroup());
    }


    /**
     * Test whether we can positively identify a folder as not being a group
     */
    public function testIsNotGroup()
    {
        $folder = new Folder\Model();
        $folder->setGroup(null);
        $this->assertFalse($folder->isGroup());
    }


    /**
     * Test whether we can positively identify a folder as being a class
     */
    public function testIsClass()
    {
        $folder = new Folder\Model();
        $folder->setCodes(new GenericCollection(Folder\Code\Model::class, [new Folder\Code\Model()]));
        $this->assertTrue($folder->isClass());
    }


    /**
     * Test whether we can positively identify a folder as not being a class
     */
    public function testIsNotClass()
    {
        $folder = new Folder\Model();
        $folder->setCodes(new GenericCollection(Folder\Code\Model::class));
        $this->assertFalse($folder->isClass());
    }


    /**
     * Test whether we can positively identify a folder as not being a class,
     * because the class codes it has have been soft-deleted
     */
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
