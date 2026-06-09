<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Database\Test\OpenAi\Session;

use Alaress\Database\Test\TestConnectorTrait;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Schoolbox\OpenAi\Session\Feature;
use Schoolbox\OpenAi\Session\Mapper;
use Schoolbox\OpenAi\Session\Model;
use Schoolbox\UserMapper;

/**
 * Database tests for {@see Mapper::findOrCreate()}.
 */
class FindOrCreateTest extends TestCase
{
    use TestConnectorTrait;

    /**
     * Open a transaction and point the mappers at it, so writes roll back afterwards.
     */
    public function setUp(): void
    {
        $this->connectSchoolbox();
        $this->schoolbox->beginTransaction();

        Mapper::getInstance()->setDatabase($this->schoolbox);
        UserMapper::getInstance()->setDatabase($this->schoolbox);
    }

    /**
     * Roll back everything written during the test.
     */
    public function tearDown(): void
    {
        $this->schoolbox->rollback();
    }

    /**
     * First call with an unseen id inserts the session row with the given feature.
     */
    public function testCreatesWhenAbsent(): void
    {
        $id = Uuid::uuid4()->toString();

        $session = Mapper::getInstance()->findOrCreate($id, Feature::CHAT->value, null);

        $this->assertInstanceOf(Model::class, $session);
        $this->assertSame($id, $session->getId());

        $reloaded = Mapper::getInstance()->findById($id);
        $this->assertNotNull($reloaded);
        $this->assertSame('chat', $reloaded->getFeature());
    }

    /**
     * The created_by user is persisted as a real FK (not coerced to 0) and round-trips.
     */
    public function testCreatesWithUserPersistsCreatedBy(): void
    {
        $user = UserMapper::getInstance()->findOneByColumnList([]);
        $this->assertNotNull($user, 'Test DB is expected to contain at least one user');

        $id = Uuid::uuid4()->toString();
        Mapper::getInstance()->findOrCreate($id, Feature::CHAT->value, $user);

        $reloaded = Mapper::getInstance()->findById($id);
        $this->assertNotNull($reloaded->getUser());
        $this->assertSame($user->getId(), $reloaded->getUser()->getId());
    }

    /**
     * A second call with the same id does not insert a duplicate and preserves the
     * original feature (first writer wins).
     */
    public function testReusesWhenPresentAndKeepsOriginalFeature(): void
    {
        $id = Uuid::uuid4()->toString();

        Mapper::getInstance()->findOrCreate($id, Feature::CHAT->value, null);
        Mapper::getInstance()->findOrCreate($id, Feature::DIALOG_AGENT->value, null);

        $matches = Mapper::getInstance()->findByColumn('id', $id);
        $this->assertCount(1, $matches, 'No duplicate session row should be created');
        $this->assertSame('chat', $matches->first()->getFeature(), 'Original feature is preserved');
    }
}
