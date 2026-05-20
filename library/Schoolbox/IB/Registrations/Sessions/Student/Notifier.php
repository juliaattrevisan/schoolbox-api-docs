<?php

/**
 * IB registration session student notifier
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Sessions\Student;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
use Monolog\Logger;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\User;

class Notifier extends AbstractModuleNotifier
{
    protected $slug = NSMapper::NS_IB_REGISTRATION;
    public const string TRIGGER_UNCONFIRMED = 'unconfirmed';
    private const string PATTERN_UNCONFIRMED_TITLE = ':staff has requested you to confirm your IB Registration details.';
    private const string PATTERN_STUDENT_PROFILE_HREF = '/search/user/%d';

    protected function getSlug(): string
    {
        return $this->slug;
    }

    public function bundleTriggers($bundle): void
    {
        if ($bundle->getMethod() !== BaseMapper::METHOD_UPDATE) {
            return;
        }

        if (!$this->isPreparationToUnconfirmed($bundle->getEvent()->getDiff())) {
            return;
        }

        $bundle->attach(self::TRIGGER_UNCONFIRMED, self::IMMEDIATELY);
    }

    public function notifyStudentUnconfirmed(Model $model, User $effector, ?array $diff): Envelope
    {
        $envelope = new Envelope();

        if (!$this->isPreparationToUnconfirmed($diff)) {
            return $envelope;
        }

        $student = $model->getUser();
        $studentId = $student?->getId();
        $sessionId = $model->getRegistrationSession()?->getId();

        if (!$studentId || !$sessionId) {
            return $envelope;
        }

        $href = sprintf(self::PATTERN_STUDENT_PROFILE_HREF, $studentId);
        $instance = (new Builder())
            ->setTitle(self::PATTERN_UNCONFIRMED_TITLE)
            ->setParam('staff', new Param($effector->getFullName()))
            ->setIcon($this->getUserIcon($effector))
            ->setHref($href)
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::INFO)
            ->addTopic(
                sprintf(
                    '%s.%d.%s.%d.%s',
                    $this->getSlug(),
                    $sessionId,
                    'student',
                    $studentId,
                    self::TRIGGER_UNCONFIRMED
                )
            )
            ->build();

        $envelope->targetToUser($studentId, $instance);

        return $envelope;
    }

    private function isPreparationToUnconfirmed(?array $diff): bool
    {
        if (!isset($diff['status']) || !is_array($diff['status']) || count($diff['status']) < 2) {
            return false;
        }

        return (int) $diff['status'][0] === Model::STATUS_UNCONFIRMED
            && (int) $diff['status'][1] === Model::STATUS_PREPARATION;
    }
}
