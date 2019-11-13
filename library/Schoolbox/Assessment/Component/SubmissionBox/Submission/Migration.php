<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Submission;


use Database;
use PDOStatement;
use Schoolbox\Config\Loader;
use Schoolbox\Resource\Storage\MigrationPattern;
use Schoolbox\Video\QueueMapper;

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
        // TODO determine if this class is still even needed
        $this->conf = new Loader();

        $this->stmtHash = $this->db->prepare("UPDATE student_submission SET hash = :hash, realname = :name WHERE id = :id");
        $this->stmtBody = $this->db->prepare("SELECT body FROM student_submission WHERE mime_type = 'text/html' AND filesize IS NULL and id = :id");
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
     * @param string $newFilename
     * @return void
     */
    public function injectHash($fileId, $hash, $newFilename)
    {
        $this->stmtHash->execute([
            'id'   => $fileId,
            'hash' => $hash,
            'name' => $newFilename
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
     * Hey! Know what?! If this is an online/inline submission, we're gonna convert that to a FILE and then HASH THAT!
     *
     * @param array $row
     * @return string
     */
    public function readFilename(array $row)
    {
        $filename = $row['realname'];

        // push "inline submissions" out into the filesystem
        if ($filename == 'Online Submission') {
            $this->stmtBody->execute([ 'id' => $row['id'] ]);

            if ($body = $this->stmtBody->fetchColumn()) {
                file_put_contents($this->getFileDir() ."/" . $row['id'] . ".dat", $body);
            }

            return 'online-submission.html';
        }

        return $filename;
    }

    public function getQueueType() {
        return QueueMapper::FILE_TYPE_SUBMISSION;
    }
}
