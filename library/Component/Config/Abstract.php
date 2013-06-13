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
     * Is this config option shown on templates?
     *
     * @return bool
     */
    public function isShownOnTemplate() {
        return $this->is_shown_on_template;
    }

    public abstract function parse(SitePage $page, $key, $keyblock, $configValue);
}
