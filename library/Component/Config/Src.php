<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Component_Config_Src extends Component_Config_Abstract
{

    /**
     * Strips /homepage/ prefix from $value if it's there.
     *
     * @param  array                        $value
     * @param  Component_ControllerAbstract $controller
     * @return string
     */
    public function dehydrate($value, Component_ControllerAbstract $controller)
    {
        preg_match("#[^0-9]*([0-9]*)#i", $value, $matches);
        if ($matches) {
            $value = $matches[1];
        }
        return $value;
    }

    /**
     * Parse
     *
     * @param SitePage $page
     * @param string $key
     * @param string $keyblock
     * @param mixed $configValue
     * @param Component_ControllerAbstract $component
     */
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        $vars = array(
            'COMPONENT_CONFIG_SRC_ID'   => '',
            'COMPONENT_CONFIG_SRC_NAME' => 'Local',
            'COMPONENT_CONFIG_SRC_CID'  => '0'
        );
        if ($configValue) {
            try {
                $folderMapper = new \Schoolbox\Resource\Folder\Mapper();
                $folder       = $folderMapper->findCheaplyById($configValue);
                $vars         = array(
                    'COMPONENT_CONFIG_SRC_ID'   => '/homepage/' . $folder->getId(),
                    'COMPONENT_CONFIG_SRC_NAME' => Scrub::text($folder->getName()),
                    'COMPONENT_CONFIG_SRC_CID'  => $folder->getId()
                );
            } catch (\Exception $e) {
                // noop - probably a deleted folder or something
            }
        }
        $page->setVar($vars);
    }
}
