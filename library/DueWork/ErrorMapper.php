<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

/**
 * @method DueWork_Error findOneByColumn($columnName, $columnValue, $order = array(), $options = array())
 */
class DueWork_ErrorMapper extends Schoolbox\Mapper
{

    protected function mapping()
    {
        $this->hasTable('submission_errors');
        $this->hasModel(DueWork_Error::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('code', self::TYPE_STRING);
        $this->uses('display_as', self::TYPE_STRING);
        $this->uses('normalise_as', self::TYPE_DECIMAL);
    }

    /**
     * Convenience method for finding by code
     * @param $code
     * @return \Schoolbox\Collection
     */
    public function findByCode($code)
    {
        return parent::findByColumn('code', $code);
    }


    /**
     * Returns an list of the options
     * @return array
     */
    public function listDisplayValues()
    {
        $errs = $this->findAll();
        $disp = array();
        foreach ($errs as $e) {
            /* @var $e DueWork_Error */
            $disp[] = $e->getDisplayAs();
        }
        return $disp;
    }

}
