<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Topic;

class Mapper extends \Schoolbox\Mapper {

	protected function mapping() {
		$this->hasTable('newsTopic');
		$this->hasModel(Model::class);

		$this->hasMap(array(
			'column' => 'newsTopicID',
			'property' => 'id',
			'type' => Mapper::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column' => 'name',
			'property' => 'name',
			'type' => Mapper::TYPE_STRING,
		));

        $this->hasMap(array(
                'column' => 'sequence',
                'property' => 'sequence',
                'type' => Mapper::TYPE_STRING,
            ));

        $this->uses('updated_by', self::TYPE_INTEGER);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);

	}

    protected function getDefaultOrderByParams(): array
    {
        return ['sequence' => 'ASC'];
    }
}
