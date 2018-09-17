<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Test;

use PHPUnit;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Audience;
use Schoolbox\Role;
use Alaress\Specification;
use Schoolbox\Comms\News\Audience\Validation;
use Schoolbox\Comms\News\Audience\Segment\Strategy;
use Schoolbox\User;

class ValidSegmentTest extends PHPUnit\Framework\TestCase
{

    /** @var Specification\ISpecification */
    private $validSegmentSpec;


    /**
     * @inheritdoc
     */
    public function setUp()
    {
        parent::setUp();

        // Ensure the relationship is valid for the segment's entity type
        $validRelationships = [
            Audience\Model::ENTITY_TYPE_COMPOSITE => [Audience\Model::RELATIONSHIP_SELF, Audience\Model::RELATIONSHIP_PARENTS_OF, Audience\Model::RELATIONSHIP_TEACHERS_OF],

            Strategy\User::ENTITY_TYPE           => [Audience\Model::RELATIONSHIP_SELF],
            Strategy\Super::ENTITY_TYPE          => [Audience\Model::RELATIONSHIP_SELF],
            Strategy\Campus::ENTITY_TYPE         => [Audience\Model::RELATIONSHIP_SELF, Audience\Model::RELATIONSHIP_PARENTS_OF, Audience\Model::RELATIONSHIP_TEACHERS_OF],
            Strategy\Role::ENTITY_TYPE           => [Audience\Model::RELATIONSHIP_SELF, Audience\Model::RELATIONSHIP_PARENTS_OF, Audience\Model::RELATIONSHIP_TEACHERS_OF],
            Strategy\RoleType::ENTITY_TYPE       => [Audience\Model::RELATIONSHIP_SELF, Audience\Model::RELATIONSHIP_PARENTS_OF, Audience\Model::RELATIONSHIP_TEACHERS_OF],
            Strategy\SchoolHouse::ENTITY_TYPE    => [Audience\Model::RELATIONSHIP_SELF, Audience\Model::RELATIONSHIP_PARENTS_OF, Audience\Model::RELATIONSHIP_TEACHERS_OF],
            Strategy\ResHouse::ENTITY_TYPE       => [Audience\Model::RELATIONSHIP_SELF, Audience\Model::RELATIONSHIP_PARENTS_OF, Audience\Model::RELATIONSHIP_TEACHERS_OF],
            Strategy\Year::ENTITY_TYPE           => [Audience\Model::RELATIONSHIP_SELF, Audience\Model::RELATIONSHIP_PARENTS_OF, Audience\Model::RELATIONSHIP_TEACHERS_OF],
            Strategy\Service::ENTITY_TYPE        => [Audience\Model::RELATIONSHIP_SELF, Audience\Model::RELATIONSHIP_PARENTS_OF, Audience\Model::RELATIONSHIP_TEACHERS_OF],
            Strategy\PastoralGroup::ENTITY_TYPE  => [Audience\Model::RELATIONSHIP_SELF, Audience\Model::RELATIONSHIP_PARENTS_OF, Audience\Model::RELATIONSHIP_TEACHERS_OF],
            Strategy\Folder::ENTITY_TYPE         => [Audience\Model::RELATIONSHIP_SELF, Audience\Model::RELATIONSHIP_PARENTS_OF, Audience\Model::RELATIONSHIP_TEACHERS_OF],
        ];


        // Either not targeted to NULL, or targeted to NULL but that's valid for the segment's entity
        $validNullEntities = [
            Audience\Model::ENTITY_TYPE_COMPOSITE => true,
            Strategy\User::ENTITY_TYPE            => false,
            Strategy\Super::ENTITY_TYPE           => false,
            Strategy\Campus::ENTITY_TYPE          => true,
            Strategy\Role::ENTITY_TYPE            => false,
            Strategy\RoleType::ENTITY_TYPE        => false,
            Strategy\SchoolHouse::ENTITY_TYPE     => true,
            Strategy\ResHouse::ENTITY_TYPE        => true,
            Strategy\Year::ENTITY_TYPE            => true,
            Strategy\Service::ENTITY_TYPE         => false,
            Strategy\PastoralGroup::ENTITY_TYPE   => true,
            Strategy\Folder::ENTITY_TYPE          => true,
        ];
        $factory                = new Validation\Factory($validRelationships, $validNullEntities);
        $this->validSegmentSpec = $factory->makeValidSegmentSpecification(new User());
    }


    /**
     * @dataProvider provider_validSimpleRootSegment
     * @param Audience\Model $segment
     * @throws PHPUnit\Framework\AssertionFailedError
     */
    public function test_validSimpleRootSegment(Audience\Model $segment): void
    {
        $this->assertTrue($this->validSegmentSpec->isSatisfiedBy($segment));
    }


    /**
     * @dataProvider provider_invalidSimpleRootSegment
     * @param Audience\Model $segment
     * @param string         $expected
     * @param string         $message
     * @throws PHPUnit\Framework\AssertionFailedError
     * @throws PHPUnit\Framework\Exception
     */
    public function test_invalidSimpleRootSegment(Audience\Model $segment, string $expected, string $message): void
    {
        $this->assertFalse($this->validSegmentSpec->isSatisfiedBy($segment), $message);

        $unsatisfied = $this->getFailedSpecification($this->validSegmentSpec, $segment);
        /** @noinspection UnnecessaryAssertionInspection */
        $this->assertInstanceOf($expected, $unsatisfied);
    }


