<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

/**
 * @method DueWork_Error findOneByColumn($columnName, $columnValue, array $options = [])
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
        $this->uses('sequence', self::TYPE_INTEGER);
        $this->uses('updated_by', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
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

    /** @inheritDoc */
    protected function getDefaultOrderByParams(): array
    {
        return ['sequence' => 'ASC'];
    }
}
