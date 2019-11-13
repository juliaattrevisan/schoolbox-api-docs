<?php
/**
 * Submission return migration plan.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;

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

    /** @var PDOStatement */
    protected $stmtFlag;

    /**
     * Constructor
     */
    function __construct()
    {
        $this->db       = Database::getInstance();
        // TODO determine if this class is still even needed
        $this->conf     = new Loader();
        $this->stmtHash = $this->db->prepare("UPDATE submission_return SET hash = :hash, filename = :name WHERE id = :id");
        $this->stmtFlag = $this->db->prepare("UPDATE submission_return SET deleted = TRUE WHERE id = ?");
    }

    /**
     * @return string
     */
    public function getFileDir()
    {
        return $this->conf->get('returns_dir');
    }

    /**
     * @return PDOStatement
     */
    public function getFetchStatement()
    {
        return $this->db->query("SELECT id, filename FROM submission_return WHERE `hash` IS NULL AND filename != ''");
    }

    /**
     * @param int    $fileId
     * @param string $hash
     * @param string $newFilename
     * @return void
     */
    public function injectHash($fileId, $hash, $newFilename)
    {
        $this->stmtHash->execute(
            [
                'id'   => $fileId,
                'hash' => $hash,
                'name' => $newFilename
            ]
        );
    }

    /**
     * @param int $id
     *
     * @return void
     */
    public function flagAsDeleted($id)
    {
        $this->stmtFlag->execute([ $id ]);
    }

    /**
     * @param array $row
     *
     * @return int
     */
    public function readId(array $row)
    {
        return $row['id'];
    }

    /**
     * @param array $row
     *
     * @return string
     */
    public function readFilename(array $row)
    {
        return $row['filename'];
    }

    public function getQueueType() {
        return null;
    }
}
