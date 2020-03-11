<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\Log;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Instance\Instance;
use Monolog\Logger;
use Schoolbox\L10n\Mapper as L10nMapper;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\L10n\RosettaStoneFactory;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\User;

class Notifier extends AbstractModuleNotifier
{
    const TRIGGER_CREATED = 'created';

    /** @var RosettaStone $l10n */
    protected $l10n;

    /**
     * @param Model  $model
     * @param string $method
     *
     * @return Envelope
     */
    protected function buildMessage($model, $method)
    {
        $envelope = new Envelope();

        if ($model->getResult() == 'Completed') {
            // Create the message

            $builder = new Builder();
            $builder->setTitle(sprintf("%s import completed", $this->l10n->translate('pastoralCare', false, true)))
                ->setIcon("/images/icon.php?size=".$this->iconSize."&icon=".NSMapper::NS_PASTORAL_LOG)
                ->setHref("/adminv2/import/pastoral/log/{$model->getImportId()}")
                ->setNamespace($this->getSlug())
                ->setSeverity(Logger::NOTICE)
                ->addTopic("pastoral_log.{$model->getImportId()}.created")
                ->setAllowSelfSend(true);

            $message = $builder->build();
            $envelope->targetToUser($model->getUserId(), $message, Instance::DELIVERY_INSTANT);
        }

        return $envelope;
    }

    /**
     * @param array $dependencies
     */
    public function loadDependencies($dependencies = null)
    {
        if (empty($dependencies["l10n"])) {
            $stoneFactory = new RosettaStoneFactory();
            $dependencies["l10n"] = $stoneFactory->build(L10nMapper::getInstance());
        }

        parent::loadDependencies($dependencies);
    }

    /**
     * @return string
     */
    protected function getSlug()
    {
        return NSMapper::NS_PASTORAL_LOG;
    }

    /**
     * @param TriggerBundleInterface $bundle
     */
    public function bundleTriggers($bundle)
    {
        switch ($bundle->getMethod()) {
            case BaseMapper::METHOD_CREATE:
                $bundle->attach(self::TRIGGER_CREATED, self::IMMEDIATELY);
                break;
        }
    }

    /**
     * @param Model $model
     * @param User  $userEffected
     * @param array $diff
     *
     * @return Envelope
     */
    public function notifyCreated($model, $userEffected, $diff = [])
    {
        return $this->buildMessage($model, self::TRIGGER_CREATED);
    }

    /**
     * @param string $label
     *
     * @return array
     */
    public function retrieveCallbacks($label)
    {
        return [
            [$this, "notify" . ucfirst($label)],
        ];
    }

    /**
     * @param RosettaStone $l10n
     * @return self|$this
     */
    public function setL10n(RosettaStone $l10n)
    {
        $this->l10n = $l10n;

        return $this;
    }
}
