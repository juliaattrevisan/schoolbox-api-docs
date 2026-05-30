<?php

/**
 * IB registration session student notifier test
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Sessions\Student\Test;

use Alaress_Date_Format;
use PHPUnit\Framework\TestCase;
use Schoolbox\IB\Registrations\Sessions\Model as SessionModel;
use Schoolbox\IB\Registrations\Sessions\Student\Mapper;
use Schoolbox\IB\Registrations\Sessions\Student\Model;
use Schoolbox\IB\Registrations\Sessions\Student\Notifier;
use Schoolbox\External\User as ExternalUser;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Realtime\Message\NS;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Schedule\Event\Event;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\User;

class NotifierTest extends TestCase implements TriggerBundleInterface
{
    private const int STUDENT_ID = 123;
    private const int EFFECTOR_ID = 456;

    private const int SESSION_ID = 2;

    /** @var array<string,int|callable> */
    private array $attached = [];

    private ?Event $event = null;

    private string $method = '';

    public function attach($label, $timestamp)
    {
        $this->attached[$label] = $timestamp;
    }

    public function attachEach($array)
    {
        foreach ($array as $label => $timestamp) {
            $this->attach($label, $timestamp);
        }
    }

    public function detach($label)
    {
    }

    public function detachEach($array)
    {
        foreach ($array as $label) {
            $this->detach($label);
        }
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getEvent()
    {
        self::assertNotNull($this->event);
        return $this->event;
    }

    public function getTriggers()
    {
        return [];
    }

    public function testBundleTriggersAttachesUnconfirmedForPreparationToUnconfirmedTransition(): void
    {
        $notifier = $this->createNotifier();
        $this->method = BaseMapper::METHOD_UPDATE;
        $this->event = new Event(
            $this->createUser(self::EFFECTOR_ID),
            BaseMapper::METHOD_UPDATE,
            $this->createStudent(Model::STATUS_UNCONFIRMED),
            $this->createStub(Mapper::class),
            [
                'status' => [
                    Model::STATUS_UNCONFIRMED,
                    Model::STATUS_PREPARATION,
                ],
            ]
        );

        $notifier->bundleTriggers($this);

        self::assertSame(['unconfirmed'], array_keys($this->attached));
    }

    public function testBundleTriggersDoesNotAttachUnconfirmedForOtherStatusTransitions(): void
    {
        $notifier = $this->createNotifier();
        $this->method = BaseMapper::METHOD_UPDATE;
        $this->event = new Event(
            $this->createUser(self::EFFECTOR_ID),
            BaseMapper::METHOD_UPDATE,
            $this->createStudent(Model::STATUS_REGISTERED),
            $this->createStub(Mapper::class),
            [
                'status' => [
                    Model::STATUS_REGISTERED,
                    Model::STATUS_UNCONFIRMED,
                ],
            ]
        );

        $notifier->bundleTriggers($this);

        self::assertSame([], array_keys($this->attached));
    }

    public function testNotifyStudentUnconfirmedTargetsStudentProfile(): void
    {
        $notifier = $this->createNotifier();
        $before = Alaress_Date_Format::now()->getUnixTimeStamp();
        $envelope = $notifier->notifyStudentUnconfirmed(
            $this->createStudent(Model::STATUS_UNCONFIRMED),
            $this->createEffector(self::EFFECTOR_ID),
            [
                'status' => [
                    Model::STATUS_UNCONFIRMED,
                    Model::STATUS_PREPARATION,
                ],
            ]
        );

        $messages = $envelope->getAll();
        self::assertCount(1, $messages);
        self::assertSame(
            'notify.user.' . self::STUDENT_ID . '.ib_registration.' . self::SESSION_ID . '.student.' . self::STUDENT_ID . '.unconfirmed',
            array_key_first($messages)
        );

        $message = reset($messages);
        $payload = $message->jsonSerialize();
        self::assertSame(
            '/search/user/' . self::STUDENT_ID,
            $payload['href']
        );
        self::assertSame(
            ':staff has requested you to confirm your :details.',
            $payload['title']['text']
        );
        self::assertSame(
            'Test Staff',
            $payload['title']['params']['staff']->jsonSerialize()['text']
        );
        self::assertSame(
            [
                'text' => 'IB Registration details',
                'href' => '/search/user/' . self::STUDENT_ID,
                'mode' => 0,
                'time' => 0,
            ],
            $payload['title']['params']['details']->jsonSerialize()
        );
        $metaTime = $payload['meta']['time'];
        self::assertIsInt($metaTime);
        self::assertGreaterThanOrEqual($before, $metaTime);
        self::assertLessThanOrEqual(Alaress_Date_Format::now()->getUnixTimeStamp(), $metaTime);
    }

    public function testNotifyStudentUnconfirmedReturnsEmptyEnvelopeWhenStudentHasNoUser(): void
    {
        $notifier = $this->createNotifier();
        $student = (new Model())->setStatus(Model::STATUS_UNCONFIRMED);

        $envelope = $notifier->notifyStudentUnconfirmed(
            $student,
            $this->createEffector(self::EFFECTOR_ID),
            [
                'status' => [
                    Model::STATUS_UNCONFIRMED,
                    Model::STATUS_PREPARATION,
                ],
            ]
        );

        self::assertSame([], $envelope->getAll());
    }

    private function createNotifier(): Notifier
    {
        $ns = new NS();
        $ns->setId(1);
        $ns->setSlug('ib_registration');
        $ns->setLabel('IB registration');
        $nsMapper = $this->createStub(NSMapper::class);
        $nsMapper
            ->method('findOneBySlug')
            ->willReturn($ns);

        return new Notifier(
            $this->createStub(IConfigLoader::class),
            [
                'mapper' => $this->createStub(Mapper::class),
                'nsMapper' => $nsMapper,
                'externalUser' => $this->createStub(ExternalUser::class),
            ]
        );
    }

    private function createStudent(int $status): Model
    {
        return (new Model())
            ->setId(789)
            ->setStatus($status)
            ->setRegistrationSession((new SessionModel())->setId(self::SESSION_ID))
            ->setUser($this->createUser(self::STUDENT_ID));
    }


    private function createUser(int $id): User
    {
        return (new User())->setId($id);
    }

    private function createEffector(int $id): User
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getFullName')->willReturn('Test Staff');

        return $user;
    }
}
