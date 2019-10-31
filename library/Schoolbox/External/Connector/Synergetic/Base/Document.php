<?php
/**
 * @author      Damian Sloane <sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Synergetic\Base;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Exception as AlaressDatabaseException;
use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Alaress_Date_Format;
use External_Ping;
use External_Synergetic_ValidateExternalIdTrait;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\External\Connector\Base;
use Schoolbox\External\Connector\IDocument;
use Schoolbox\IConfigLoader;
use Schoolbox\User;
use Exception;

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
     * @param IConfigLoader   $config
     * @param null|IConnector $databaseExternal
     * @throws Exception
     */
    public function __construct(IConfigLoader $config, ?IConnector $databaseExternal)
    {
        parent::__construct($config, $databaseExternal);

        $this->schema = $this->retrieveDocumentSchema();
    }

    /**
     * What schema does the document integration use?
     *
     * @return string
     * @throws Exception
     */
    private function retrieveDocumentSchema(): string
    {
        // TODO inject ping/diagnostic object
        $externalPing = new External_Ping();

        if ($externalPing->getVersion() > 67) {
            return "media";
        }

        return "SynergyOneMedia.dbo";
    }

    /**
     * @inheritdoc
     *
     * @param User  $student
     * @param array $classificationCode
     *
     * @return array
     */
    public function getAcademicReportsForStudent(User $student, array $classificationCodes): array
    {
        // If the user does not have a valid external ID, or is not a student- return an empty collection

        if (!$this->isExternalIdValid($student->getExternalId()) || !$student->getRole()->isStudent() || empty($classificationCodes)) {
            return [];
        }

        // Add date filter (if we find a parseable date in the config)
        $minimumDateString = $this->config->get('external_document_report_minimum_date');
        $date              = empty($minimumDateString) ? null : new Alaress_Date_Format($minimumDateString, Alaress_Date_Format::DATABASE_DATE_FORMAT);

        // Find the documents for this user
        // Reports archive table
        $documentsArchive = $this->findDocumentsFromArchive($classificationCodes, $student->getExternalId(), null, $date);
        // Documents tables
        $documentsRef = $this->findDocumentsFromReference($classificationCodes, $student->getExternalId(), null, $date);
        $documentsDoc = $this->findDocumentsFromDocTable($classificationCodes, $student->getExternalId(), null, $date);

        // Get all documents for the student
        $documentCollection = (new Collection($documentsArchive))
            ->mergeUnique(new Collection($documentsRef))
            ->mergeUnique(new Collection($documentsDoc))
            ->map(
                function (array $a) {
                    $a["CreatedDate"] = Alaress_Date_Format::newDatabaseDate($a["CreatedDate"]);
                    return $a;
                }
            )->sortByCallback(function ($a, $b) {
                /** @var Alaress_Date_Format $dateA */
                $dateA = $a['CreatedDate'];
                /** @var Alaress_Date_Format $dateB */
                $dateB = $b['CreatedDate'];
                return $dateA->lt($dateB);
            });
        return $documentCollection->getAll();
    }

    /**
     * @param string $table
     * @return bool
     */
    private function isTableAccessible(string $table): bool
    {
        try {
            $this->databaseExternal->executeQuery("SELECT 1 FROM {$table}");
            return true;
        } catch (AlaressDatabaseException $e) {
            // There was no access to the 'pvDocumentReferences' views. Fail silently without adding the SQL
            // that references this table.
        }
        return false;
    }

    /**
     *
     * @param User   $student
     * @param string $documentId
     * @param string $source
     * @param array  $classificationCodes
     *
     * @return array
     * @throws Exception
     */
    public function downloadAcademicReportForStudent(User $student, $documentId, $source, array $classificationCodes): array
    {
        // If the user does not have a valid external ID, or is not a student- return an empty collection

        if (!$this->isExternalIdValid($student->getExternalId()) || !$student->getRole()->isStudent()) {
            return [];
        }

        // Add date filter (if we find a parseable date in the config)
        $minimumDateString = $this->config->get('external_document_report_minimum_date');
        $date              = empty($minimumDateString) ? null : new Alaress_Date_Format($minimumDateString, Alaress_Date_Format::DATABASE_DATE_FORMAT);

        // Create query to get the document from the database
        if ($source == 'tDocuments') {
            $documents = $this->findDocumentsFromDocTable($classificationCodes, $student->getExternalId(), $documentId, $date);
            if (empty($documents)) {
                $documents = $this->findDocumentsFromReference($classificationCodes, $student->getExternalId(), $documentId, $date);
            }
        } elseif ($source == 'StudentReportsArchive') {
            $documents = $this->findDocumentsFromArchive($classificationCodes, $student->getExternalId(), $documentId, $date);
        }

        if (!isset($documents) || count($documents) < 1) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_FILE_NOT_FOUND);
        }

        $row = $documents[0];
        return [
            'filename' => $row["filename"],
            'filesize' => strlen($row["Document"]),
            'blob'     => $row["Document"],
        ];
    }

    /**
     * Gets documents from tDocuments table
     * @param array                    $classificationCodes
     * @param string                   $externalId
     * @param string|null              $documentSeq
     * @param Alaress_Date_Format|null $date
     * @return array
     */
    private function findDocumentsFromDocTable(array $classificationCodes, string $externalId, ?string $documentSeq, ?Alaress_Date_Format $date): array
    {
        // If there's any DocMan classifications entered into the config - union docman docs on
        $cc      = PredicateBuilder::getArrayBindings($classificationCodes, 'cc');
        $content = $documentSeq ? 'Document, ' : '';
        $query   = "
                SELECT  d.tDocumentsSeq AS ID,
                        {$content}
                        d.Description,
                        CONCAT(d.Description,'.',d.DocumentType) as filename,
                        YEAR(d.SourceDate) AS FileYear,
                        CONVERT(VARCHAR(20), d.SourceDate, 20) as CreatedDate,
                        'tDocuments' AS Source
                FROM    {$this->schema}.tDocuments d
                JOIN    {$this->schema}.DocumentReferences dr ON dr.DocumentReferencesSeq = d.tDocumentsSeq
                WHERE   dr.ID = :externalId
                AND     d.ClassificationCode IN ({$cc['placeholders']})
            ";

        $params['externalId'] = $externalId;
        $params               = array_merge($params, $cc['bindings']);

        if ($documentSeq) {
            $query                .= ' AND d.tDocumentsSeq = :documentId';
            $params['documentId'] = $documentSeq;
        }

        if (null !== $date) {
            $query              .= ' AND d.SourceDate >= :dateFrom ';
            $params['dateFrom'] = $date->getDatabaseDate();
        }
        $documentsQuery = $this->databaseExternal->executeQuery($query, $params);
        return $documentsQuery->fetchAll();
    }

    /**
     * Gets documents from StudentReportsArchiveLink table
     * @param array                    $classificationCodes
     * @param string                   $externalId
     * @param string|null              $documentSeq
     * @param Alaress_Date_Format|null $date
     * @return array
     */
    private function findDocumentsFromArchive(array $classificationCodes, string $externalId, ?string $documentSeq, ?Alaress_Date_Format $date)
    {
        // Get classification code for archive and check whether it's included in the classification codes
        $includeArchive = false;
        if ($this->isTableAccessible('luDocumentClassification')) {
            $bindingsCC     = PredicateBuilder::getArrayBindings($classificationCodes, 'cc');
            $queryCC        = "
            SELECT  count(*) as result
            FROM    luDocumentClassification
            WHERE   SynergyMeaning = 'StudentReports'
              AND   Code IN ({$bindingsCC['placeholders']})
        ";
            $includeArchive = $this->databaseExternal->executeQuery($queryCC, $bindingsCC['bindings'])->fetchColumn();
        }
        if (!$includeArchive) {
            return [];
        }

        // If there's any DocMan classifications entered into the config - union docman docs on
        $content = $documentSeq ? 'Document, ' : '';
        $query   = "
            SELECT      sral.StudentReportsArchiveSeq as ID,
                        {$content}
                        sral.Description,
                        CONCAT(sral.Description,'.PDF') as filename,
                        sral.FileYear,
                        CONVERT(VARCHAR(20), sra.CreatedDate, 20) AS CreatedDate,
                        'StudentReportsArchive' AS Source
            FROM        {$this->schema}.StudentReportsArchiveLink sral
            JOIN        {$this->schema}.StudentReportsArchive sra ON 
                        sra.StudentReportsArchiveSeq = sral.StudentReportsArchiveSeq
            WHERE       ID = :externalId
            ";

        $params['externalId'] = $externalId;

        if ($documentSeq) {
            $query                .= ' AND sra.StudentReportsArchiveSeq = :documentId';
            $params['documentId'] = $documentSeq;
        }

        if (null !== $date) {
            $query              .= ' AND sra.CreatedDate >= :dateFrom ';
            $params['dateFrom'] = $date->getDatabaseDate();
        }
        $documentsQuery = $this->databaseExternal->executeQuery($query, $params);
        return $documentsQuery->fetchAll();
    }

    /**
     * Gets documents from pvDocumentReferences view
     * @param                          $classificationCodes
     * @param                          $externalId
     * @param string|null              $documentSeq
     * @param Alaress_Date_Format|null $date
     * @return array
     */
    private function findDocumentsFromReference($classificationCodes, $externalId, ?string $documentSeq, ?Alaress_Date_Format $date)
    {

        if (!$this->isTableAccessible("{$this->schema}.pvDocumentReferences")) {
            return [];
        }

        // If there's any DocMan classifications entered into the config - union docman docs on
        $cc      = PredicateBuilder::getArrayBindings($classificationCodes, 'cc');
        $content = $documentSeq ? 'Document, ' : '';
        $query   = "
                    SELECT      d.tDocumentsSeq AS ID,
                                {$content}
                                d.Description,
                                CONCAT(d.Description,'.',d.DocumentType) as filename,
                                YEAR(d.SourceDate) AS FileYear,
                                CONVERT(VARCHAR(20), SourceDate, 20) as CreatedDate,
                                'tDocuments' AS Source
                    FROM        {$this->schema}.pvDocumentReferences dr
                    INNER JOIN  {$this->schema}.tDocuments d ON d.tDocumentsSeq = dr.tDocumentsSeq
                    WHERE       dr.ID = :externalId
                    AND         d.ClassificationCode IN ({$cc['placeholders']})
            ";

        $params['externalId'] = $externalId;
        $params               = array_merge($params, $cc['bindings']);

        if ($documentSeq) {
            $query                .= ' AND d.tDocumentsSeq = :documentId';
            $params['documentId'] = $documentSeq;
        }

        if (null !== $date) {
            $query              .= ' AND d.SourceDate >= :dateFrom ';
            $params['dateFrom'] = $date->getDatabaseDate();
        }
        $documentsQuery = $this->databaseExternal->executeQuery($query, $params);
        return $documentsQuery->fetchAll();
    }
}
