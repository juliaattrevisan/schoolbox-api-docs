<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource;


use PDO;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\User;

class LogMapper extends Mapper {

	const ACTION_CREATE = 'create';
	const ACTION_READ   = 'access';
	const ACTION_UPDATE = 'modify';
	const ACTION_DELETE = 'delete';

	const KIND_FILE     = 'file';
	const KIND_FOLDER   = 'folder';
	const KIND_LINK     = 'link';
	const KIND_CONTENT  = 'content';
	const KIND_MYFILE   = 'myfile';
	const KIND_MYFOLDER = 'myfolder';

	/**
	 * @return void
	 */
	protected function mapping() {
		$this->hasTable('cmsLog');
		$this->hasModel('\\Schoolbox\Resource\\Log');

		$this->hasMap(array(
			'column'     => 'id',
			'property'   => 'id',
			'type'       => self::TYPE_INTEGER,
			'identifier' => true
		));

		$this->hasMap(array(
			'column'     => 'object',
			'property'   => 'objectKind',
			'type'       => self::TYPE_STRING
		));

		$this->hasMap(array(
			'column'     => 'objectId',
			'property'   => 'objectId',
			'type'       => self::TYPE_INTEGER
		));

		$this->hasMap(array(
			'column'     => 'action',
			'property'   => 'action',
			'type'       => self::TYPE_STRING
		));

		$this->hasMap(array(
			'column'     => 'user',
			'property'   => 'user',
			'type'       => self::TYPE_MODEL,
			'join'       => 'user'
		));

		$this->hasMap(array(
			'column'            => 'time',
			'property'          => 'timestamp',
			'type'              => self::TYPE_DATETIME,
			'created_timestamp' => true
		));

		$this->hasJoin('user', array(
			'mapper'    => '\\Schoolbox\\UserMapper',
			'this_key'  => 'user',
			'other_key' => 'id'
		));

		// unmapped joins

		$this->hasJoin('link', array(
			'mapper'    => '\\Schoolbox\\Resource\\LinkMapper',
			'this_key'  => 'objectId',
			'other_key' => 'id'
		));

		$this->hasJoin('file', array(
			'mapper'    => '\\Schoolbox\\Resource\\FileMapper',
			'this_key'  => 'objectId',
			'other_key' => 'id'
		));

		$this->hasJoin('cat', array(
			'mapper'    => '\\Schoolbox\\Resource\\CategoryMapper',
			'this_key'  => 'objectId',
			'other_key' => 'id'
		));

		$this->hasJoin('text', array(
			'mapper'    => \Schoolbox\Textbox\Mapper::class,
			'this_key'  => 'objectId',
			'other_key' => 'textBoxID'
		));

	}

	/**
	 * Return a collection of log items appropriate for aggregation
	 *
	 * @param int $context
	 * @param int $limit
	 * @param int $offset
	 * @param string $filter
	 * @param int $time
	 * @return Collection|Log[]
	 */
	public function findForFeed($context, $filter, $limit, $offset = 0, $time = 0) {
		$time = date('Y-m-d H:i:s', $time ?: time());
		$limit = (int)$limit;
		$offset = (int)$offset;

		$joins = array(
			self::KIND_LINK   => "
				LEFT JOIN {$this->getJoinSql('link', 'log', 'l')}
				AND object = 'link'
				AND l.category = :ctxt
			",
			self::KIND_FILE   => "
				LEFT JOIN {$this->getJoinSql('file', 'log', 'f')}
				AND object = 'file'
				AND f.category = :ctxt
			",
			self::KIND_FOLDER => "
				LEFT JOIN {$this->getJoinSql('cat',  'log', 'c')}
				AND object = 'folder'
				AND c.parent = :ctxt
			",
			self::KIND_CONTENT => "
				LEFT JOIN {$this->getJoinSql('text', 'log', 't')}
				AND object = 'content'
				AND t.categoryID = :ctxt
			"
		);

		$selects = array(
			self::KIND_LINK    => $this->getJoinMapper('link')->getColumnString('l'),
			self::KIND_FILE    => $this->getJoinMapper('file')->getColumnString('f'),
			self::KIND_FOLDER  => $this->getJoinMapper('cat')->getColumnString('c'),
			self::KIND_CONTENT => $this->getJoinMapper('text')->getColumnString('t'),
		);

		$wheres = array(
			self::KIND_LINK    => "object = 'link'   AND l.id IS NOT NULL",
			self::KIND_FILE    => "object = 'file'   AND f.id IS NOT NULL",
			self::KIND_FOLDER  => "object = 'folder' AND c.id IS NOT NULL",
			self::KIND_CONTENT => "object = 'content' AND t.textBoxID IS NOT NULL"
		);

		// are we filtering? or just grabbing all the things?
		$selectSql = $selects[$filter];
		$joinSql = $joins[$filter];
		$whereSql = $wheres[$filter];

		$stmt = $this->database->prepare("
			SELECT
				{$this->getColumnString('log')},
				{$this->getJoinColumnString('user', 'u')},
				$selectSql
			FROM `{$this->getTable()}` `log`
				LEFT JOIN {$this->getJoinSql('user', 'log', 'u')}
				$joinSql
			WHERE log.time <= :time
			AND $whereSql
			ORDER BY log.time DESC
			LIMIT $offset, $limit
		");

		$stmt->execute(array(
			'ctxt' => $context,
			'time' => $time
		));

		$collection = new Collection();

		while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
			/** @var Log $log */
			$collection->add($this->hydrate($r, 'log')->setUser($this->hydrateJoin($r, 'user', 'u')));
		}

		return $collection;
	}


    /**
     * @param Link|File\Model|Folder\Model|Textbox $object
     * @param User                                 $user
     */
    public function logCreate($object, $user)
    {
        $this->putLog(self::ACTION_CREATE, $object, $user);
    }

    /**
     * @param Link|File\Model|Folder\Model|Textbox $object
     * @param User                                 $user
     */
    public function logRead($object, $user)
    {
        $this->putLog(self::ACTION_READ, $object, $user);
    }

    /**
     * @param Link|File\Model|Folder\Model|Textbox $object
     * @param User                                 $user
     */
    public function logUpdate($object, $user)
    {
        $this->putLog(self::ACTION_UPDATE, $object, $user);
    }

    /**
     * @param Link|File\Model|Folder\Model|Textbox $object
     * @param User                                 $user
     */
    public function logDelete($object, $user)
    {
        $this->putLog(self::ACTION_DELETE, $object, $user);
    }

    /**
     * @param string                               $action
     * @param Link|File\Model|Folder\Model|Textbox $object
     * @param User                                 $user
     * @throws Mapper\Exception
     */
    protected function putLog($action, $object, $user)
    {
        /** @var Log $log */
        $log = $this->createModel();
        $log->setAction($action)->setObject($object)->setUser($user);
        $this->create($log);
    }

} 