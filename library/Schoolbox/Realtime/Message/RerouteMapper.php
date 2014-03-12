<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Message;


use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception;

class RerouteMapper extends Mapper {

	protected function mapping() {
		$this->hasTable('msg_reroute');
		$this->hasModel('\\Schoolbox\\Realtime\\Message\\Reroute');

		$this->hasMap(array(
			'column'     => 'relation_id',
			'property'   => 'relationId',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'relation_type',
			'property'   => 'relationType',
			'type'       => self::TYPE_STRING,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'routing_key',
			'property'   => 'routingKey',
			'type'       => self::TYPE_STRING,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'incoming_level',
			'property'   => 'incomingLevel',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'outgoing_level',
			'property'   => 'outgoingLevel',
			'type'       => self::TYPE_INTEGER,
		));

	}


	/**
	 * @override base mapper findById functionality to compensate for multiKey
	 * @param mixed $relationId
	 * @param $relationType
	 * @param $routingKey
	 * @param $incomingLevel
	 * @return Reroute
	 */
	public function findById($relationId, $relationType, $routingKey, $incomingLevel) {
		return parent::findOneByColumnList(array(
			'relation_id'    => $relationId,
			'relation_type'  => $relationType,
			'routing_key'    => $routingKey,
			'incoming_level' => $incomingLevel
		));
	}


}