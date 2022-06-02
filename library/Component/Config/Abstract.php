<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Resource\Folder\Model as Folder;

abstract class Component_Config_Abstract
{

    public const KEY_SHOW_COMPONENT_TITLE = 'showComponentTitle';
    public const KEY_COLLAPSE = 'collapse';

    /**
     * Where this Config's Component resides
     *
     * @var Folder|Eportfolio_Page_Instance|null
     */
    private $context;

    // Defined on respective Component Controller, and passed in
    // via Component/ControllerAbstract::renderConfigElements()
    private $filterFields = null;
    private $sortFields = null;

    /**
     * Does this config option show on templates? Most do, but some don't (e.g.
     * the source selector)
     *
     * @var bool
     */
    protected $is_shown_on_template = true;

    /**
     * @param Folder|Eportfolio_Page_Instance|null $context
     * @param array|null $fields
     */
    public function __construct($context, $fields = null)
    {
        $this->context = $context;

        if (isset($fields['sort'])) {
            $this->sortFields = $fields['sort'];
        }
        if (isset($fields['filter'])) {
            $this->filterFields = $fields['filter'];
        }
    }


    /**
     * Converts value from a form into a value that can be stored in a database.
     *
     * @param  mixed $value
     * @param  Component_ControllerAbstract $controller
     * @return mixed
     */
    public function dehydrate($value, Component_ControllerAbstract $controller)
    {
        return $value;
    }

    /**
     * Renders this configuration into a SitePage.
     *
     * @param SitePage $page The SitePage to render into
     * @param string $key I don't think this is used
     * @param string $keyblock The block the config gets rendered into.
     *                                                  If a config template has sub-blocks, then
     *                                                  they are set into this block.
     *                                                  Is always set to the same value as $key.
     * @param string $configValue The value of this config
     * @param Component_ControllerAbstract $component The component this config is part of
     */
    abstract public function parse(
        SitePage $page,
        $key,
        $keyblock,
        $configValue,
        Component_ControllerAbstract $component = null
    );

    /* Getters / Setters
     */

    /**
     * @return Folder|Eportfolio_Page_Instance|null
     */
    protected function getContext()
    {
        return $this->context;
    }

    /**
     * @return mixed
     */
    protected function getFilterFields()
    {
        return $this->filterFields;
    }

    /**
     * @return mixed
     */
    protected function getSortFields()
    {
        return $this->sortFields;
    }

    /**
     * Is this config option shown on templates?
     *
     * @return bool
     */
    public function isShownOnTemplate()
    {
        return $this->is_shown_on_template;
    }
}
