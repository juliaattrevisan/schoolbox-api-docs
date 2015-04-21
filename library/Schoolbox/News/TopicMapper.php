<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\News;


use Schoolbox\Mapper;

class TopicMapper extends Mapper {

	protected function mapping() {
		$this->hasTable('newsTopic');
		$this->hasModel('\\Schoolbox\\News\\Topic');

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
	}

}