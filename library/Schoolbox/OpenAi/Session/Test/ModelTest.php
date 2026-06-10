<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Session\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\OpenAi\Session\Feature;
use Schoolbox\OpenAi\Session\Model;
use Schoolbox\User;
use TypeError;
use ValueError;

/**
 * Unit tests for the AI session {@see Model}.
 */
class ModelTest extends TestCase
{
    /**
     * A Feature enum passed to setFeature is stored and returned as its string value.
     */
    public function testSetFeatureAcceptsEnumAndReturnsStringValue(): void
    {
        $model = new Model();
        $model->setFeature(Feature::DIALOG_AGENT);

        $this->assertSame('dialogagent', $model->getFeature());
    }

    /**
     * A plain string passed to setFeature is stored and returned unchanged.
     */
    public function testSetFeatureAcceptsStringAndReturnsStringValue(): void
    {
        $model = new Model();
        $model->setFeature('chat');

        $this->assertSame('chat', $model->getFeature());
    }

    /**
     * A plain string passed to setFeature must be a known Feature value.
     */
    public function testSetFeatureRejectsUnknownStringValue(): void
    {
        $model = new Model();

        $this->expectException(ValueError::class);
        $model->setFeature('unknown');
    }

    /**
     * The feature is null until explicitly set.
     */
    public function testFeatureIsNullUntilSet(): void
    {
        $model = new Model();

        $this->assertNull($model->getFeature());
    }

    /**
     * setFeature rejects null.
     */
    public function testSetFeatureRejectsNull(): void
    {
        $model = new Model();

        $this->expectException(TypeError::class);
        $model->setFeature(null);
    }

    /**
     * The id is a string (the table's char(36) UUID), round-tripping unchanged.
     */
    public function testIdIsString(): void
    {
        $model = new Model();
        $model->setId('1f2e3d4c-5b6a-7890-abcd-ef0123456789');

        $this->assertSame('1f2e3d4c-5b6a-7890-abcd-ef0123456789', $model->getId());
    }

    /**
     * The user set via setUser is returned by getUser.
     */
    public function testUserAccessor(): void
    {
        $user = new User();
        $model = new Model();
        $model->setUser($user);

        $this->assertSame($user, $model->getUser());
    }
}
