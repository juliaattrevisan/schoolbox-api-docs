<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Assessment;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Learning\Quiz;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Mapper;

class InteractionMapperProxyTest extends TestCase
{
    /**
     * @param Mapper\AbstractMapperProxy $mapperProxy
     * @param string                     $modelClass
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerMapperProxy')]
    public function testCreateModel(Mapper\AbstractMapperProxy $mapperProxy, $modelClass)
    {
        $model = $mapperProxy->createModel();
        $this->assertInstanceOf($modelClass, $model);
        $this->assertNull($model->getId());
    }


    /**
     * @return array
     */
    public static function providerMapperProxy()
    {
        return [
            [
                self::getDueWorkResponseMapperProxy(),
                Assessment\DueWork\Response\Model::class,
            ],
            [
                self::getDueWorkFeedbackMapperProxy(),
                Assessment\DueWork\Feedback\Model::class,
            ],
            [
                self::getQuizResponseMapperProxy(),
                Assessment\Quiz\Attempt\Model::class,
            ],
            [
                self::getQuizFeedbackMapperProxy(),
                Assessment\Quiz\Feedback\Model::class,
            ],
            [
                self::getTaskResponseMapperProxy(),
                Assessment\Task\Response\Model::class,
            ],
            [
                self::getTaskFeedbackMapperProxy(),
                Assessment\Task\Feedback\Model::class,
            ],
        ];
    }


    /**
     * @return Assessment\DueWork\Response\MapperProxy
     */
    private static function getDueWorkResponseMapperProxy()
    {
        return new Assessment\DueWork\Response\MapperProxy(SubmissionMapper::getInstance());
    }

    /**
     * @return Assessment\DueWork\Feedback\MapperProxy
     */
    private static function getDueWorkFeedbackMapperProxy()
    {
        return new Assessment\DueWork\Feedback\MapperProxy(ReturnedWorkMapper::getInstance());
    }

    /**
     * @return Assessment\Quiz\Attempt\MapperProxy
     */
    private static function getQuizResponseMapperProxy()
    {
        return new Assessment\Quiz\Attempt\MapperProxy(Quiz\Attempt\Mapper::getInstance());
    }

    /**
     * @return Assessment\Quiz\Feedback\MapperProxy
     */
    private static function getQuizFeedbackMapperProxy()
    {
        return new Assessment\Quiz\Feedback\MapperProxy(Quiz\Attempt\Mapper::getInstance());
    }

    /**
     * @return Assessment\Task\Response\MapperProxy
     */
    private static function getTaskResponseMapperProxy()
    {
        return new Assessment\Task\Response\MapperProxy(Task\CompletionMapper::getInstance());
    }

    /**
     * @return Assessment\Task\Feedback\MapperProxy
     */
    private static function getTaskFeedbackMapperProxy()
    {
        return new Assessment\Task\Feedback\MapperProxy(Task\CompletionMapper::getInstance());
    }
}
