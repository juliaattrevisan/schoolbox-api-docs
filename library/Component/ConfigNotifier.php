<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Redis;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Realtime\Routing\Cache\Redis\WriteCache;
use Schoolbox\Realtime\Routing\Subscription\Loader;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\User;

class Component_ConfigNotifier extends AbstractModuleNotifier
{
    const TRIGGER_CREATED = 'created';
    const TRIGGER_UPDATED = 'updated';
    const TRIGGER_DELETED = 'deleted';


    /** @var int[] */
    private $definitionCache = [];

    /**
     * @return string
     */
    protected function getSlug()
    {
        return 'component_config';
    }


    /**
     * @param TriggerBundleInterface $bundle
     * @return void
     */
    public function bundleTriggers($bundle)
    {
        $method = $bundle->getEvent()->getAction();

        switch ($method) {
            case Mapper::METHOD_CREATE:
                $bundle->attach(self::TRIGGER_CREATED, self::IMMEDIATELY);
                break;

            case Mapper::METHOD_UPDATE:
                $bundle->attach(self::TRIGGER_UPDATED, self::IMMEDIATELY);
                break;

            case Mapper::METHOD_DELETE:
                $bundle->attach(self::TRIGGER_DELETED, self::IMMEDIATELY);
                break;
        }
    }


    /**
     * Override standard behaviour - this notifier is not dependent on SQL configs
     *
     * @param string $label
     * @return callable[]
     */
    public function retrieveCallbacks($label)
    {
        return [
            [ $this, "notifySystem$label" ]
        ];
    }


    /**
     * @param Component_Config $model
     * @param User             $effector
     * @throws Mapper\Exception
     */
    public function notifySystemCreated(Component_Config $model, User $effector = null)
    {
        if (!$this->definitionIsSource($model)) {
            return;
        }

        $this->handleCreate($this->getInstanceFromConfig($model), $model->getValue());
    }


    /**
     * @param Component_Config $model
     * @param User             $effector
     * @param array            $diff
     * @throws Mapper\Exception
     */
    public function notifySystemUpdated(Component_Config $model, User $effector = null, array $diff = [])
    {
        if (!$this->definitionIsSource($model)) {
            return;
        }

        $instance = $this->getInstanceFromConfig($model);

        // update is a delete-and-replace action
        $this->handleDelete($instance, $diff['value'][1]);
        $this->handleCreate($instance, $diff['value'][0]);
    }


    /**
     * KNOWN LIMITATION:  If two components point to the exact same alt-source (same ID and NS) the redirect will
     *                    be deleted, despite the fact that there is still a component pointing to that alt-source.
     *
     * @param Component_Config $model
     * @param User             $effector
     * @throws Mapper\Exception
     */
    public function notifySystemDeleted(Component_Config $model, User $effector = null)
    {
        if (!$this->definitionIsSource($model)) {
            return;
        }

        $this->handleDelete($this->getInstanceFromConfig($model), $model->getValue());
    }


    /**
     * @param Component_Instance $instance
     * @param int                $altSourceId
     */
    private function handleCreate(Component_Instance $instance, $altSourceId)
    {
        $this->handle(
            'INSERT INTO msg_redirect (`from`, `to`) VALUES (:from, :to) ON DUPLICATE KEY UPDATE `to` = :to',
            $instance,
            $altSourceId
        );
    }


    /**
     * @param Component_Instance $instance
     * @param int                $altSourceId
     */
    private function handleDelete(Component_Instance $instance, $altSourceId)
    {
        $this->handle(
            'DELETE FROM msg_redirect WHERE `from` = :from AND `to` = :to',
            $instance,
            $altSourceId
        );
    }