    /**
     * @dataProvider provider_invalidSegmentTrees
     * @param Audience\Model $segment
     * @throws PHPUnit\Framework\Exception
     */
    public function test_invalidSegmentTree(Audience\Model $segment): void
    {
        $this->assertFalse($this->validSegmentSpec->isSatisfiedBy($segment));

        $unsatisfied = $this->getFailedSpecification($this->validSegmentSpec, $segment);
        /** @noinspection UnnecessaryAssertionInspection */
        $this->assertInstanceOf(Validation\ValidTreeHeight::class, $unsatisfied);
    }

    // Data Providers


    /**
     * @return array
     * @throws \UnexpectedValueException
     */
    public function provider_validSimpleRootSegment(): array
    {
        return [
            [
                (new Audience\Model())
                    ->setId(1)
                    ->setCommsNewsArticle(new News\Model())
                    ->setEntityType(Strategy\User::ENTITY_TYPE)
                    ->setEntityValue('230')
                    ->setRelationship(Audience\Model::RELATIONSHIP_SELF),
            ],
            [
                (new Audience\Model())
                    ->setId(2)
                    ->setCommsNewsArticle(new News\Model())
                    ->setEntityType(Strategy\Super::ENTITY_TYPE)
                    ->setEntityValue(Audience\Model::ENTITY_VALUE_SUPER)
                    ->setRelationship(Audience\Model::RELATIONSHIP_SELF),
            ],
            [
                (new Audience\Model())
                    ->setId(3)
                    ->setCommsNewsArticle(new News\Model())
                    ->setEntityType(Strategy\Campus::ENTITY_TYPE)
                    ->setEntityValue('2')
                    ->setRelationship(Audience\Model::RELATIONSHIP_SELF),
            ],
            [
                (new Audience\Model())
                    ->setId(4)
                    ->setCommsNewsArticle(new News\Model())
                    ->setEntityType(Strategy\RoleType::ENTITY_TYPE)
                    ->setEntityValue(Role::STUDENT)
                    ->setRelationship(Audience\Model::RELATIONSHIP_SELF),
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
                (new Audience\Model())
                    ->setId(1)
                    ->setEntityType(Strategy\User::ENTITY_TYPE)
                    ->setEntityValue('230')
                    ->setRelationship(Audience\Model::RELATIONSHIP_SELF),
                Specification\ExclusiveOr::class,
                'No article or parent',
            ],
            [
                (new Audience\Model())
                    ->setId(2)
                    ->setCommsNewsArticle(new News\Model())
                    ->setParent(new Audience\Model())
                    ->setEntityType(Strategy\Super::ENTITY_TYPE)
                    ->setEntityValue(Audience\Model::ENTITY_VALUE_SUPER)
                    ->setRelationship(Audience\Model::RELATIONSHIP_SELF),
                Specification\ExclusiveOr::class,
                'Article and parent',
            ],
            [
                (new Audience\Model())
                    ->setId(3)
                    ->setCommsNewsArticle(new News\Model())
                    ->setEntityType(Strategy\RoleType::ENTITY_TYPE)
                    ->setEntityValue(Role::STUDENT),
                Specification\Conjunction::class,
                'No relationship',
            ],
            [
                (new Audience\Model())
                    ->setId(4)
                    ->setCommsNewsArticle(new News\Model())
                    ->setEntityType(Audience\Model::ENTITY_TYPE_COMPOSITE)
                    ->setRelationship(Audience\Model::RELATIONSHIP_SELF),
                Specification\ExclusiveOr::class,
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
        $grandparent = (new Audience\Model())
            ->setId(1)
            ->setCommsNewsArticle(new News\Model())
            ->setEntityType(Audience\Model::ENTITY_TYPE_COMPOSITE)
            ->setRelationship(Audience\Model::RELATIONSHIP_SELF);

        $parent = (new Audience\Model())
            ->setId(2)
            ->setParent($grandparent)
            ->setEntityType(Audience\Model::ENTITY_TYPE_COMPOSITE)
            ->setRelationship(Audience\Model::RELATIONSHIP_SELF);

        $grandchild = (new Audience\Model())
            ->setId(2)
            ->setParent($parent)
            ->setEntityType(Strategy\User::ENTITY_TYPE)
            ->setRelationship(Audience\Model::RELATIONSHIP_SELF);

        $grandparent->getChildren()[] = $parent;
        $parent->getChildren()[]      = $grandchild;


        return [[$grandparent]];
    }

    // Helpers


    /**
     * Returns the reason/s why an audience segment fails a spec
     *
     * @param Specification\ISpecification $spec
     * @param Audience\Model               $segment
     * @return Specification\ISpecification
     */
    private function getFailedSpecification(Specification\ISpecification $spec, Audience\Model $segment): Specification\ISpecification
    {
        $unsatisfied = $spec->remainderUnsatisfiedBy($segment);
        while ($unsatisfied instanceof Specification\Conjunction && 1 === \count($unsatisfied->getComponents())) {
            $components  = $unsatisfied->getComponents();
            $unsatisfied = \array_shift($components);
        }
        return $unsatisfied;
    }
}
