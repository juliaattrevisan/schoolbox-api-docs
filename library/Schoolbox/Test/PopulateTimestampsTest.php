<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Test;

use Schoolbox\Blog\Homepage\Post as Blog;

class PopulateTimestampsTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Blog\Mapper
     */
    private $blogMapper;


    /**
     * @inheritdoc
     */
    public function setUp(): void
    {
        $this->blogMapper = $this->getBlogMapper();
    }


    /**
     * 'created' should be null prior to saving a new model
     */
    public function testCreateTimestampBeforeSave()
    {
        $model = $this->blogMapper->createModel();
        $this->assertNull($model->getCreatedAt());
    }


    /**
     * 'created' should be 'now' after saving a new model
     */
    public function testCreateTimestampAfterSave()
    {
        $now   = \Alaress_Date_Format::now();
        $model = $this->blogMapper->createModel();
        $this->blogMapper->save($model);

        $this->assertEqualsWithDelta($model->getCreatedAt()->getUnixTimeStamp(), $now->getUnixTimeStamp(), 0.001);
    }


    /**
     * 'created' should not change if we re-save a model
     */
    public function testCreateTimestampAfterReSave()
    {
        $model = $this->blogMapper->createModel();
        $this->blogMapper->save($model);
        $firstCreate = clone $model->getCreatedAt();
        sleep(1);
        $this->blogMapper->save($model);

        $this->assertEquals($model->getCreatedAt()->getUnixTimeStamp(), $firstCreate->getUnixTimeStamp());
    }


    /**
     * 'created' should be respected if explicitly set
     * (although this is not recommended)
     */
    public function testCreateTimestampExplicitSet()
    {
        $yesterday = \Alaress_Date_Format::now()->addDays(-1);
        $model     = $this->blogMapper->createModel();
        $model->setCreatedAt(clone $yesterday);
        $this->blogMapper->save($model);

        $this->assertEquals($model->getCreatedAt()->getUnixTimeStamp(), $yesterday->getUnixTimeStamp());
    }


    /**
     * 'updated' should be null prior to saving a new model
     */
    public function testUpdateTimestampBeforeSave()
    {
        $model = $this->blogMapper->createModel();
        $this->assertNull($model->getUpdatedAt());
    }


    /**
     * 'updated' should be 'now' after saving a new model
     */
    public function testUpdateTimestampAfterSave()
    {
        $now   = \Alaress_Date_Format::now();
        $model = $this->blogMapper->createModel();
        $this->blogMapper->save($model);

        $this->assertEqualsWithDelta($model->getUpdatedAt()->getUnixTimeStamp(), $now->getUnixTimeStamp(), 0.001);
    }


    /**
     * 'updated' should change if we re-save a model
     */
    public function testUpdateTimestampAfterReSave()
    {
        $model = $this->blogMapper->createModel();
        $this->blogMapper->save($model);
        $firstUpdated = clone $model->getUpdatedAt();
        sleep(1);
        $this->blogMapper->save($model);

        $this->assertNotEquals($model->getUpdatedAt()->getUnixTimeStamp(), $firstUpdated->getUnixTimeStamp());
    }


    /**
     * 'updated' should be ignored if explicitly set
     */
    public function testUpdateTimestampExplicitSet()
    {
        $yesterday = \Alaress_Date_Format::now()->addDays(-1);
        $model     = $this->blogMapper->createModel();
        $model->setUpdatedAt(clone $yesterday);
        $this->blogMapper->save($model);

        $this->assertNotEquals($model->getUpdatedAt()->getUnixTimeStamp(), $yesterday->getUnixTimeStamp());
    }


    /**
     * @return Blog\Mapper
     */
    private function getBlogMapper()
    {
        $db         = new MockConnector($this);
        $blogMapper = Blog\Mapper::getInstance();
        $blogMapper->setDatabase($db);
        return $blogMapper;
    }
}
