<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Submission;


use Database;
use PDOStatement;
use Schoolbox\Config\Loader;
use Schoolbox\Resource\Storage\MigrationPattern;

class Migration implements MigrationPattern
{
    /** @var Database */
    protected $db;

    /** @var Loader */
    protected $conf;

    /** @var PDOStatement */
    protected $stmtHash;


    /**
     */
    function __construct()
    {
        $this->db   = Database::getInstance();
        $this->conf = new Loader();

        $this->stmtHash = $this->db->prepare("UPDATE student_submission SET hash = :hash WHERE id = :id");
    }


    /**
     * @return string
     */
    public function getFileDir()
    {
        return $this->conf->get('submission_dir');
    }


    /**
     * @return PDOStatement
     */
    public function getFetchStatement()
    {
        return $this->db->query("
          SELECT id, realname FROM student_submission WHERE hash = ''
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
        // noop
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

} 