    /**
     * Rebuild the subscription cache if required.
     *
     * The subscription cache needs to be rebuilt if:
     * * the component has an alt source
     * * the component's definition includes a notification namespace
     *
     * TODO migrate to taskd when that exists
     *
     * @param string             $query
     * @param Component_Instance $instance
     * @param int                $altSourceId
     */
    private function handle($query, Component_Instance $instance, $altSourceId)
    {
        if (!$this->verifyWorkToBeDone($instance, $altSourceId)) {
            return;
        }

        foreach ([ UserAccess::ACCESS_READ, UserAccess::ACCESS_WRITE, UserAccess::ACCESS_FULL ] as $access) {
            $from = $this->determineSourcePath($instance, $access, $altSourceId);
            $to   = $this->determineDestinationPath($instance, $access);

            if (isset($from, $to)) {
                $this->doQuery($query, $from, $to);
            }
        }

        // The routing subscription loader is stored in Redis, not jst cached
        // TODO desingleton these
        $redis      = Redis::getCacheClientInstance();
        $redisQueue = Redis::getSignalQueueClientInstance();

        // update the cache
        // TODO replace with $di('subscriptionCache')
        $cache = new WriteCache($redis, new Loader\Factory($redis), $redisQueue);

        $cache->cacheRedirects(INSTANCE_ID);
    }

    /**
     * @param string $query
     * @param string $from
     * @param string $to
     */
    private function doQuery($query, $from, $to)
    {
        $this->getMapper()->getDatabase()->query($query, [ 'from' => $from, 'to' => $to ]);
    }


    /**
     * @param Component_Instance $instance
     * @param int                $altSourceId
     * @return bool
     */
    private function verifyWorkToBeDone(Component_Instance $instance, $altSourceId)
    {
        $hostFolderId = $instance->getIdentifier();

        if (!$altSourceId || (int)$hostFolderId === (int)$altSourceId) {
            return false;
        }

        $ns = $instance->getDefinition()->getNamespace();

        if (!$ns) {
            return false;
        }

        return true;
    }


    /**
     * @param Component_Config $model
     * @return Component_Instance
     */
    private function getInstanceFromConfig(Component_Config $model)
    {
        $instance = Component_InstanceMapper::getInstance()->findById($model->getInstanceId());

        // ensure definition hydrated
        $instance->setDefinition(Component_DefinitionMapper::getInstance()->findById($instance->getDefinitionId()));

        return $instance;
    }


    /**
     * @param Component_Config $model
     * @return bool
     */
    private function definitionIsSource(Component_Config $model)
    {
        if (empty($this->definitionCache)) {
            $this->definitionCache = Component_ConfigDefinitionMapper::getInstance()->findIdsByColumn('key', 'src');
        }
        return in_array($model->getConfigDefinitionId(), $this->definitionCache, false);
    }


    /**
     * Determines what the source path should be for the message redirect.
     *
     * @param Component_Instance $instance
     * @param int                $access
     * @param int                $altSourceId
     * @return null|string
     */
    private function determineSourcePath(Component_Instance $instance, int $access, int $altSourceId): ?string
    {
        $namespace = $instance->getDefinition()->getNamespace();
        if (!$namespace) {
            return null;
        }
        $namespaceSlug = $namespace->getSlug();
        if ($namespaceSlug === null) {
            return null;
        }
        $namespaceSuffix = $instance->getDefinition()->getNamespaceSuffix();
        if ($namespaceSuffix === null) {
            return null;
        }
        $service = UserAccess::SERVICE_HOMEPAGE;
        return "*.notify.service.$service.$altSourceId.$access.$namespaceSlug.$namespaceSuffix";
    }


    /**
     * Determines what the destination path should be for the message redirect.
     *
     * @param Component_Instance $instance
     * @param int                $access
     * @return string
     */
    private function determineDestinationPath(Component_Instance $instance, int $access): string
    {
        $hostFolderId = $instance->getIdentifier();
        $service      = UserAccess::SERVICE_HOMEPAGE;
        return "*.notify.service.$service.$hostFolderId.$access.#";
    }
}
