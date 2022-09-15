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

    private Component_DefinitionMapper $componentDefinitionMapper;

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
            'setComponentDefinitionMapper'       => $mf->get(Component_DefinitionMapper::class),
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
     * @param Component_DefinitionMapper $componentDefinitionMapper
     * @return void
     */
    public function setComponentDefinitionMapper(Component_DefinitionMapper $componentDefinitionMapper)
    {
        $this->componentDefinitionMapper = $componentDefinitionMapper;
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

        // if not src, do not trigger
        $definitions = $this->componentConfigDefinitionMapper->findIdsByColumn('key', 'src');
        if (!in_array($model->getConfigDefinitionId(), $definitions, false)) {
            return;
        }

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
     * Rebuild the subscription cache if required.
     *
     * The subscription cache needs to be rebuilt if:
     * * the component has an alt source
     * * the component's definition includes a notification namespace
     *
     * @param Component_Config $model
     * @param User|null        $effector
     * @return void
     * @throws \Schoolbox\Mapper\Exception
     */
    public function notifySystemReload(Component_Config $model, User $effector = null)
    {
        $instance = $this->componentInstanceMapper->findById($model->getInstanceId());

        // ensure definition hydrated
        $instance->setDefinition($this->componentDefinitionMapper->findById($instance->getDefinitionId()));

        $this->subscriptionCache->cacheRedirects(INSTANCE_ID);
    }
}
