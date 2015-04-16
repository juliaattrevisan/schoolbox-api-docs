<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Storage;


use Database;
use PDOStatement;
use Schoolbox\Config\Loader;

class Migration implements MigrationPattern
{
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->conf = new Loader();

        $this->stmtFlag = $this->db->prepare("UPDATE `storage` SET expired_at = '1999-12-31 23:59:59' WHERE storage_id = ?");
    }


    /**
     * @return string
     */
    public function getFileDir()
    {
        return $this->conf->get('storage_dir');
    }


    /**
     * @return PDOStatement
     */
    public function getFetchStatement()
    {
        return $this->db->query("
          SELECT `storage_id`, `filename` FROM `storage` WHERE filename != '' AND expired_at IS NULL
        ");
    }


    /**
     * @param int    $fileId
     * @param string $hash
     * @return void
     */
    public function injectHash($fileId, $hash)
    {
        // noop
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
        return $row['storage_id'];
    }


    /**
     * @param array $row
     * @return string
     */
    public function readFilename(array $row)
    {
        return $row['filename'];
    }

    /**
     * Storage of course has a queue type, but it isn't meant to be migrated.
     *
     * @return null
     */
    public function getQueueType()
    {
        return null;
    }
} 