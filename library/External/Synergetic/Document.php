<?php
/**
 * @author      Damian Sloane <sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\User;

class External_Synergetic_Document implements External_DocumentInterface
{
    use External_MssqlConnector;
    use External_Synergetic_ValidateExternalIdTrait;

    /** @var string $schema String indicating the Synergetic DB schema (varies between DB versions) */
    private $schema;

    /**
     * Constructor
     */
    public function __construct()
    {
        // This connects to the Synergetic database and stores an IConnection in `$this->connector` variable

        $this->setupDatabase();

        $externalPing = new External_Ping();

        // The location of documents varies depending on the version of the Synergetic database schema

        if ($externalPing->getVersion() == "68") {
            $this->schema = "media";
        } else {
            $this->schema = "SynergyOneMedia.dbo";
        }
    }

    /**
     * @inheritdoc
     *
     * @param User $student
     * @return array
     */
    public function getAcademicReportsForStudent(User $student)
    {
        // If the user does not have a valid external ID, or is not a student- return an empty collection

        if (!$this->isExternalIdValid($student->getExternalId()) || !$student->getRole()->isStudent()) {
            return [];
        }

        // Otherwise attempt to find the documents for this user

        $query =
        "
            SELECT	    sral.StudentReportsArchiveLinkSeq,
                        sral.FileYear,
                        sral.FileSemester,
                        sral.Description
            FROM	    {$this->schema}.StudentReportsArchiveLink sral
            JOIN        {$this->schema}.StudentReportsArchive sra ON 
                        sra.StudentReportsArchiveSeq = sral.StudentReportsArchiveSeq
            WHERE       ID = :id
            ORDER BY    sral.FileYear DESC, 
                        sral.FileSemester DESC, 
                        sra.CreatedDate DESC
        ";

        $iterator = $this->getConnector()->executeQueryIterator($query, [
            'id' => $student->getExternalId()
        ]);

        $reports = [];

        foreach ($iterator as $row) {
            $reportId = $row['StudentReportsArchiveLinkSeq'];
            $year = $row['FileYear'];
            $semester = $row['FileSemester'];
            $description = $row['Description'];

            $reports[$year][$semester][$reportId] = ['description' => $description];
        }

        return $reports;
    }

    /**
     * @inheritdoc
     *
     * @param User $student
     * @param string $documentId
     * @return array
     */
    public function downloadAcademicReportForStudent(User $student, $documentId)
    {
        // If the user does not have a valid external ID, or is not a student- return an empty collection

        if (!$this->isExternalIdValid($student->getExternalId()) || !$student->getRole()->isStudent()) {
            return [];
        }

        // Retrieve the document from the database

        $query =
        "
            SELECT    Document, 
                      Description
            FROM      {$this->schema}.StudentReportsArchive sra
            JOIN      {$this->schema}.StudentReportsArchiveLink sral ON 
                      sra.StudentReportsArchiveSeq = sral.StudentReportsArchiveSeq
            WHERE     ID = :id
            AND       sra.StudentReportsArchiveSeq = :documentId
        ";

        $result = $this->getConnector()->executeQuery($query, [
            'id' => $student->getExternalId(),
            'documentId' => $documentId
        ]);

        if ($row = $result->fetch()) {

            // If we found a database record, return an array with the details necessary for downloading

            return [
                'filename' => "{$row["Description"]}.pdf",
                'filesize' => strlen($row["Document"]),
                'blob' => $row["Document"],
            ];
        }

        // If we got here the document could not be located in the database

        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_FILE_NOT_FOUND);
    }
}
