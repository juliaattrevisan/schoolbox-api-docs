<?php
/**
 *
 * @author      Damian Sloane <dsloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 */

use \Schoolbox\Pastoral\Pastoral;
use \Schoolbox\Pastoral\Pastoral\Mapper as PastoralMapper;
use \Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Alaress\DebugBar\Instance as DebugBar;

class External_Synergetic_Pastoral implements External_PastoralInterface
{
    /**
     * @var MSSQL
     */
    private $database;

    /**
     * Constructor

     * @throws Exception
     */
    public function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
        if (DebugBar::isEnabled()) {
            $this->getDatabase()->installCollector();
            /** @var \DebugBar\DataCollector\DataCollectorInterface $collector */
            $collector = $this->getDatabase()->getCollector();
            if (!DebugBar::getInstance()->hasCollector($collector->getName())) {
                DebugBar::getInstance()->addCollector($collector);
            }
        }
    }

    /**
     * Returns the pastoral record if found in remote system
     *
     * @param \Schoolbox\Pastoral\Pastoral\Model $pastoral
     * @return array
     */
    public function getPastoral(\Schoolbox\Pastoral\Pastoral\Model $pastoral)
    {
        $query = "SELECT PastoralCareSeq,
                          ID,
                          ClassCode,
                          ItemDate,
                          MethodCode,
                          Details,
                          PrivateDetails,
                          InitiatorCode,
                          TypeCode,
                          CategoryCode,
                          ClassificationCode,
                          IsFollowUpFlag,
                          FollowUpDate,
                          ConfidentialFlag,
                          CreatedDate,
                          CreatedByID,
                          ModifiedDate,
                          ModifiedByID,
                          EmailSentDate,
                          EmailSentByID,
                          DuplicateFromSeq,
                          SubjectClassesSeq
                  FROM tPastoralCare
                  WHERE PastoralCareSeq = " . $this->getPastoralIdentifier($pastoral->getExternalId());

        return $this->getDatabase()->getAll($query);
    }

    /**
     * Returns the external ID used to identify this pastoral record
     *
     * @param \Schoolbox\Pastoral\Pastoral\Model $pastoral
     * @return string
     */
    private function getPastoralIdentifier(\Schoolbox\Pastoral\Pastoral\Model $pastoral)
    {
        return ($pastoral->getExternalId());
    }

    /**
     * Creates a new pastoral record in the external database
     *
     * @param \Schoolbox\Pastoral\Pastoral
     * @param \Schoolbox\Pastoral\Pastoral\Model $pastoral
     * @return bool
     */
    public function createPastoral(\Schoolbox\Pastoral\Pastoral\Model $pastoral)
    {
        // Get the information necessary to create the query

        // External student ID

        $userMapper = new \Schoolbox\UserMapper();
        $id = $userMapper->findById($pastoral->getStudent()->getId())->getExternalId();

        // Set the item date to the post date

        $item_date = "'" . $pastoral->getPostDate() . "'";

        // If the message is marked as private, do not put in public details

        if ($pastoral->getPrivate()) {
            $details = "'" . str_replace("'", "''", $pastoral->getHeading()) . "'";
        } else {
            $details = "'" . str_replace("'", "''", $pastoral->getHeading()) . "\n\n" . str_replace("'", "''", $pastoral->getBodyText()) . "'";
        }

        $private_details = "'" . str_replace("'", "''", $pastoral->getHeading()) . "\n\n" . str_replace("'", "''", $pastoral->getBodyText()) . "'";

        // Pastoral event type code

        $typeMapper = new PastoralTypeMapper();
        $type = $typeMapper->findById($pastoral->getType()->getId());
        $type_code = "'" . $type->getExternalCode() . "'";

        // Created/modified dates

        $created_date = "'" . $pastoral->getPostDate() . "'";
        $created_by_id = $pastoral->getOwner()->getExternalId();

        // If there is no modified date, give it the created date

        if ($pastoral->getModifiedDate() != null) {
            $modified_date = "'" . $pastoral->getModifiedDate() . "'";
            $modified_by_id = $pastoral->getOwner()->getExternalId();
        } else {
            $modified_date = "'" . $pastoral->getPostDate() . "'";
            $modified_by_id = $pastoral->getOwner()->getExternalId();
        }

        // Execute the query

        $query = "INSERT INTO tPastoralCare (
                    ID,
                    ItemDate,
                    Details,
                    PrivateDetails,
                    TypeCode,
                    CreatedDate,
                    CreatedById,
                    ModifiedDate,
                    ModifiedById,
                    ClassCode,
                    MethodCode,
                    EmailSentByID
                ) VALUES (
                    {$id},
                    {$item_date},
                    {$details},
                    {$private_details},
                    {$type_code},
                    {$created_date},
                    {$created_by_id},
                    {$modified_date},
                    {$modified_by_id},
                    '',
                    '',
                    -1
                )";

        $this->getDatabase()->query($query);
        $result = $this->getDatabase()->query("SELECT SCOPE_IDENTITY() AS insert_id");
        $row = mssql_fetch_object($result);

        $pastoralMapper = new PastoralMapper();
        $pastoral->setExternalId($row->insert_id);
        $pastoralMapper->save($pastoral);

        // TODO: Upload attachments to DocMan (SynergyOneMedia)

        /*
        $attachmentMapper = new AttachmentMapper();
        $attachments = $attachmentMapper->findByPastoralId($pastoral->getId());

        foreach ($attachments as $attachment) {

            $resource = Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash($attachment->getHash());
            $content = bin2hex($content);
            $document = "0x".$content;

            $this->getDatabase()->query("INSERT INTO tDocuments (Description, SourceRed, Document) VALUES ('Text', 'Imported from Schoolbox', '{$document}')");
        }
        */

        return $result;
    }


    /**
     * Updates an existing pastoral record
     *
     * @param \Schoolbox\Pastoral\Pastoral\Model $pastoral
     * @return bool
     */
    public function updatePastoral(\Schoolbox\Pastoral\Pastoral\Model $pastoral)
    {
        // Get the information necessary to create the query

        $external_id = $pastoral->getExternalId();

        if (is_int($external_id)) {

            // External student ID

            $userMapper = new \Schoolbox\UserMapper();
            $id = $userMapper->findById($pastoral->getStudent()->getId())->getExternalId();

            // Set the item date to the post date

            $item_date = "'" . date("Y-m-d H:i:s", strtotime($pastoral->getPostDate())) . "'";

            // If the message is marked as private, do not put in public details

            if ($pastoral->getPrivate()) {
                $details = "'" . str_replace("'", "''", $pastoral->getHeading()) . "'";
            } else {
                $details = "'" . str_replace("'", "''", $pastoral->getHeading()) . "\n\n" . str_replace("'", "''", $pastoral->getBodyText()) . "'";
            }

            $private_details = "'" . str_replace("'", "''", $pastoral->getHeading()) . "\n\n" . str_replace("'", "''", $pastoral->getBodyText()) . "'";

            // Pastoral event type code

            $typeMapper = new PastoralTypeMapper();
            $type = $typeMapper->findById($pastoral->getType()->getId());
            $type_code = "'" . $type->getExternalCode() . "'";

            // Created/modified dates

            $created_date = "'" . date("Y-m-d H:i:s", strtotime($pastoral->getPostDate())) . "'";
            $created_by_id = $pastoral->getOwner()->getExternalId();

            // If there is no modified date, give it the created date

            if ($pastoral->getModifiedDate() != null) {
                $modified_date = "'" . date("Y-m-d H:i:s", strtotime($pastoral->getModifiedDate())) . "'";
                $modified_by_id = $pastoral->getOwner()->getExternalId();
            } else {
                $modified_date = "'" . date("Y-m-d H:i:s", strtotime($pastoral->getPostDate())) . "'";
                $modified_by_id = $pastoral->getOwner()->getExternalId();
            }

            // Execute the query

            $query = "UPDATE tPastoralCare SET
                        ID = {$id},
                        ItemDate = {$item_date},
                        Details = {$details},
                        PrivateDetails = {$private_details},
                        TypeCode = {$type_code},
                        CreatedDate = {$created_date},
                        CreatedById = {$created_by_id},
                        ModifiedDate = {$modified_date},
                        ModifiedById = {$modified_by_id},
                        ClassCode = '',
                        MethodCode = '',
                        EmailSentByID = -1
                        WHERE PastoralCareSeq = {$external_id}
            ";

            $result = $this->getDatabase()->query($query);
            return $result;
        }

        // TODO: Upload attachments to DocMan (SynergyOneMedia)

        /*
        $attachmentMapper = new AttachmentMapper();
        $attachments = $attachmentMapper->findByPastoralId($pastoral->getId());

        foreach ($attachments as $attachment) {

            $resource = Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash($attachment->getHash());
            $content = bin2hex($content);
            $document = "0x".$content;

            $this->getDatabase()->query("INSERT INTO tDocuments (Description, SourceRed, Document) VALUES ('Text', 'Imported from Schoolbox', '{$document}')");
        }
        */

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deletePastoral(\Schoolbox\Pastoral\Pastoral\Model $pastoral)
    {
        if (trim($pastoral->getExternalId()) != '') {
            $result = $this->getDatabase()->query("DELETE FROM tPastoralCare WHERE PastoralCareSeq = " . $pastoral->getExternalId());
            return $result;
        } else {

            // Nothing to delete

            return true;
        }
    }

    /**
     * @param MSSQL $database
     */
    public function setDatabase($database)
    {
        $this->database = $database;
    }

    /**
     * @return MSSQL
     */
    public function getDatabase()
    {
        return $this->database;
    }
}