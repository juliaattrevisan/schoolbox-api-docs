<?php

/**
 * Class Component_Config_EventName
 *
 * Searches for an event name in Geosnapshots
 */
class Component_Config_EventName extends Component_Config_Abstract
{
    /**
     * Parse
     *
     * @param SitePage                          $page
     * @param string                            $key
     * @param string                            $keyblock
     * @param mixed                             $configValue
     * @param Component_ControllerAbstract|null $component
     * @throws PDOException
     * @throws \Schoolbox\Mapper\Exception
     */
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        $page->setVar([
            'COMPONENT_INSTANCE_ID' => $component->getInstance()->getId(),
            'COMPONENT_CONFIG_EVENT_NAME' => $configValue
        ]);
    }
}