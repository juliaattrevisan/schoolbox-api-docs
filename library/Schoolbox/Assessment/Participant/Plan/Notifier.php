<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Participant\Plan;

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;

/**
 * Class Notifier
 */
class Notifier extends AbstractModuleNotifier
{
    /**
     * @return string|void
     */
    protected function getSlug()
    {
        return '';
    }

    /**
     * Instantiate all your dependencies within yourself.
     *
     * @param mixed $dependencies
     */
    public function loadDependencies($dependencies = null): void
    {
        // Dunno what dependencies this has yet
        parent::loadDependencies();
    }

    /**
     * If created, raise a notification. No conditions, no other functions. That's it.
     *
     * @param TriggerBundle $bundle
     */
    public function bundleTriggers($bundle): void
    {
        // What kind of object was saved?
        $assessable = $bundle->getEvent()->getObject();

        if ($assessable instanceof SubmissionBox) {
            // Trigger the submission box's notifier
            return;
        }

        if ($assessable instanceof \Schoolbox\Learning\Quiz\Model) {
            // Trigger the quiz notifier
            return;
        }

        // Do nothing
    }
}
