<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Test;

use PHPUnit;
use Schoolbox\Comms\News\Audience\InvalidSegmentException;
use Schoolbox\Comms\News\Audience\Model;
use Schoolbox\Comms\News\Audience\ValidSegment;
use Schoolbox\Role;

class ValidSegmentTest extends PHPUnit\Framework\TestCase
{

    /** @var ValidSegment */
    private $segmentValidator;


    public function setUp()
    {
        parent::setUp();
        $this->segmentValidator = new ValidSegment();
    }


    /**
     * @dataProvider provider_validSimpleRootSegment
     * @param Model $segment
     * @throws PHPUnit\Framework\AssertionFailedError
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Comms\News\Audience\InvalidSegmentException
     */
    public function test_validSimpleRootSegment(Model $segment): void
    {
        $this->segmentValidator->assertValid($segment);
        $this->assertEmpty($this->segmentValidator->getInvalidReasons());
    }


    /**
     * @dataProvider provider_invalidSimpleRootSegment
     * @param Model  $segment
     * @param string $message
     * @throws InvalidSegmentException
     * @throws PHPUnit\Framework\AssertionFailedError
     * @throws PHPUnit\Framework\Exception
     * @throws \InvalidArgumentException
     */
    public function test_invalidSimpleRootSegment(Model $segment, string $message): void
    {
        $this->expectException(InvalidSegmentException::class);
        $this->segmentValidator->assertValid($segment);
        $this->assertNotEmpty($this->segmentValidator->getInvalidReasons(), $message);
    }


    /**
     * @dataProvider provider_invalidSegmentTrees
     * @param Model $segment
     * @throws InvalidSegmentException
     * @throws PHPUnit\Framework\Exception
     * @throws \InvalidArgumentException
     */
    public function test_invalidSegmentTree(Model $segment): void
    {
        $this->expectException(InvalidSegmentException::class);
        $this->segmentValidator->assertValid($segment);
    }


    /**
     * @return array
     * @throws \UnexpectedValueException
     */
    public function provider_validSimpleRootSegment(): array
    {
        return [
            [
                (new Model())
                    ->setId(1)
                    ->setCommsNewsArticle(new \stdClass())// TODO replace with real model
                    ->setEntityType(Model::ENTITY_TYPE_USER)
                    ->setEntityValue('230')
                    ->setRelationship(Model::RELATIONSHIP_SELF),
            ],
            [
                (new Model())
                    ->setId(2)
                    ->setCommsNewsArticle(new \stdClass())// TODO replace with real model
                    ->setEntityType(Model::ENTITY_TYPE_SUPER)
                    ->setEntityValue(Model::ENTITY_VALUE_SUPER)
                    ->setRelationship(Model::RELATIONSHIP_SELF),
            ],
            [
                (new Model())
                    ->setId(3)
                    ->setCommsNewsArticle(new \stdClass())// TODO replace with real model
                    ->setEntityType(Model::ENTITY_TYPE_CAMPUS)
                    ->setEntityValue('2')
                    ->setRelationship(Model::RELATIONSHIP_SELF),
            ],
            [
                (new Model())
                    ->setId(4)
                    ->setCommsNewsArticle(new \stdClass())// TODO replace with real model
                    ->setEntityType(Model::ENTITY_TYPE_ROLE_TYPE)
                    ->setEntityValue(Role::STUDENT)
                    ->setRelationship(Model::RELATIONSHIP_SELF),
            ],
        ];
    }


    /**
     * @return array
     * @throws \UnexpectedValueException
     */
    public function provider_invalidSimpleRootSegment(): array
    {
        return [
            [
                (new Model())
                    ->setId(1)
                    ->setEntityType(Model::ENTITY_TYPE_USER)
                    ->setEntityValue('230')
                    ->setRelationship(Model::RELATIONSHIP_SELF),
                'No article or parent',
            ],
            [
                (new Model())
                    ->setId(2)
                    ->setCommsNewsArticle(new \stdClass())// TODO replace with real model
                    ->setParent(new Model())
                    ->setEntityType(Model::ENTITY_TYPE_SUPER)
                    ->setEntityValue(Model::ENTITY_VALUE_SUPER)
                    ->setRelationship(Model::RELATIONSHIP_SELF),
                'Article and parent',
            ],
            [
                (new Model())
                    ->setId(3)
                    ->setCommsNewsArticle(new \stdClass())// TODO replace with real model
                    ->setEntityValue('2')
                    ->setRelationship(Model::RELATIONSHIP_SELF),
                'No entity type',
            ],
            [
                (new Model())
                    ->setId(4)
                    ->setCommsNewsArticle(new \stdClass())// TODO replace with real model
                    ->setEntityType(Model::ENTITY_TYPE_ROLE_TYPE)
                    ->setEntityValue(Role::STUDENT),
                'No relationship modifier',
            ],
            [
                (new Model())
                    ->setId(5)
                    ->setCommsNewsArticle(new \stdClass())// TODO replace with real model
                    ->setEntityType(Model::ENTITY_TYPE_COMPOSITE)
                    ->setRelationship(Model::RELATIONSHIP_SELF),
                'No children of composite',
            ],
        ];
    }


    /**
     * @return array
     * @throws \UnexpectedValueException
     */
    public function provider_invalidSegmentTrees(): array
    {
        // Straight chain
        $grandparent = (new Model())
            ->setId(1)
            ->setCommsNewsArticle(new \stdClass())// TODO replace with real model
            ->setEntityType(Model::ENTITY_TYPE_COMPOSITE)
            ->setRelationship(Model::RELATIONSHIP_SELF);

        $parent = (new Model())
            ->setId(2)
            ->setParent($grandparent)
            ->setEntityType(Model::ENTITY_TYPE_COMPOSITE)
            ->setRelationship(Model::RELATIONSHIP_SELF);

        $grandchild = (new Model())
            ->setId(2)
            ->setParent($parent)
            ->setEntityType(Model::ENTITY_TYPE_USER)
            ->setRelationship(Model::RELATIONSHIP_SELF);

        $grandparent->getChildren()[] = $parent;
        $parent->getChildren()[]      = $grandchild;


        return [[$grandparent]];
    }

}
