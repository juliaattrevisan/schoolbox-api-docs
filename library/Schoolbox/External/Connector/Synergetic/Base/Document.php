<?php
/**
 * @author      Damian Sloane <sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Synergetic\Base;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Exception as AlaressDatabaseException;
use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Alaress_Date_Format;
use DateTime;
use External_Ping;
use External_Synergetic_ValidateExternalIdTrait;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\External\Connector\Base;
use Schoolbox\External\Connector\IDocument;
use Schoolbox\IConfigLoader;
use Schoolbox\User;

class Document extends Base implements IDocument
{
    use External_Synergetic_ValidateExternalIdTrait;

    /** @var string $schema String indicating the Synergetic DB schema (varies between DB versions) */
    private $schema;

    /**
     * Constructor
     * Performs normal setup stuff, and also determines which schema to use
     * based on database version.
     *
     * @param IConfigLoader    $config
     * @param IConnector|array $databaseExternal
     */
    public function __construct(IConfigLoader $config, $databaseExternal)
    {
        parent::__construct($config, $databaseExternal);

        $this->schema = $this->retrieveDocumentSchema();
    }

    /**
     * What schema does the document integration use?
     *
     * @return string
     */
    private function retrieveDocumentSchema()
    {
        // TODO inject ping/diagnostic object
        $externalPing = new External_Ping();

        if ($externalPing->getVersion() == "68") {
            return "media";
        }

        return "SynergyOneMedia.dbo";
    }

    /**
     * @inheritdoc
     *
     * @param User $student
     *
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
            SELECT      sral.StudentReportsArchiveSeq as ID,
                        sral.Description,
                        sral.FileYear,
                        CONVERT(VARCHAR(20), sra.CreatedDate, 20) AS CreatedDate,
                        'StudentReportsArchive' AS Source
            FROM        {$this->schema}.StudentReportsArchiveLink sral
            JOIN        {$this->schema}.StudentReportsArchive sra ON 
                        sra.StudentReportsArchiveSeq = sral.StudentReportsArchiveSeq
            WHERE       ID = :id1
        ";

        $params = ['id1' => $student->getExternalId()];

        // Add date filter (if we find a parseable date in the config)

        $minimumDateString = $this->config->get('external_document_report_minimum_date');

        $date = DateTime::createFromFormat(Alaress_Date_Format::DATABASE_DATE_FORMAT, $minimumDateString);

        if ($date !== false) {
            $query .= ' AND sra.CreatedDate >= :date1';

            $params = array_merge($params, [
                'date1' => $date->format(Alaress_Date_Format::DATABASE_DATE_FORMAT),
            ]);
        }

        // If there's any DocMan classifications entered into the config - union docman docs on
        $bindings = $this->buildBindingsForClassifications();

        if (!empty($bindings)) {
            $query .= "
                UNION

                SELECT  d.tDocumentsSeq AS ID,
                        d.Description,
                        YEAR(d.SourceDate) AS FileYear,
                        CONVERT(VARCHAR(20), d.SourceDate, 20) as CreatedDate,
                        'tDocuments' AS Source
                FROM    {$this->schema}.tDocuments d
                JOIN    {$this->schema}.DocumentReferences dr ON dr.DocumentReferencesSeq = d.tDocumentsSeq
                WHERE   dr.ID = :id2
            ";

            $params['id2'] = $student->getExternalId();

            if ($date !== false) {
                $query .= ' AND d.SourceDate >= :date2 ';

                $params = array_merge($params, [
                    'date2' => $date->format(Alaress_Date_Format::DATABASE_DATE_FORMAT),
                ]);
            }

            $query .= "AND d.ClassificationCode IN ({$bindings['placeholders']})";

            $params = array_merge(
                $params,
                array_map(
                    'trim',
                    $bindings['bindings']
                )
            );

            try {
                // Check if we have permission to the `pvDocumentReferences` view. This will fail into the exception
                // block and stop us from adding the query and breaking the feature if permission to this table is
                // not available.

                // TODO: Remove external query to check for access once all schools have added the appropriate permission

                $this->databaseExternal->executeQuery("SELECT 1 FROM {$this->schema}.pvDocumentReferences");

                // If that query worked we can assume they have updated their DB permissions and use this extra source

                $bindings = $this->buildBindingsForClassifications('pvd');

                $query .= "
                    UNION
                    
                    SELECT      d.tDocumentsSeq AS ID,
                                d.Description,
                                COALESCE(
                                    (
                                        SELECT TOP 1 FileYear
                                        FROM FileSemesters
                                        WHERE d.SourceDate 
                                        BETWEEN StartDate AND EndDate
                                    ), YEAR(d.SourceDate)
                                ) AS FileYear,
                                CONVERT(VARCHAR(20), SourceDate, 20) as CreatedDate,
                                'tDocuments' AS Source
                    FROM        {$this->schema}.pvDocumentReferences dr
                    INNER JOIN  {$this->schema}.tDocuments d ON d.tDocumentsSeq = dr.tDocumentsSeq
                    WHERE       dr.ID = :id3
                ";

                $params['id3'] = $student->getExternalId();

                if ($date !== false) {
                    $query .= ' AND d.SourceDate >= :date3 ';

                    $params = array_merge($params, [
                        'date2' => $date->format(Alaress_Date_Format::DATABASE_DATE_FORMAT),
                    ]);
                }

                $query .= "AND d.ClassificationCode IN ({$bindings['placeholders']})";

                $params = array_merge(
                    $params,
                    array_map(
                        'trim',
                        $bindings['bindings']
                    )
                );
            } catch (AlaressDatabaseException $e) {
                // There was no access to the 'pvDocumentReferences' views. Fail silently without adding the SQL
                // that references this table.
            }
        }

        $query .= "
            ORDER BY FileYear DESC, CreatedDate DESC
        ";

        // Get all documents for the student

        $documentsQuery = $this->databaseExternal->executeQuery($query, $params);
        $documents = $documentsQuery->fetchAll();

        $documentsUnique = [];

        foreach ($documents as &$document) {
            if (!isset($documentsUnique["{$document["Source"]}.{$document["ID"]}"])) {
                $document["CreatedDate"] = Alaress_Date_Format::newDatabaseDate($document["CreatedDate"]);
                $documentsUnique["{$document["Source"]}.{$document["ID"]}"] = $document;
            }
        }

        return $documentsUnique;
    }

    /**
     * @inheritdoc
     *
     * @param User   $student
     * @param string $documentId
     * @param string $source
     *
     * @return array
     */
    public function downloadAcademicReportForStudent(User $student, $documentId, $source)
    {
        // If the user does not have a valid external ID, or is not a student- return an empty collection

        if (!$this->isExternalIdValid($student->getExternalId()) || !$student->getRole()->isStudent()) {
            return [];
        }

        $params = [
            'id'         => $student->getExternalId(),
            'documentId' => $documentId,
        ];

        // Create query to get the document from the database

        if ($source == 'tDocuments') {
            $bindings = $this->buildBindingsForClassifications();

            // If there's no classifications in config - error out because they shouldn't be getting anything
            // from DocMan
            if (empty($bindings)) {
                throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_FILE_NOT_FOUND);
            }

            // Loading from generic DocMan table

            $query = "
                SELECT    Document,
                          DocumentType,
                          Description
                FROM      {$this->schema}.tDocuments d
                JOIN      {$this->schema}.DocumentReferences dr ON dr.DocumentReferencesSeq = d.tDocumentsSeq
                WHERE     dr.ID = :id
                AND       d.tDocumentsSeq = :documentId
            ";

            // Add date filter (if we find a parseable date in the config)

            $minimumDateString = $this->config->get('external_document_report_minimum_date');

            $date = DateTime::createFromFormat(Alaress_Date_Format::DATABASE_DATE_FORMAT, $minimumDateString);

            if ($date !== false) {
                $query .= ' AND d.SourceDate >= :date';

                $params = array_merge($params, [
                    'date' => $date->format(Alaress_Date_Format::DATABASE_DATE_FORMAT),
                ]);
            }

            // Check this document is in one of the classifications in config

            $query .= " AND d.ClassificationCode IN ({$bindings['placeholders']})";

            $params = array_merge(
                $params,
                array_map(
                    'trim',
                    $bindings['bindings']
                )
            );
        } elseif ($source == 'StudentReportsArchive') {
            // Loading from school reports archive table

            $query = "
                SELECT    Document,
                          'PDF' AS DocumentType,
                          Description
                FROM      {$this->schema}.StudentReportsArchive sra
                JOIN      {$this->schema}.StudentReportsArchiveLink sral ON 
                          sra.StudentReportsArchiveSeq = sral.StudentReportsArchiveSeq
                WHERE     ID = :id
                AND       sra.StudentReportsArchiveSeq = :documentId
            ";

            // Add date filter (if we find a parseable date in the config)

            $minimumDateString = $this->config->get('external_document_report_minimum_date');

            $date = DateTime::createFromFormat(Alaress_Date_Format::DATABASE_DATE_FORMAT, $minimumDateString);

            if ($date !== false) {
                $query .= ' AND sra.CreatedDate >= :date ';

                $params = array_merge($params, [
                    'date' => $date->format(Alaress_Date_Format::DATABASE_DATE_FORMAT),
                ]);
            }
        } else {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_FILE_NOT_FOUND);
        }

        // Get the document from the database

        $result = $this->databaseExternal->executeQuery($query, $params);

        if ($row = $result->fetch()) {
            // If we found a database record, return an array with the details necessary for downloading using
            // the historical code

            return [
                'filename' => "{$row["Description"]}.{$row["DocumentType"]}",
                'filesize' => strlen($row["Document"]),
                'blob'     => $row["Document"],
            ];
        }

        // If we got here the document could not be located in the database

        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_FILE_NOT_FOUND);
    }

    /**
     * Builds placeholders and bindings for document classifications.
     *
     * @param string $suffix Suffix for bindings
     *
     * @return array Empty if there's no document classifications.
     *               Otherwise:
     *               bindings:     the parameters to bind to a query
     *               placeholders: the IN part of a query
     */
    private function buildBindingsForClassifications($suffix = '')
    {
        $classificationCodes = array_filter(
            explode(",", trim($this->config->get('external_document_docman_categories')))
        );

        if (count($classificationCodes) > 0) {
            return PredicateBuilder::getArrayBindings($classificationCodes, 'classificationCode' . $suffix);
        }

        return [];
    }
}
