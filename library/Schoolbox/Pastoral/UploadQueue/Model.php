<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Pastoral\UploadQueue;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Mapper\Exception as MapperException;

class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /** @var int */
    protected $pastoralId;

    /** @var int */
    protected $externalId;

    /** @var int */
    protected $studentExternalId;

    /** @var string */
    protected $hash;

    /** @var string */
    protected $type;

    /** @var string */
    protected $status;

    /** @var string */
    protected $message;

    /**
     * @return int
     */
    public function getPastoralId()
    {
        return $this->pastoralId;
    }

    /**
     * @param int $pastoralId
     * @return self
     */
    public function setPastoralId($pastoralId)
    {
        $this->pastoralId = $pastoralId;
        return $this;
    }

    /**
     * @return int
     */
    public function getExternalId()
    {
        return $this->externalId;
    }

    /**
     * @param int $externalId
     * @return self
     */
    public function setExternalId($externalId)
    {
        $this->externalId = $externalId;
        return $this;
    }

    /**
     * @return int
     */
    public function getStudentExternalId()
    {
        return $this->studentExternalId;
    }

    /**
     * @param int $studentExternalId
     * @return self
     */
    public function setStudentExternalId($studentExternalId)
    {
        $this->studentExternalId = $studentExternalId;
        return $this;
    }

    /**
     * @param string $hash
     * @return self
     */
    public function setHash($hash)
    {
        $this->hash = $hash;
        return $this;
    }

    /**
     * @return string
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * @param string $type
     * @return self
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $status
     * @return self
     */
    public function setStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param string $message
     * @return self
     */
    public function setMessage($message)
    {
        $this->message = $message;
        return $this;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }


    /**
     * Upload this file to Synergetic DocMan
     * @returns boolean
     */
    public function uploadDocman()
    {
        $storageMapper = StorageMapper::getInstance();
        $storageObject = $storageMapper->findByHash($this->getHash());

        // This whole thing is a bit of an ass, unfortunately Synergetic stores its DocMan files in
        // the actual database- which means we need to shove the file into a SQL query as a hex string
        // which is totally inefficient and very slow :(

        // Temporarily increase the memory limit and timeout so we can get the file across to Synergetic

        ini_set('memory_limit', '2048M');
        set_time_limit(0);

        // Save the file into to the database

        $hex = "0x" . bin2hex($storageObject->read());
        $externalId = $this->getExternalId();
        $studentExternalId = $this->getStudentExternalId();
        $date = date("Y-m-d H:i:s");
        $sourceDate = $storageObject->getCreatedAt()->getDatabase();
        $extension = strtoupper($storageObject->getExtension());

        $query = "
            INSERT INTO SynergyOneMedia.dbo.tDocuments
            (
                Description,
                DocumentCreatedByID,
                DocumentModifiedByID,
                Document,
                DocumentCreatedDate,
                DocumentModifiedDate,
                SourceDate,           
                DocumentType
            )
            VALUES
            (
                'Imported from Schoolbox',
                {$externalId},
                {$externalId},
                {$hex},
                '{$date}',
                '{$date}',
                '{$sourceDate}',
                '{$extension}'
            );            
        ";

        $database = \MSSQL::getInstance();
        $database->query($query);
        $documentReferencesSeq = $database->getOne("SELECT @@IDENTITY");

        // Add a document reference to the student so it shows up on their record

        $query =
        "
            INSERT INTO SynergyOneMedia.dbo.DocumentReferences
            (
                ID,
                DocumentReferencesSeq
            )
            VALUES
            (
                {$studentExternalId},
                {$documentReferencesSeq}
            );            
        ";

        $database->query($query);
    }
}
