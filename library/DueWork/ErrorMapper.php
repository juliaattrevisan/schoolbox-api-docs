<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/**
 * @method DueWork_Error findOneByColumn($columnName, $columnValue, $order = array(), $options = array())
 */
class DueWork_ErrorMapper extends Schoolbox\Mapper {

	protected function mapping () {
		$this->hasTable('submission_errors');
		$this->hasModel(DueWork_Error::class);
		$this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('code', self::TYPE_STRING);
        $this->uses('display_as', self::TYPE_STRING);
        $this->uses('normalise_as', self::TYPE_DECIMAL);
        $this->uses('is_default_for_new_submissions', self::TYPE_BOOLEAN);
	}

	/**
	 * Convenience method for finding by code
	 * @param $code
	 * @return \Schoolbox\Collection
	 */
	public function findByCode($code) {
		return parent::findByColumn('code', $code);
	}

    /**
     * Gets the default state for new submissions.
     *
     * @return DueWork_Error
     * @throws Schoolbox\Mapper\Exception if none was found
     */
    public function findDefaultForNewSubmissions()
    {
        return $this->findOneByColumn(
            'is_default_for_new_submissions',
            true,
            array('id' => 'asc')
        );
    }

	public function listDisplayValues() {
		$errs = $this->findAll();
		$disp = array();
		foreach($errs as $e) {
			/* @var $e DueWork_Error */
			$disp[] = $e->getDisplayAs();
		}
		return $disp;
	}

}
