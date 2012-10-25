<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class DueWork_ErrorMapper extends Mapper {

    protected function mapping () {
        $this->table = 'submission_errors';

        $this->hasMap(array(
            'column'     => 'id',
            'property'   => 'id',
            'type'       => Mapper::TYPE_INTEGER,
            'identifier' => true
        ));

        $this->hasMap(array(
            'column'     => 'code',
            'property'   => 'code',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'display_as',
            'property'   => 'displayAs',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        $this->hasMap(array(
            'column'     => 'normalise_as',
            'property'   => 'normaliseAs',
            'type'       => Mapper::TYPE_INTEGER,
        ));

        parent::mapping();
    }

    /**
     * Convenience method for finding by code
     * @param $code
     * @return Collection
     */
    public function findByCode($code) {
        return parent::findByColumn('code', $code);
    }

    public function listDisplayValues() {
        $errs = $this->findAll();
        $disp = array();
        foreach($errs as $e) {
            $disp[] = $e->displayAs;
        }
        return $disp;
    }

}
