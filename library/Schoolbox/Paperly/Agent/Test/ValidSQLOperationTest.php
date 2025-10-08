<?php

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Paperly\Agent\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Paperly\Agent\ConnectionFactory;
use Schoolbox\Paperly\Agent\Proxy;

class ValidSQLOperationTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidSQLOperation')]
    public function testValidSQLOperation($sql, $expected)
    {
        $connection = $this->createMock(ConnectionFactory::class);

        $proxy = new Proxy($connection->build());

        $actual = $proxy->shouldReturnResults($sql);

        $this->assertEquals($expected, $actual);
    }

    /**
     * Data provider for testValidSQLOperation
     *
     * @return array
     */
    public static function providerValidSQLOperation(): array
    {
        return [
            'Simple SELECT query' => [
                'SELECT * FROM luYearLevel',
                true
            ],
            'Simple UPDATE query' => [
                'UPDATE dbo.Students SET EmergencyName = "Bob" WHERE ID = 51',
                false
            ],
            'Simple INSERT INTO query' => [
                'INSERT INTO dbo.Students (ID,CreatedDate,CreatedBy,ModifiedDate,ModifiedBy,Operator,StudiesCode,TertiaryRegistrationCode,TimeTableCode,AltCode,DebtorID,DebtorIDSeq,N16StuCode,N16ParCode,N16DebCode,AgeYearsJan,AgeMonthsJan,FamilyPosition,EldestFlag,YoungestFlag,EntryDate,EntryCampus,EntryYearLevel,EnquirySource,EnquirySourceComment,PreviousSchool,MedicalAlert,ExternalStudentFlag,EnglishSecondFlag,DisabilityFlag,FullFeeFlag,TuitionVariationType,TuitionNoSibFlag,DoctorName,DoctorPhone,DoctorPhoneAlt,DoctorAddress,DoctorSpecialisation,Doctor2Name,Doctor2Phone,Doctor2PhoneAlt,Doctor2Address,Doctor2Specialisation,DentistName,DentistPhone,DentistPhoneAlt,DentistAddress,EmergencyName,EmergencyAddress,EmergencyPhone,EmergencyPhoneAlt,EmergencyRelat,Emergency2Name,Emergency2Address,Emergency2Phone,Emergency2PhoneAlt,Emergency2Relat,LastLessonRcvdDate,BirthCertificateFlag,NonResidentFlag,CrecheFlag,BeforeSchoolCareFlag,AfterSchoolCareFlag,VisaType,VisaExpiryDate,PassportNo,Comment,CommentWarningFlag,BaptismFlag,BaptismDate,ReconciliationFlag,ReconciliationDate,CommunionFlag,CommunionDate,ConfirmationFlag,ConfirmationDate,AustudyFlag,IndigenousFlag,NaturalisationDate,DebtorPrimaryOnlyFlag,StudentBillingFlag,AllowAutoTuitionFlag,AllowAutoChargesFlag,VisaNumber,PassportCountryCode,PassportExpiryDate,ApplicationDate,Emergency3Name,Emergency3Address,Emergency3Phone,Emergency3PhoneAlt,Emergency3Relat,MedicalAlertFlag,SpecialNeedsFlag,SpecialBillingType,StudyAtHomeFlag,TSIFlag,ESLProficiencyLevel, PreEntryComment, EnquiryDate, IntendsTertiaryFlag,PassportIssuedDate,VisaIssuedDate,EmergencyID,Emergency2ID,Emergency3ID,LastRequestedDetailsDate)
                 VALUES
                     (54,2002-08-27 16:43:45.677,sa,2025-01-21 12:45:05.130,paperly,sa,,,,,22,0,,,,0,0,0,0,0,NULL,,NULL,,,,,0,0,0,0,,0,,,,,,,,,,,,,,,TEST44,,,,,,,,,,NULL,0,0,0,0,0,,NULL,,NULL,0,0,NULL,0,NULL,0,NULL,0,NULL,0,0,NULL,0,0,1,1,,,NULL,NULL,,,,,,0,0,,0,0,,NULL,NULL,0,NULL,NULL,0,0,0,NULL)',
                false
            ],
            'Nesting SELECT query' => [
                'SELECT StaffID, StaffPreferred, StaffSurname, StaffEmail, ClassCode, ClassDescription, YearLevelCode FROM (SELECT StaffID, StaffPreferred, StaffSurname, StaffEmail, ClassCode, ClassDescription, YearLevelCode FROM uvPaperlyAllStaff WHERE 1=1) filtered_results',
                true
            ],
            'SELECT with parameter placeholders' => [
                'SELECT DISTINCT Code, Description FROM luYearLevel WHERE Code IN (?, ?, ?)',
                true
            ],
            'SELECT with spaces' => [
                '        SELECT * FROM luYearLevel ',
                true
            ],
            'Ignore leading non-alphabetical characters' => [
                '(SELECT * FROM luYearLevel)',
                true
            ]
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvalidSQLOperation')]
    public function testInvalidSQLOperation($sql)
    {
        $this->expectException(\OutOfBoundsException::class);

        $connection = $this->createMock(ConnectionFactory::class);

        $proxy = new Proxy($connection->build());

        $actual = $proxy->shouldReturnResults($sql);
    }

    /**
     * Data provider for providerInvalidSQLOperation
     *
     * @return array
     */
    public static function providerInvalidSQLOperation(): array
    {
        return [
            'Simple SELECT query with semi-colon' => [
                'SELECT * FROM luYearLevel;',
                true
            ],
            'Simple SELECT query with leading semi-colon' => [
                ';SELECT * FROM luYearLevel;'
            ],
            'Simple DELETE query' => [
                'DELETE FROM dbo.Students WHERE ID = 54',
                false
            ],
            'Simple DROP query' => [
                'DROP TABLE luYearLevel'
            ],
            'Simple TRUNCATE query' => [
                'TRUNCATE TABLE luYearLevel',
            ],
            'Simple ALTER query' => [
                'ALTER TABLE luYearLevel DROP COLUMN Code',
            ],
            'Random string' => [
                'SELECERTER EFED ADSLl'
            ]
        ];
    }
}
