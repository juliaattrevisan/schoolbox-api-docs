<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\DialogAgent;

use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;

class Notifier extends AbstractModuleNotifier
{
    public const TRIGGER_REOPEN = 'reopen';
    public const TRIGGER_RESET = 'reset';


    /**
     * @inheritdoc
     */
    protected function getSlug()
    {
        return NSMapper::NS_DIALOG_AGENT;
    }


    /**
     * Attaches and detaches triggers properly depending on the dialog agent response status and transition
     *
     * @param TriggerBundleInterface $bundle
     * @return void
     */
    public function bundleTriggers($bundle)
    {
        // TODO: Implement bundleTriggers() method.
    }
}
