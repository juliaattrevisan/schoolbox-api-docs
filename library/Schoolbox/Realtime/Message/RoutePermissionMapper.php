<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Message;


use DatabaseStatement;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception;

class RoutePermissionMapper extends Mapper {

	/** @var DatabaseStatement[] */
	protected $prepared = array();

	protected function mapping() {
		$this->hasTable('msg_route_perm');
		$this->hasModel('\\Schoolbox\\Realtime\\Message\\RoutePermission');

		$this->hasMap(array(
			'column'     => 'msg_source_id',
			'property'   => 'msgSource',
			'type'       => self::TYPE_MODEL,
			'join'       => 'msg_source',
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'role_id',
			'property'   => 'role',
			'type'       => self::TYPE_MODEL,
			'join'       => 'role',
			'identifier' => true,
		));
		$this->hasMap(array(
			'column'     => 'min_level',
			'property'   => 'minLevel',
			'type'       => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column'     => 'max_level',
			'property'   => 'maxLevel',
			'type'       => self::TYPE_INTEGER,
		));
		$this->hasMap(array(
			'column'     => 'default_level',
			'property'   => 'defaultLevel',
			'type'       => self::TYPE_INTEGER,
		));

		$this->hasJoin('msg_source', array(
			'mapper'    => '\\Schoolbox\\Realtime\\Message\\SourceMapper',
			'this_key'  => 'msg_source_id',
			'other_key' => 'id',
		));
		$this->hasJoin('role', array(
			'mapper'    => '\\Schoolbox\\RoleMapper',
			'this_key'  => 'role_id',
			'other_key' => 'id',
		));
	}

	public function findById($msgSourceId, $roleId) {
		return parent::findOneByColumnList(array(
			'msg_source_id' => $msgSourceId,
			'role_id'       => $roleId
		));
	}

	public function findRouteLevels($topic, $roleId, $userId = 0) {
		$stmt = $this->prepare("
			SELECT
				ns.slug, src.key,
				perm.min_level, perm.default_level, reroute.outgoing_level, perm.max_level
			FROM msg_source src
			LEFT JOIN msg_namespace ns ON src.namespace = ns.id
			LEFT JOIN msg_route_perm perm ON src.id = perm.msg_source_id AND perm.role_id = :role_id
			LEFT JOIN msg_reroute reroute ON src.id = reroute.msg_source_id AND reroute.user_id = :user_id
			WHERE role_id IS NOT NULL
		");

		$stmt->execute(array(
			'role_id' => $roleId,
			'user_id' => $userId
		));

		$result = array();
		$topic = explode('.', $topic);

		while($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
			$test = array($r['slug'], $r['key']);
			if(!count(array_diff($test, $topic))) {
				$result[] = $r;
			}
		}

		return $result;

	}

	/**
	 * Transparently cache prepared statements rather than creating new ones
	 *
	 * @param $queryString
	 * @return DatabaseStatement
	 */
	public function prepare($queryString) {
		$hash = sha1($queryString);
		if(empty($this->prepared[$hash])) {
			$this->prepared[$hash] = $this->database->prepare($queryString);
		}
		return $this->prepared[$hash];
	}


}