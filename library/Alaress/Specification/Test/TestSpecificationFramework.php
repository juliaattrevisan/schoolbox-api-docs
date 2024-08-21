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

class TestSpecificationFramework extends TestCase
{
    /**
     * @param ISpecification $candidate
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_candidate')]
    public function test_true($candidate): void
    {
        $spec = new TrueLeaf();
        $this->assertTrue($spec->isSatisfiedBy($candidate));
    }


    /**
     * @param ISpecification $candidate
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_candidate')]
    public function test_false($candidate): void
    {
        $spec = new FalseLeaf();
        $this->assertFalse($spec->isSatisfiedBy($candidate));
    }


    /**
     * @param ISpecification $candidate
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_negationValid')]
    public function test_negationValid(ISpecification $candidate): void
    {
        $spec = new Negation($candidate);
        $this->assertTrue($spec->isSatisfiedBy($candidate));
    }


    /**
     * @param ISpecification $candidate
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_negationInvalid')]
    public function test_negationInvalid(ISpecification $candidate): void
    {
        $spec = new Negation($candidate);
        $this->assertFalse($spec->isSatisfiedBy($candidate));
    }


    /**
     * @param ISpecification[] $components
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_andValid')]
    public function test_andValid($components): void
    {
        $spec = new Conjunction($components);
        $this->assertTrue($spec->isSatisfiedBy(null));
    }


    /**
     * @param ISpecification[] $components
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_andInvalid')]
    public function test_andInvalid($components): void
    {
        $spec = new Conjunction($components);
        $this->assertFalse($spec->isSatisfiedBy(null));
    }


    /**
     * @param ISpecification[] $components
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_orValid')]
    public function test_orValid($components): void
    {
        $spec = new Disjunction($components);
        $this->assertTrue($spec->isSatisfiedBy(null));
    }


    /**
     * @param ISpecification[] $components
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_orInvalid')]
    public function test_orInvalid($components): void
    {
        $spec = new Disjunction($components);
        $this->assertFalse($spec->isSatisfiedBy(null));
    }


    /**
     * @param ISpecification[] $components
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_xorValid')]
    public function test_xorValid($components): void
    {
        $spec = new ExclusiveOr($components);
        $this->assertTrue($spec->isSatisfiedBy(null));
    }


    /**
     * @param ISpecification[] $components
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_xorInvalid')]
    public function test_xorInvalid($components): void
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
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_equalsValid')]
    public function test_equalsValid(User $candidate, $propertyName, $expectedValue): void
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
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_equalsInvalid')]
    public function test_equalsInvalid(User $candidate, $propertyName, $expectedValue): void
    {
        $spec = new Equals($propertyName, $expectedValue);
        $this->assertFalse($spec->isSatisfiedBy($candidate));
    }


    /**
     * @param mixed  $candidate
     * @param string $class
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_instanceOfValid')]
    public function test_instanceOfValid($candidate, string $class): void
    {
        $spec = new IsInstanceOf($class);
        $this->assertTrue($spec->isSatisfiedBy($candidate));
    }


    /**
     * @param mixed  $candidate
     * @param string $class
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_instanceOfInvalid')]
    public function test_instanceOfInvalid($candidate, string $class): void
    {
        $spec = new IsInstanceOf($class);
        $this->assertFalse($spec->isSatisfiedBy($candidate));
    }


    // Data Providers


    /**
     * @return mixed[][]
     */
    public static function provider_candidate(): array
    {
        return [
            [1],
            [null],
            ['a string'],
        ];
    }


    public static function provider_negationValid(): array
    {
        return [
            [new FalseLeaf()],
            [new Negation(new TrueLeaf())],
        ];
    }


    public static function provider_negationInvalid(): array
    {
        return [
            [new TrueLeaf()],
            [new Negation(new FalseLeaf())],
        ];
    }


    public static function provider_andValid(): array
    {
        return [
            [[]],
            [[new TrueLeaf()]],
            [[new TrueLeaf(), new TrueLeaf()]],
            [[new TrueLeaf(), new TrueLeaf(), new TrueLeaf()]],
        ];
    }


    public static function provider_andInvalid(): array
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


    public static function provider_orValid(): array
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


    public static function provider_orInvalid(): array
    {
        return [
            [[]],
            [[new FalseLeaf()]],
            [[new FalseLeaf(), new FalseLeaf()]],
            [[new FalseLeaf(), new FalseLeaf(), new FalseLeaf()]],
        ];
    }


    public static function provider_xorValid(): array
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


    public static function provider_xorInvalid(): array
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


    public static function provider_equalsValid(): array
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


    public static function provider_equalsInvalid(): array
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


    public static function provider_instanceOfValid(): array
    {
        return [
            [new User(), User::class],
            [new Project(), Project::class],
            [new Project(), AbstractAssessment::class],
            [new Project(), AssessmentContext::class],
            [new Project(), IAssessable::class],
        ];
    }


    public static function provider_instanceOfInvalid(): array
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
