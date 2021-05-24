<?php

namespace Schoolbox\Fixtures\Import;

use Alaress\Async\Notification\Instance\Builder;
use Monolog\Logger;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Fixtures\Import\Mapper as ImportMapper;
use Schoolbox\Fixtures\Import\Model as Import;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\User;

class Notifier extends AbstractModuleNotifier
{
    const TRIGGER_CREATED = 'created';
    const TRIGGER_UPDATED = 'updated';

    /**
     * @var ImportMapper
     */
    protected $importMapper;

    /**
     * @param Model $model
     * @param string $method
     * @return Envelope
     */
    protected function buildMessage($model, $method)
    {
        $envelope = new Envelope();

        // Get hydrated import record

        /** @var Import $import */
        $import = $this->getImportMapper()->findById($model->getId());

        // Create the message

        $builder = new Builder();
        $builder->setTitle("A fixture import has " . $import->getStatus())
            ->setIcon("/images/icon.php?size=32&icon=" . NSMapper::NS_SPORTS_FIXTURES_IMPORT)
            ->setHref("/fixtures/import-progress?id=" . $import->getId())
            ->setNamespace($this->getSlug())
            ->setSeverity(Logger::ERROR)
            ->addTopic("sports_fixtures_import." . $model->getId() . ".{$method}");

        $message = $builder->build();

        // Work out who to send the message to

        $this->log->info("Sending to {$import->getOwnerId()} - A fixture import has {$import->getStatus()}");
        $envelope->targetToUser($import->getOwnerId(), $message);

        return $envelope;
    }

    /**
     * @param array $dependencies
     */
    public function loadDependencies($dependencies = null)
    {
        if (empty($dependencies["importMapper"])) {
            $dependencies["importMapper"] = ImportMapper::getInstance();
        }

        parent::loadDependencies($dependencies);
    }

    /**
     * @return string
     */
    protected function getSlug()
    {
        return NSMapper::NS_SPORTS_FIXTURES_IMPORT;
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
            case BaseMapper::METHOD_UPDATE:
                $bundle->attach(self::TRIGGER_UPDATED, self::IMMEDIATELY);
                break;
        }
    }

    /**
     * @param Model $model
     * @param User $userEffected
     * @param array $diff
     * @return Envelope
     */
    public function notifyCreated($model, $userEffected, $diff = [])
    {
        return $this->buildMessage($model, self::TRIGGER_CREATED);
    }

    /**
     * @param Model $model
     * @param User $userEffected
     * @param array $diff
     * @return Envelope
     */
    public function notifyUpdated($model, $userEffected, $diff = [])
    {
        return $this->buildMessage($model, self::TRIGGER_UPDATED);
    }

    /**
     * @param string $label
     * @return array
     */
    public function retrieveCallbacks($label)
    {
        return [
            [ $this, "notify" . ucfirst($label) ]
        ];
    }

    // Here be the getters/setter

    /**
     * @return ImportMapper
     */
    public function getImportMapper()
    {
        return $this->importMapper;
    }

    /**
     * @param ImportMapper $importMapper
     * @return $this
     */
    public function setImportMapper($importMapper)
    {
        $this->importMapper = $importMapper;
        return $this;
    }
}
