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

class ModelTest extends TestCase
{
    public function testSetFeatureAcceptsEnumAndReturnsStringValue(): void
    {
        $model = new Model();
        $model->setFeature(Feature::DIALOG_AGENT);

        $this->assertSame('dialogagent', $model->getFeature());
    }

    public function testSetFeatureAcceptsStringAndReturnsStringValue(): void
    {
        $model = new Model();
        $model->setFeature('chat');

        $this->assertSame('chat', $model->getFeature());
    }

    public function testSetFeatureAcceptsNull(): void
    {
        $model = new Model();
        $model->setFeature(null);

        $this->assertNull($model->getFeature());
    }

    public function testIdIsString(): void
    {
        $model = new Model();
        $model->setId('1f2e3d4c-5b6a-7890-abcd-ef0123456789');

        $this->assertSame('1f2e3d4c-5b6a-7890-abcd-ef0123456789', $model->getId());
    }

    public function testUserAccessor(): void
    {
        $user = new User();
        $model = new Model();
        $model->setUser($user);

        $this->assertSame($user, $model->getUser());
    }
}
