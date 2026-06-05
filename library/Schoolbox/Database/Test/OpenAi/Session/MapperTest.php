<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Database\Test\OpenAi\Session;

use Alaress\Database\Test\TestConnectorTrait;
use PHPUnit\Framework\TestCase;
use Schoolbox\OpenAi\Session\Feature;
use Schoolbox\OpenAi\Session\Mapper;
use Schoolbox\OpenAi\Session\Model;
use Schoolbox\UserMapper;

class MapperTest extends TestCase
{
    use TestConnectorTrait;

    public function setUp(): void
    {
        $this->connectSchoolbox();
        $this->schoolbox->beginTransaction();

        // Inject the transactional connection so that save/find calls are
        // included in the transaction and rolled back in tearDown().
        Mapper::getInstance()->setDatabase($this->schoolbox);
        UserMapper::getInstance()->setDatabase($this->schoolbox);
    }

    public function tearDown(): void
    {
        $this->schoolbox->rollback();
    }

    public function testSaveGeneratesUuidAndRoundTrips(): void
    {
        $model = (new Model())->setFeature(Feature::CHAT);

        Mapper::getInstance()->save($model);

        $id = $model->getId();
        $this->assertNotNull($id);
        $this->assertSame(36, strlen($id), 'A char(36) UUID should be generated on save');

        $reloaded = Mapper::getInstance()->findById($id);

        $this->assertInstanceOf(Model::class, $reloaded);
        $this->assertSame($id, $reloaded->getId());
        $this->assertSame('chat', $reloaded->getFeature());
        $this->assertNull($reloaded->getUser());
    }

    public function testUserJoinRoundTrips(): void
    {
        // Use any existing user in the (transactional) test DB for the created_by FK.
        $user = UserMapper::getInstance()->findOneByColumnList([]);
        $this->assertNotNull($user, 'Test DB is expected to contain at least one user');

        $model = (new Model())
            ->setFeature(Feature::DIALOG_AGENT)
            ->setUser($user);

        Mapper::getInstance()->save($model);

        $reloaded = Mapper::getInstance()->findById($model->getId());

        $this->assertNotNull($reloaded->getUser());
        $this->assertSame($user->getId(), $reloaded->getUser()->getId());
    }
}
