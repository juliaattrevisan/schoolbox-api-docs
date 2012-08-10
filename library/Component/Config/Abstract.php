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

    public function __construct(array $category, $fields = null) {
        $this->cat = $category;

        if(isset($fields['sort'])) {
            $this->sortFields = $fields['sort'];
        }
        if(isset($fields['filter'])) {
            $this->filterFields = $fields['filter'];
        }
    }

    protected function getCategory($attr = '') {
        if($attr) {
            if(isset($this->cat[$attr])) {
                return $this->cat[$attr];
            }
            return null;
        }
        return $this->cat;
    }

    public abstract function parse(SitePage $page, $key, $keyblock, $configValue);
}
