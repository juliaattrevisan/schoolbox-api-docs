<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Dropbox;


use Database;
use PDOStatement;
use Schoolbox\Config\Loader;
use Schoolbox\Resource\Storage\MigrationPattern;

class Migration implements MigrationPattern
{

    /** @var Database */
    protected $db;

    /** @var PDOStatement */
    protected $stmtHash;

    /** @var PDOStatement */
    protected $stmtFlag;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->conf = new Loader();

        $this->stmtHash = $this->db->prepare("UPDATE dropbox SET hash = :hash WHERE id = :id");
        $this->stmtFlag = $this->db->prepare("UPDATE dropbox SET deleted = 1 AND deleted_at = '1999-12-31 23:59:59' WHERE id = ?");
    }

    /**
     * @return string
     */
    public function getFileDir()
    {
        return $this->conf->get('dropbox_dir');
    }



    /**
     * @return PDOStatement
     */
    public function getFetchStatement()
    {
        return $this->db->query("
            SELECT id, realname FROM dropbox WHERE deleted = 0 and hash = ''
        ");
    }


    /**
     * @param int    $fileId
     * @param string $hash
     * @return void
     */
    public function injectHash($fileId, $hash)
    {
        $this->stmtHash->execute([
            'id'   => $fileId,
            'hash' => $hash
        ]);
    }


    /**
     * @param int $id
     * @return void
     */
    public function flagAsDeleted($id)
    {
        $this->stmtFlag->execute([ $id ]);
    }


    /**
     * @param array $row
     * @return int
     */
    public function readId(array $row)
    {
        return $row['id'];
    }


    /**
     * @param array $row
     * @return string
     */
    public function readFilename(array $row)
    {
        return $row['realname'];
    }

    /**
     * Dropbox has no queue
     *
     * @return null
     */
    public function getQueueType()
    {
        return null;
    }
} 