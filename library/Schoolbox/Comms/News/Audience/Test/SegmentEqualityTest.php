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
    public function providerEqualSimple(): array
    {
        $left  = $this->createSimple(Strategy\Folder::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF);
        $right = $this->createSimple(Strategy\Folder::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF);

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
    public function providerEqualSimpleVsComposite(): array
    {
        $simple = $this->createSimple(Strategy\Folder::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF);

        $child1 = $this->createSimple(Strategy\Folder::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF);

        $composite1 = $this->createComposite(Audience\Model::RELATIONSHIP_SELF);

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
    public function providerEqualComposite(): array
    {
        $data = [];

        // Both empty composites
        $left1  = $this->createComposite(Audience\Model::RELATIONSHIP_SELF);
        $right1 = $this->createComposite(Audience\Model::RELATIONSHIP_SELF);
        $data[] = [$left1, $right1, true];

        // Both with two children, same order
        $left2 = $this->createComposite(Audience\Model::RELATIONSHIP_SELF);
        $left2->getChildren()
              ->add($this->createSimple(Strategy\Folder::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF))
              ->add($this->createSimple(Strategy\Role::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF));
        $right2 = $this->createComposite(Audience\Model::RELATIONSHIP_SELF);
        $right2->getChildren()
               ->add($this->createSimple(Strategy\Folder::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF))
               ->add($this->createSimple(Strategy\Role::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF));
        $data[] = [$left2, $right2, true];

        // Both with two children, reversed order
        $left3 = $this->createComposite(Audience\Model::RELATIONSHIP_SELF);
        $left3->getChildren()
              ->add($this->createSimple(Strategy\Folder::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF))
              ->add($this->createSimple(Strategy\Role::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF));
        $right3 = $this->createComposite(Audience\Model::RELATIONSHIP_SELF);
        $right3->getChildren()
               ->add($this->createSimple(Strategy\Role::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF))
               ->add($this->createSimple(Strategy\Folder::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF));
        $data[] = [$left3, $right3, true];

        // First has duplicate children
        $left4 = $this->createComposite(Audience\Model::RELATIONSHIP_SELF);
        $left4->getChildren()
              ->add($this->createSimple(Strategy\Folder::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF))
              ->add($this->createSimple(Strategy\Folder::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF));
        $right4 = $this->createComposite(Audience\Model::RELATIONSHIP_SELF);
        $right4->getChildren()
               ->add($this->createSimple(Strategy\Role::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF))
               ->add($this->createSimple(Strategy\Folder::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF));
        $data[] = [$left4, $right4, false];

        // Second has duplicate children
        $left5 = $this->createComposite(Audience\Model::RELATIONSHIP_SELF);
        $left5->getChildren()
              ->add($this->createSimple(Strategy\Role::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF))
              ->add($this->createSimple(Strategy\Folder::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF));
        $right5 = $this->createComposite(Audience\Model::RELATIONSHIP_SELF);
        $right5->getChildren()
               ->add($this->createSimple(Strategy\Role::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF))
               ->add($this->createSimple(Strategy\Role::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF));
        $data[] = [$left5, $right5, false];

        // First has 1 child, second has 2
        $left6 = $this->createComposite(Audience\Model::RELATIONSHIP_SELF);
        $left6->getChildren()
              ->add($this->createSimple(Strategy\Role::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF));
        $right6 = $this->createComposite(Audience\Model::RELATIONSHIP_SELF);
        $right6->getChildren()
               ->add($this->createSimple(Strategy\Role::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF))
               ->add($this->createSimple(Strategy\Role::getEntityType(), '10', Audience\Model::RELATIONSHIP_SELF));
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
    private function createSimple(string $entityType, ?string $entityValue, string $relationship): Audience\Model
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
    private function createComposite(string $relationship): Audience\Model
    {
        return (new Audience\Model())
            ->setEntityType(Strategy\Composite::getEntityType())
            ->setChildren(Audience\Model::createCollection())
            ->setRelationship($relationship);
    }
}
