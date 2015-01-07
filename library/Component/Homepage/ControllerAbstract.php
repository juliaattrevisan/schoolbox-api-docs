<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Component_Homepage_ControllerAbstract extends Component_ControllerAbstract {

    public function setup(array $context, array $config) {
        parent::setup($context, $config);
        $g = new Group();
        // Managing permissions for alternate sources:
        // alt-source permission overrides homepage perm.
        if (!empty($config['src']['value'])) {
            $sourceId = $config['src']['value'];
        } else {
            $sourceId = $context['id'];
        }
        $this->permission = min($g->getPermission($sourceId), $this->getMaximumPermission());
        $this->sitepage->setVar('ID', $context['id']);

        $this->hook();
    }

    /**
     * @param string $templateName
     * @param string $templatePath
     * @return string
     */
    protected function getTemplate ($templateName = '', $templatePath = '') {

        if ( empty($templatePath) ) {
            $templatePath = "component/homepage";
        }

        return parent::getTemplate($templateName, $templatePath);
    }


}
