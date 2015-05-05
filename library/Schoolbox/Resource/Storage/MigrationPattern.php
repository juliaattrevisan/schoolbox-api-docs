<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Storage;


use PDOStatement;

/**
 * Interface MigrationPattern
 *
 * Implementing classes aid in transition from brokenFS to OTFS for their particular domain.
 * @see Migrator for how it's used
 *
 * @package Schoolbox\Resource\Storage
 */
interface MigrationPattern
{

    /**
     * @return string
     */
    public function getFileDir();


    /**
     * @return PDOStatement
     */
    public function getFetchStatement();


    /**
     * @param int    $fileId
     * @param string $hash
     * @param string $newFilename
     * @return void
     */
    public function injectHash($fileId, $hash, $newFilename);


    /**
     * @param int $id
     * @return void
     */
    public function flagAsDeleted($id);


    /**
     * @param array $row
     * @return int
     */
    public function readId(array $row);


    /**
     * @param array $row
     * @return string
     */
    public function readFilename(array $row);

    /**
     * Each storage type that has a queue has a corresponding type
     * (in video_queue.type) - this returns that type so that the queue can be
     * migrated.
     *
     * @return string A Schoolbox\Video\QueueMapper::FILE_TYPE_* constant,
     *                or null if it has no queue.
     */
    public function getQueueType();
}