<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\MapperFactory;
use Schoolbox\Realtime\Routing\Cache\Redis\WriteCache;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\User;

class Component_ConfigNotifier extends AbstractModuleNotifier
{
    const TRIGGER_CREATED = 'created';
    const TRIGGER_UPDATED = 'updated';
    const TRIGGER_DELETED = 'deleted';
    const TRIGGER_RELOAD  = 'reload';

    private Component_InstanceMapper $componentInstanceMapper;

    private Component_ConfigDefinitionMapper $componentConfigDefinitionMapper;

    private WriteCache $subscriptionCache;

    /**
     * @return string
     */
    protected function getSlug()
    {
        return 'component_config';
    }

    /**
     * @inheritDoc
     */
    public static function getSetters(\Psr\Container\ContainerInterface $di): array
    {
        /** @var MapperFactory $mf */
        $mf = $di->get('mapperFactory');

        return [
            'setComponentInstanceMapper'         => $mf->get(Component_InstanceMapper::class),
            'setComponentConfigDefinitionMapper' => $mf->get(Component_ConfigDefinitionMapper::class),
            'setSubscriptionCache'               => $di->get('subscriptionCache'),
        ];
    }

    /**
     * @param WriteCache $subscriptionCache
     * @return void
     */
    public function setSubscriptionCache(WriteCache $subscriptionCache)
    {
        $this->subscriptionCache = $subscriptionCache;
    }

    /**
     * @param Component_InstanceMapper $componentInstanceMapper
     * @return void
     */
    public function setComponentInstanceMapper(Component_InstanceMapper $componentInstanceMapper)
    {
        $this->componentInstanceMapper = $componentInstanceMapper;
    }

    /**
     * @param Component_ConfigDefinitionMapper $componentConfigDefinitionMapper
     * @return void
     */
    public function setComponentConfigDefinitionMapper(Component_ConfigDefinitionMapper $componentConfigDefinitionMapper)
    {
        $this->componentConfigDefinitionMapper = $componentConfigDefinitionMapper;
    }

    /**
     * @param TriggerBundleInterface $bundle
     * @return void
     */
    public function bundleTriggers($bundle): void
    {
        /** @var Component_Config $model */
        $model = $bundle->getEvent()->getObject();

        $diff = $bundle->getEvent()->getDiff();
        if (\Schoolbox\Mapper::METHOD_UPDATE === $bundle->getMethod() && !isset($diff['value'])) {
            // simpler way to get rid of non src config
            return;
        }

        // * The subscription cache needs to be rebuilt if:
        // * * the component has an alt source
        // * * the component's definition includes a notification namespace
        $definitions = $this->componentConfigDefinitionMapper->findIdsByColumn('key', 'src');
        if (!in_array($model->getConfigDefinitionId(), $definitions, false)) {
            return;
        }
        try {
            $instance = $this->componentInstanceMapper->findById($model->getInstanceId());
            if (!$instance->getDefinition()->getNamespace()) {
                return;
            }
        } catch (\Schoolbox\Mapper\Exception $e) {
            $this->log->debug("Failed to find instance: {$model->getInstanceId()} " . $e->getMessage());
            return;
        }

        // Detach existing reload request TODO: disabling this for now
        // $bundle->detach(self::TRIGGER_RELOAD);
        // Trigger refreshing subscription cache after 10 seconds
        $now = (\Alaress_Date_Format::now())->addSeconds(10);
        $bundle->attach(self::TRIGGER_RELOAD, $now->getUnixTimeStamp());
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
            [$this, "notifySystem$label"],
        ];
    }


    /**
     * Rebuild the subscription cache.
     *
     * @param Component_Config $model
     * @param User|null        $effector
     * @return void
     */
    public function notifySystemReload(Component_Config $model, User $effector = null)
    {
        $this->subscriptionCache->cacheRedirects(INSTANCE_ID);
    }
}
