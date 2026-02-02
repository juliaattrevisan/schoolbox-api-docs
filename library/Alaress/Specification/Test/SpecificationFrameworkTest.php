<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Specification\Test;

use Alaress\Specification\Conjunction;
use Alaress\Specification\Disjunction;
use Alaress\Specification\Equals;
use Alaress\Specification\ExclusiveOr;
use Alaress\Specification\FalseLeaf;
use Alaress\Specification\IsInstanceOf;
use Alaress\Specification\ISpecification;
use Alaress\Specification\Negation;
use Alaress\Specification\TrueLeaf;
use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Assessment\AbstractAssessment;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\User;

class SpecificationFrameworkTest extends TestCase
{
    /**
     * @param ISpecification $candidate
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerCandidate')]
    public function testTrue($candidate): void
    {
        $spec = new TrueLeaf();
        $this->assertTrue($spec->isSatisfiedBy($candidate));
    }


    /**
     * @param ISpecification $candidate
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerCandidate')]
    public function testFalse($candidate): void
    {
        $spec = new FalseLeaf();
        $this->assertFalse($spec->isSatisfiedBy($candidate));
    }


    /**
     * @param ISpecification $candidate
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerNegationValid')]
    public function testNegationValid(ISpecification $candidate): void
    {
        $spec = new Negation($candidate);
        $this->assertTrue($spec->isSatisfiedBy($candidate));
    }


    /**
     * @param ISpecification $candidate
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerNegationInvalid')]
    public function testNegationInvalid(ISpecification $candidate): void
    {
        $spec = new Negation($candidate);
        $this->assertFalse($spec->isSatisfiedBy($candidate));
    }


    /**
     * @param ISpecification[] $components
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerAndValid')]
    public function testAndValid($components): void
    {
        $spec = new Conjunction($components);
        $this->assertTrue($spec->isSatisfiedBy(null));
    }


    /**
     * @param ISpecification[] $components
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerAndInvalid')]
    public function testAndInvalid($components): void
    {
        $spec = new Conjunction($components);
        $this->assertFalse($spec->isSatisfiedBy(null));
    }


    /**
     * @param ISpecification[] $components
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerOrValid')]
    public function testOrValid($components): void
    {
        $spec = new Disjunction($components);
        $this->assertTrue($spec->isSatisfiedBy(null));
    }


    /**
     * @param ISpecification[] $components
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerOrInvalid')]
    public function testOrInvalid($components): void
    {
        $spec = new Disjunction($components);
        $this->assertFalse($spec->isSatisfiedBy(null));
    }


    /**
     * @param ISpecification[] $components
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerXorValid')]
    public function testXorValid($components): void
    {
        $spec = new ExclusiveOr($components);
        $this->assertTrue($spec->isSatisfiedBy(null));
    }


    /**
     * @param ISpecification[] $components
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerXorInvalid')]
    public function testXorInvalid($components): void
    {
        $spec = new ExclusiveOr($components);
        $this->assertFalse($spec->isSatisfiedBy(null));
    }


    /**
     * @param User   $candidate
     * @param mixed  $expectedValue
     * @param string $propertyName
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerEqualsValid')]
    public function testEqualsValid(User $candidate, $propertyName, $expectedValue): void
    {
        $spec = new Equals($propertyName, $expectedValue);
        $this->assertTrue($spec->isSatisfiedBy($candidate));
    }


    /**
     * @param User   $candidate
     * @param mixed  $expectedValue
     * @param string $propertyName
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerEqualsInvalid')]
    public function testEqualsInvalid(User $candidate, $propertyName, $expectedValue): void
    {
        $spec = new Equals($propertyName, $expectedValue);
        $this->assertFalse($spec->isSatisfiedBy($candidate));
    }


    /**
     * @param mixed  $candidate
     * @param string $class
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInstanceOfValid')]
    public function testInstanceOfValid($candidate, string $class): void
    {
        $spec = new IsInstanceOf($class);
        $this->assertTrue($spec->isSatisfiedBy($candidate));
    }


    /**
     * @param mixed  $candidate
     * @param string $class
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInstanceOfInvalid')]
    public function testInstanceOfInvalid($candidate, string $class): void
    {
        $spec = new IsInstanceOf($class);
        $this->assertFalse($spec->isSatisfiedBy($candidate));
    }


    // Data Providers


    /**
     * @return mixed[][]
     */
    public static function providerCandidate(): array
    {
        return [
            [1],
            [null],
            ['a string'],
        ];
    }


    public static function providerNegationValid(): array
    {
        return [
            [new FalseLeaf()],
            [new Negation(new TrueLeaf())],
        ];
    }


