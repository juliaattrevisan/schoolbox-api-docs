<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
 
class Component_Instance extends Model {
    public $id;

    public $definitionId;

    public $definition;

    public $module;

    public $identifier;

    public $position;

    public $sequence;

    public $config;

    public function getConfigArray($compat = true) {

        if(isset($this->config)) {

            // dead index, this is actually not a config
            if(count($this->config) == 1 && isset($this->config[''])) {
                return array();
            }

            $output = array();
            foreach($this->config as $opt) {
                $key = $compat ? $this->compatMap($opt['key']) : $opt['key'];
                $val = isset($opt['value']) ? $opt['value'] : $opt['defaultValue'];
                $output[$key] = array(
                    'label' => $opt['label'],
                    'value' => $val,
                );
            }
            return $output;
        }
        return array();
    }

    /**
     * Converts new keys to old keys to preserve integrity with old components
     * @param $key
     * @return mixed
     */
    protected function compatMap($key) {
        $map = array(
            'limit'  => 'size',
            'title'  => 'componentname',
        );
        if(isset($map[$key])) {
            return $map[$key];
        }
        return $key;
    }
}