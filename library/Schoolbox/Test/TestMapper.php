<?php

/**
 * Test mapper.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Test;

/**
 * @method TestModel createModel($idValue = null)
 */
class TestMapper extends AbstractTestMapper
{

    /**
     * @var MapperTest
     */
    private $test;

    /**
     * @param MapperTest $test
     */
    public function __construct(MapperTest $test = null)
    {
        parent::__construct();
        $this->test = isset($test) ? $test : null;
    }

    /**
     * Switch the cache back on for test purposes, even though this is a test
     * that is likely run over CLI (where cache is usually switched off).
     */
    protected function setup()
    {
        $this->enableCache();
    }

    protected function mapping()
    {
        $this->hasTable('test_table');
        $this->hasModel(TestModel::class);
        $this->hasMap([
            'column'     => 'storage_id',
            'property'   => 'id',
            'type'       => self::TYPE_INTEGER,
            'identifier' => true,
        ]);
        $this->hasMap([
            'column'   => 'filename',
            'property' => 'filename',
            'type'     => self::TYPE_STRING,
        ]);
        $this->hasMap([
            'column'   => 'is_viewed',
            'property' => 'isViewed',
            'type'     => self::TYPE_BOOLEAN,
        ]);
        $this->hasMap([
            'column'   => 'file',
            'property' => 'file',
            'type'     => self::TYPE_LOB,
        ]);
        $this->hasMap([
            'column'   => 'parent',
            'property' => 'parent',
            'type'     => self::TYPE_INTEGER,
        ]);
        $this->hasJoin('parent', [
            'mapper'    => '\\Schoolbox\\Test\\TestMapper',
            'this_key'  => 'category',
            'other_key' => 'id',
        ]);

        $this->registerCallback(self::CALLBACK_POSTSAVE, function (TestModel $model, TestModel $previous = null) {
            $this->test->internalTestNotSame($model, $previous);
            return true;
        });
    }
}
