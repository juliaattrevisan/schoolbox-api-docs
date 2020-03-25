<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource;


use PDO;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class LogMapper extends Mapper {

    const ACTION_CREATE = 'create';
    const ACTION_READ   = 'access';
    const ACTION_UPDATE = 'modify';
    const ACTION_MOVE   = 'move';
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
        $this->hasModel(Log::class);

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
            'mapper'    => '\\Schoolbox\\Resource\\File\\Mapper',
            'this_key'  => 'objectId',
            'other_key' => 'id'
        ));

        $this->hasJoin('cat', array(
            'mapper'    => '\\Schoolbox\\Resource\\Folder\\Mapper',
            'this_key'  => 'objectId',
            'other_key' => 'id'
        ));

        $this->hasJoin('text', array(
            'mapper'    => \Schoolbox\Resource\Textbox\Mapper::class,
            'this_key'  => 'objectId',
            'other_key' => 'textBoxID'
        ));

    }

    /**
     * Return recent log entries
     *
     * @param int $limit
     *
     * @return array
     */
    public function findRecent(int $limit = 30): array
    {
        $stmt = $this->database->prepare
        ("
            (SELECT `date` occurred, login, ip, title, firstname, lastname FROM login_log LEFT JOIN `user` ON `user`.`username`=login_log.username AND `user`.`deleted_at` IS NULL ORDER BY DATE DESC LIMIT 0, {$limit})
            UNION
            (SELECT `time` occurred, `action`, object, title, firstname, lastname FROM cmsLog LEFT JOIN `user` ON `user`.id=`cmsLog`.`user` AND `user`.`deleted_at` IS NULL ORDER BY `time` DESC LIMIT 0, {$limit})
            ORDER BY occurred DESC LIMIT 0,{$limit}
        ");

        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Return a collection of log items appropriate for aggregation
     *
     * @param     $context
     * @param     $filter
     * @param     $limit
     * @param int $offset
     * @param int $time
     *
     * @return Collection|Log[]
     * @throws Mapper\Exception
     */
    public function findForFeed($context, $filter, $limit, $offset = 0, $time = 0) {
        $time = date('Y-m-d H:i:s', $time ?: time());
        $limit = (int)$limit;
        $offset = (int)$offset;

        $joins = array(
            self::KIND_LINK   => "
                INNER JOIN {$this->getJoinSql('link', 'log', 'l', true)}
                AND object = 'link'
                AND l.category = :ctxt
            ",
            self::KIND_FILE   => "
                INNER JOIN {$this->getJoinSql('file', 'log', 'f', true)}
                AND object = 'file'
                AND f.category = :ctxt
            ",
            self::KIND_FOLDER => "
                INNER JOIN {$this->getJoinSql('cat',  'log', 'c', true)}
                AND object = 'folder'
                AND c.parent = :ctxt
            ",
            self::KIND_CONTENT => "
                INNER JOIN {$this->getJoinSql('text', 'log', 't', true)}
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

        // are we filtering? or just grabbing all the things?
        $selectSql = $selects[$filter];
        $joinSql = $joins[$filter];

        /** @var UserMapper $userMapper */
        $userMapper = $this->getJoinMapper('user');

        $stmt = $this->database->prepare("
            SELECT
                {$this->getColumnString('log')},
                {$this->getJoinColumnString('user', 'u')},
                $selectSql
            FROM `{$this->getTable()}` `log`
                LEFT JOIN {$this->getJoinSql('user', 'log', 'u', true)}
                $joinSql
            WHERE log.time <= :time
            ORDER BY log.time DESC
            LIMIT $offset, $limit
        ");

        $stmt->execute(array(
            'ctxt' => $context,
            'time' => $time
        ));

        $collection = new Collection();

        while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $user = $this->hydrateJoin($r, 'user', 'u');

            /* Special case for logs:
             * For the moment, we do not enforce referential integrity.
             * If the user no longer exists, create an empty user object
             */
            $user = $user ?: $userMapper->getUnknownUser();

            $collection->add($this->hydrate($r, 'log')->setUser($user));
        }

        return $collection;
    }


    /**
     * @param Link|File\Model|Folder\Model|Textbox\Model $object
     * @param $user
     *
     * @throws Mapper\Exception
     */
    public function logCreate($object, $user)
    {
        $this->putLog(self::ACTION_CREATE, $object, $user);
    }

    /**
     * @param Link|File\Model|Folder\Model|Textbox\Model $object
     * @param $user
     *
     * @throws Mapper\Exception
     */
    public function logRead($object, $user)
    {
        $this->putLog(self::ACTION_READ, $object, $user);
    }

    /**
     * @param Link|File\Model|Folder\Model|Textbox\Model $object
     * @param $user
     *
     * @throws Mapper\Exception
     */
    public function logUpdate($object, $user)
    {
        $this->putLog(self::ACTION_UPDATE, $object, $user);
    }

    /**
     * @param Link|File\Model|Folder\Model|Textbox\Model $object
     * @param $user
     *
     * @throws Mapper\Exception
     */
    public function logDelete($object, $user)
    {
        $this->putLog(self::ACTION_DELETE, $object, $user);
    }

    /**
     * @param string                                     $action
     * @param Link|File\Model|Folder\Model|Textbox\Model $object
     * @param User                                       $user
     * @throws Mapper\Exception
     */
    protected function putLog($action, $object, $user)
    {
        /** @var Log $log */
        $log = $this->createModel();
        $log->setAction($action)->setObject($object)->setUser($user);
        $this->create($log);
    }


    /**
     * No notifications.
     *
     * @param object $object
     * @param string $verb
     * @param object $previous
     * @return bool
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        return true;
    }

}
