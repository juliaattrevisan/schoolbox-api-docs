<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Test;

use DomainException;
use PHPUnit\Framework;
use PHPUnit\Framework\TestCase;
use Schoolbox\Comms\News\Audience;
use Schoolbox\Comms\News\Audience\Segment\Strategy;
use Schoolbox\Mapper\Exception\InsufficientHydrationException;

/**
 * Tests that audience segments can detect equality
 *
 * @codeCoverageIgnore
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Comms\News\Audience\Model::class)]
class SegmentEqualityTest extends TestCase
{

    // ============================ Tests ============================

    /**
     * @throws InsufficientHydrationException
     * @throws Framework\Exception
     */
    public function testInsufficientHydration(): void
    {
        $this->expectException(InsufficientHydrationException::class);

        (new Audience\Model())->equals(new Audience\Model());
    }


    /**
     * @param Audience\Model $left
     * @param Audience\Model $right
     * @param bool           $expected
     * @throws InsufficientHydrationException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerEqualSimple')]
    public function testEqualSimple(Audience\Model $left, Audience\Model $right, bool $expected): void
    {
        $this->assertEquals($expected, $left->equals($right));
    }


    /**
     *
     * @param Audience\Model $simple
     * @param Audience\Model $composite
     * @throws InsufficientHydrationException
     * @throws Framework\AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerEqualSimpleVsComposite')]
    public function testEqualSimpleVsComposite(Audience\Model $simple, Audience\Model $composite): void
    {
        $this->assertFalse($simple->equals($composite));
        $this->assertFalse($composite->equals($simple));
    }


    /**
     * Test we can check equality between composite segments
     *
     * @param Audience\Model $left
     * @param Audience\Model $right
     * @param bool           $expected
     * @throws InsufficientHydrationException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerEqualComposite')]
    public function testEqualComposite(Audience\Model $left, Audience\Model $right, bool $expected): void
    {
        $this->assertEquals($expected, $left->equals($right));
        $this->assertEquals($expected, $right->equals($left));
    }

    // ============================ Data Providers ============================

    /**
     * @return array
     */
    public static function providerEqualSimple(): array
    {
        $left  = self::createSimple(Strategy\Folder::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF);
        $right = self::createSimple(Strategy\Folder::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF);

        return [
            // Audience\Model $left, Audience\Model $right, bool $expected
            [$left, $right, true],
            [$right, $left, true],
            [$left, (clone $right)->setEntityType(Strategy\Role::getEntityType()), false],
            [$left, (clone $right)->setEntityValue('01'), false],
            [$left, (clone $right)->setRelationship(Audience\Model::RELATIONSHIP_TEACHERS_OF), false],
        ];
    }


    /**
     * @return array
     * @throws DomainException
     */
    public static function providerEqualSimpleVsComposite(): array
    {
        $simple = self::createSimple(Strategy\Folder::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF);

        $child1 = self::createSimple(Strategy\Folder::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF);

        $composite1 = self::createComposite(Audience\Model::RELATIONSHIP_SELF);

        $composite2 = clone $composite1;
        $composite2->getChildren()->add($child1);

        $composite3 = clone $composite1;
        $composite3->getChildren()->add($child1)->add($child1);

        $composite4 = clone $composite1;
        $composite4->getChildren()->add($child1)->add($child1)->add($child1);

        return [
            [$simple, $composite1],
            [$simple, $composite2],
            [$simple, $composite3],
            [$simple, $composite4],
        ];
    }

    /**
     * @return array
     * @throws DomainException
     */
    public static function providerEqualComposite(): array
    {
        $data = [];

        // Both empty composites
        $left1  = self::createComposite(Audience\Model::RELATIONSHIP_SELF);
        $right1 = self::createComposite(Audience\Model::RELATIONSHIP_SELF);
        $data[] = [$left1, $right1, true];

        // Both with two children, same order
        $left2 = self::createComposite(Audience\Model::RELATIONSHIP_SELF);
        $left2->getChildren()
              ->add(self::createSimple(Strategy\Folder::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF))
              ->add(self::createSimple(Strategy\Role::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF));
        $right2 = self::createComposite(Audience\Model::RELATIONSHIP_SELF);
        $right2->getChildren()
               ->add(self::createSimple(Strategy\Folder::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF))
               ->add(self::createSimple(Strategy\Role::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF));
        $data[] = [$left2, $right2, true];

        // Both with two children, reversed order
        $left3 = self::createComposite(Audience\Model::RELATIONSHIP_SELF);
        $left3->getChildren()
              ->add(self::createSimple(Strategy\Folder::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF))
              ->add(self::createSimple(Strategy\Role::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF));
        $right3 = self::createComposite(Audience\Model::RELATIONSHIP_SELF);
        $right3->getChildren()
               ->add(self::createSimple(Strategy\Role::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF))
               ->add(self::createSimple(Strategy\Folder::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF));
        $data[] = [$left3, $right3, true];

        // First has duplicate children
        $left4 = self::createComposite(Audience\Model::RELATIONSHIP_SELF);
        $left4->getChildren()
              ->add(self::createSimple(Strategy\Folder::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF))
              ->add(self::createSimple(Strategy\Folder::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF));
        $right4 = self::createComposite(Audience\Model::RELATIONSHIP_SELF);
        $right4->getChildren()
               ->add(self::createSimple(Strategy\Role::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF))
               ->add(self::createSimple(Strategy\Folder::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF));
        $data[] = [$left4, $right4, false];

        // Second has duplicate children
        $left5 = self::createComposite(Audience\Model::RELATIONSHIP_SELF);
        $left5->getChildren()
              ->add(self::createSimple(Strategy\Role::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF))
              ->add(self::createSimple(Strategy\Folder::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF));
        $right5 = self::createComposite(Audience\Model::RELATIONSHIP_SELF);
        $right5->getChildren()
               ->add(self::createSimple(Strategy\Role::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF))
               ->add(self::createSimple(Strategy\Role::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF));
        $data[] = [$left5, $right5, false];

        // First has 1 child, second has 2
        $left6 = self::createComposite(Audience\Model::RELATIONSHIP_SELF);
        $left6->getChildren()
              ->add(self::createSimple(Strategy\Role::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF));
        $right6 = self::createComposite(Audience\Model::RELATIONSHIP_SELF);
        $right6->getChildren()
               ->add(self::createSimple(Strategy\Role::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF))
               ->add(self::createSimple(Strategy\Role::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF));
        $data[] = [$left6, $right6, false];

        return $data;
    }

    // ============================ Helpers ============================


    /**
     * Creates a simple audience segment
     *
     * @param string      $entityType
     * @param string|null $entityValue
     * @param string      $relationship
     * @return Audience\Model
     */
    private static function createSimple(string $entityType, ?string $entityValue, string $relationship): Audience\Model
    {
        return (new Audience\Model())
            ->setEntityType($entityType)
            ->setEntityValue($entityValue)
            ->setRelationship($relationship);
    }


    /**
     * Creates a composite audience segment
     *
     * @param string $relationship
     * @return Audience\Model
     * @throws DomainException
     */
    private static function createComposite(string $relationship): Audience\Model
    {
        return (new Audience\Model())
            ->setEntityType(Strategy\Composite::getEntityType())
            ->setChildren(Audience\Model::createCollection())
            ->setRelationship($relationship);
    }
}