    public static function providerNegationInvalid(): array
    {
        return [
            [new TrueLeaf()],
            [new Negation(new FalseLeaf())],
        ];
    }


    public static function providerAndValid(): array
    {
        return [
            [[]],
            [[new TrueLeaf()]],
            [[new TrueLeaf(), new TrueLeaf()]],
            [[new TrueLeaf(), new TrueLeaf(), new TrueLeaf()]],
        ];
    }


    public static function providerAndInvalid(): array
    {
        return [
            [[new FalseLeaf()]],
            [[new FalseLeaf(), new TrueLeaf()]],
            [[new TrueLeaf(), new FalseLeaf()]],
            [[new FalseLeaf(), new TrueLeaf(), new TrueLeaf()]],
            [[new TrueLeaf(), new FalseLeaf(), new TrueLeaf()]],
            [[new TrueLeaf(), new TrueLeaf(), new FalseLeaf()]],
            [[new FalseLeaf(), new FalseLeaf(), new TrueLeaf()]],
            [[new FalseLeaf(), new FalseLeaf(), new FalseLeaf()]],
        ];
    }


    public static function providerOrValid(): array
    {
        return [
            [[new TrueLeaf()]],
            [[new FalseLeaf(), new TrueLeaf()]],
            [[new TrueLeaf(), new FalseLeaf()]],
            [[new TrueLeaf(), new TrueLeaf()]],
            [[new TrueLeaf(), new TrueLeaf(), new TrueLeaf()]],
            [[new FalseLeaf(), new TrueLeaf(), new TrueLeaf()]],
            [[new TrueLeaf(), new FalseLeaf(), new TrueLeaf()]],
            [[new TrueLeaf(), new FalseLeaf(), new FalseLeaf()]],
        ];
    }


    public static function providerOrInvalid(): array
    {
        return [
            [[]],
            [[new FalseLeaf()]],
            [[new FalseLeaf(), new FalseLeaf()]],
            [[new FalseLeaf(), new FalseLeaf(), new FalseLeaf()]],
        ];
    }


    public static function providerXorValid(): array
    {
        return [
            [[new TrueLeaf()]],
            [[new FalseLeaf(), new TrueLeaf()]],
            [[new TrueLeaf(), new FalseLeaf()]],
            [[new FalseLeaf(), new FalseLeaf(), new TrueLeaf()]],
            [[new FalseLeaf(), new TrueLeaf(), new FalseLeaf()]],
            [[new TrueLeaf(), new FalseLeaf(), new FalseLeaf()]],
            [[new TrueLeaf(), new TrueLeaf(), new TrueLeaf()]],
        ];
    }


    public static function providerXorInvalid(): array
    {
        return [
            [[]],
            [[new FalseLeaf()]],
            [[new FalseLeaf(), new FalseLeaf()]],
            [[new TrueLeaf(), new TrueLeaf()]],
            [[new FalseLeaf(), new FalseLeaf(), new FalseLeaf()]],
            [[new FalseLeaf(), new TrueLeaf(), new TrueLeaf()]],
            [[new TrueLeaf(), new FalseLeaf(), new TrueLeaf()]],
            [[new TrueLeaf(), new TrueLeaf(), new FalseLeaf()]],
        ];
    }


    public static function providerEqualsValid(): array
    {
        $id        = 101;
        $firstName = 'John';
        $lastName  = 'Smith';
        $user      = new User();
        $user
            ->setId($id)
            ->setFirstname($firstName)
            ->setLastname($lastName);

        return [
            [$user, 'id', $id],
            [$user, 'firstname', $firstName],
            [$user, 'lastname', $lastName],
        ];
    }


    public static function providerEqualsInvalid(): array
    {
        $user = new User();
        $user
            ->setId(101)
            ->setFirstname('John')
            ->setLastname('Smith');

        return [
            [$user, 'id', '101'],
            [$user, 'id', 102],
            [$user, 'firstname', 'john'],
            [$user, 'firstname', 'Jonny'],
            [$user, 'lastname', 'Ryan'],
        ];
    }


    public static function providerInstanceOfValid(): array
    {
        return [
            [new User(), User::class],
            [new Project(), Project::class],
            [new Project(), AbstractAssessment::class],
            [new Project(), AssessmentContext::class],
            [new Project(), IAssessable::class],
        ];
    }


    public static function providerInstanceOfInvalid(): array
    {
        return [
            [new Project(), User::class],
            [new User(), Project::class],
            [new User(), 'a user'],
            ['user', User::class],
            [null, User::class],
            [true, User::class],
            [false, User::class],
            [1, User::class],
        ];
    }
}
