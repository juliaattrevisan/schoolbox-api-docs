<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Component_Config_Abstract {

    protected $cat;

    protected $filterFields = null;
    protected $sortFields = null;

    /**
     * Does this config option show on templates? Most do, but some don't (e.g.
     * the source selector)
     *
     * @var bool
     */
    protected $is_shown_on_template = true;

    public function __construct($category = array(), $fields = null) {
        $this->cat = $category;

        if(isset($fields['sort'])) {
            $this->sortFields = $fields['sort'];
        }
        if(isset($fields['filter'])) {
            $this->filterFields = $fields['filter'];
        }
    }

    /**
     * @param string $attr
     * @return mixed|bool
     */
    protected function getCategory($attr = '') {
        if($attr) {
            if(isset($this->cat[$attr])) {
                return $this->cat[$attr];
            }
            return false;
        }
        return $this->cat;
    }

    /**
     * Converts value from a form into a value that can be stored in a database.
     *
     * @param  mixed                        $value
     * @param  Component_ControllerAbstract $controller
     * @return mixed
     */
    public function dehydrate($value, Component_ControllerAbstract $controller) {
        return $value;
    }

    /**
     * Is this config option shown on templates?
     *
     * @return bool
     */
    public function isShownOnTemplate() {
        return $this->is_shown_on_template;
    }

    /**
     * Renders this configuration into a SitePage.
     *
     * @param SitePage                     $page        The SitePage to render into
     * @param string                       $key         I don't think this is used
     * @param string                       $keyblock    The block the config gets rendered into.
     *                                                  If a config template has sub-blocks, then
     *                                                  they are set into this block.
     *                                                  Is always set to the same value as $key.
     * @param mixed                        $configValue The value of this config
     * @param Component_ControllerAbstract $component   The component this config is part of
     */
    abstract public function parse(
        SitePage $page,
        $key,
        $keyblock,
        $configValue,
        Component_ControllerAbstract $component = null
    );
}
