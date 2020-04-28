<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

/**
 * Collection ID for Twitter component configuration.
 */
class Component_Config_CollectionId extends Component_Config_Abstract
{

    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        $page->setVar('COMPONENT_CONFIG_COLLECTION_ID', $configValue);
    }
}
