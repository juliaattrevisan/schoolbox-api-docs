<?php
/**
 * @author      Damian Sloane <sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Schoolbox\Config\ConfigMapper;
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
        ";

        $params = [
            'id' => $student->getExternalId()
        ];

        // Add date filter (if we find a parseable date in the config)

        $minimumDateString = ConfigMapper::getInstance()->get('external_document_report_minimum_date');

        $date = DateTime::createFromFormat(Alaress_Date_Format::DATABASE_DATE_FORMAT, $minimumDateString);

        if ($date !== false) {

            $query .= ' AND sra.CreatedDate >= :createdDate';

            $params = array_merge($params, [
                'createdDate' => $date->format(Alaress_Date_Format::DATABASE_DATE_FORMAT)
            ]);
        }

        $query .= "
            ORDER BY    sral.FileYear DESC, 
                        sral.FileSemester DESC, 
                        sra.CreatedDate DESC
        ";

        // Get all documents for the student

        $iterator = $this->getConnector()->executeQueryIterator($query, $params);

        // Return the results in an array in the format that is required by the historical code we are using

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

        // Create query to get the document from the database

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

        $params = [
            'id' => $student->getExternalId(),
            'documentId' => $documentId
        ];

        // Add date filter (if we find a parseable date in the config)

        $minimumDateString = ConfigMapper::getInstance()->get('external_document_report_minimum_date');

        $date = DateTime::createFromFormat(Alaress_Date_Format::DATABASE_DATE_FORMAT, $minimumDateString);

        if ($date !== false) {

            $query .= ' AND sra.CreatedDate >= :createdDate';

            $params = array_merge($params, [
                'createdDate' => $date->format(Alaress_Date_Format::DATABASE_DATE_FORMAT)
            ]);
        }

        // Get the document from the database

        $result = $this->getConnector()->executeQuery($query, $params);

        if ($row = $result->fetch()) {

            // If we found a database record, return an array with the details necessary for downloading
            // using the historical code

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
