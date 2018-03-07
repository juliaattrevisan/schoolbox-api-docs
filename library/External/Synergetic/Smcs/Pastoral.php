<?php
/**
 *
 * @author      Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Schoolbox\User;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\Pastoral\Log\Model as PastoralLog;
use Schoolbox\Pastoral\Access\Model as PastoralAccess;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;

class External_Synergetic_Smcs_Pastoral extends External_Synergetic_Pastoral
{
    /** @var string $sqlPastoralSelect */
    protected $sqlPastoralSelect = "
        SELECT        dbo.tPastoralCare.PastoralCareSeq, 
                      dbo.tPastoralCare.ID, 
                      dbo.tPastoralCare.ClassCode, 
                      dbo.tPastoralCare.ItemDate, 
                      dbo.tPastoralCare.MethodCode,
                      dbo.tPastoralCare.Details, 
                      dbo.tPastoralCare.PrivateDetails, 
                      dbo.tPastoralCare.InitiatorCode, 
                      dbo.tPastoralCare.TypeCode,
                      dbo.luPastoralCareType.Description AS TypeDescription,
                      dbo.tPastoralCare.CategoryCode,
                      dbo.luPastoralCareCategory.Description AS CategoryDescription,
                      dbo.tPastoralCare.ClassificationCode, 
                      dbo.tPastoralCare.IsFollowUpFlag, 
                      dbo.tPastoralCare.FollowUpDate, 
                      dbo.tPastoralCare.CreatedDate, 
                      dbo.tPastoralCare.CreatedByID, 
                      dbo.tPastoralCare.ModifiedDate, 
                      dbo.tPastoralCare.ModifiedByID,
                      dbo.tPastoralCare.EmailSentDate, 
                      dbo.tPastoralCare.EmailSentByID, 
                      dbo.tPastoralCare.DuplicateFromSeq, 
                      dbo.tPastoralCare.SubjectClassesSeq,
                      Community_1.Surname AS StudentLastname, 
                      Community_1.Given1 AS StudentFirstname,
                      dbo.Community.Surname AS OwnerLastname, 
                      dbo.Community.Given1 AS OwnerFirstname
        FROM          dbo.tPastoralCare 
        LEFT JOIN     dbo.luPastoralCareType ON dbo.tPastoralCare.TypeCode = dbo.luPastoralCareType.Code
        LEFT JOIN     dbo.luPastoralCareCategory ON dbo.tPastoralCare.CategoryCode = dbo.luPastoralCareCategory.Code
        INNER JOIN    dbo.Community ON dbo.tPastoralCare.CreatedByID = dbo.Community.ID 
        INNER JOIN    dbo.Community AS Community_1 ON dbo.tPastoralCare.ID = Community_1.ID
        INNER JOIN    dbo.uvStudents ON dbo.uvStudents.ID = dbo.tPastoralCare.ID
        INNER JOIN    dbo.FileSemesters ON 
                          dbo.FileSemesters.FileYear = dbo.uvStudents.FileYear AND
                          dbo.FileSemesters.FileSemester = dbo.uvStudents.FileSemester AND
                          dbo.FileSemesters.SystemCurrentFlag = 1
    ";
}