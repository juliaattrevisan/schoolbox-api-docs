<?php
/**
 * @author      Damian Sloane <sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

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

        // The upload location varies depending on the version of Synergetic

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
            SELECT          StudentReportsArchiveLinkSeq, 
                            sral.FileYear, 
                            sral.FileSemester, 
                            Description
            FROM            {$this->schema}.StudentReportsArchive sra
            JOIN            {$this->schema}.StudentReportsArchiveLink sral ON 
                            sra.StudentReportsArchiveSeq = sral.StudentReportsArchiveSeq
            INNER JOIN      dbo.studentyears sy ON 
                            sy.id = sral.ID AND 
                            sy.fileyear = sral.FileYear
            LEFT OUTER JOIN dbo.uluStudentReports ulusr ON 
                            sral.fileyear = ulusr.fileyear AND 
                            sral.FileSemester = ulusr.FileSemester AND 
                            sy.YearLevel = ulusr.YearLevel AND 
                            sy.StudentCampus = ulusr.Campus
            WHERE           sral.ID = :externalId AND
                            ulusr.ShowOnOLE = 1
            ORDER BY        sral.FileYear DESC, 
                            sral.FileSemester DESC, 
                            sra.CreatedDate DESC
        ";

        $iterator = $this->getConnector()->executeQueryIterator($query, [
            'externalId' => $student->getExternalId()
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
}
