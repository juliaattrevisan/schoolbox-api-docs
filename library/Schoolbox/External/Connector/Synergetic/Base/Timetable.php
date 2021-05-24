<?php

/**
 * Schoolbox implementation of timetable connector
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Synergetic\Base;

use Schoolbox\Collection;
use Schoolbox\External\Connector\Base;
use Schoolbox\External\Connector\ITimetable;
use Schoolbox\External\Normalise\Timetable\Lesson;

class Timetable extends Base implements ITimetable
{
    /**
     * @inheritdoc
     */
    public function getCurrentSemester($externalId = null)
    {
        if (!empty($externalId)) {
            // Get user specific file semester

            $semesterStatement = $this->databaseExternal->executeStoredProcedure(
                'spsConfigUserDefaultYearSemester',
                ['ID', $externalId]
            );

            if ($row = $semesterStatement->fetch()) {
                return $row['UserFileSemester'];
            }
        }

        // Get current default file semester

        $semesterStatement = $this->databaseExternal->executeQuery("
            SELECT    FileSemester
            FROM      FileSemesters
            WHERE     SystemCurrentFlag = 1
        ");

        if ($row = $semesterStatement->fetch()) {
            return $row['FileSemester'];
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function getCurrentYear($externalId = null)
    {
        if (!empty($externalId)) {
            // Get user specific file year

            $semesterStatement = $this->databaseExternal->executeStoredProcedure(
                'spsConfigUserDefaultYearSemester',
                ['ID', $externalId]
            );

            if ($row = $semesterStatement->fetch()) {
                return $row['UserFileYear'];
            }
        }

        // Get current default file year

        $semesterStatement = $this->databaseExternal->executeQuery("
            SELECT    FileYear
            FROM      FileSemesters
            WHERE     SystemCurrentFlag = 1
        ");

        if ($row = $semesterStatement->fetch()) {
            return $row['FileYear'];
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function getLessons($externalIds)
    {
        $externalIdBindings1 = $this->databaseExternal->prepareInBindings($externalIds);
        $externalIdBindings2 = $this->databaseExternal->prepareInBindings($externalIds);

        $subjectIterator = $this->databaseExternal->getQueryIterator(
            "
                SELECT          SubjectClasses.SubjectClassesSeq AS id,
                                StudentClasses.ClassCode AS code,
                                SubjectClasses.ClassCampus AS campus,
                                SubjectClasses.Description AS name,
                                luFileType.Description AS classType
                FROM            StudentClasses
                INNER JOIN      SubjectClasses ON
                                    StudentClasses.FileType = SubjectClasses.FileType AND
                                    StudentClasses.FileYear = SubjectClasses.FileYear AND
                                    StudentClasses.FileSemester = SubjectClasses.FileSemester AND
                                    StudentClasses.ClassCampus = SubjectClasses.ClassCampus AND
                                    StudentClasses.ClassCode = SubjectClasses.ClassCode AND
                                    StudentClasses.ID IN ({$externalIdBindings1['sql']})
                JOIN            FileSemesters Semester ON
                                Semester.FileSemester = StudentClasses.FileSemester AND
                                Semester.FileYear = StudentClasses.FileYear AND
                                Semester.SystemCurrentFlag = 1

                LEFT JOIN       luFileType ON StudentClasses.FileType = luFileType.Code
                WHERE           StudentClasses.StartDate <= getdate() AND
                                (StudentClasses.StopDate >= getdate() OR StudentClasses.StopDate IS NULL)
                ORDER BY        SubjectClasses.FileType ASC,
                                SubjectClasses.Description ASC

                UNION ALL

                SELECT DISTINCT c2.SubjectClassesSeq AS id,
                                c1.ClassCode AS code,
                                c1.ClassCampus AS campus,
                                c2.description AS name,
                                luFileType.Description AS type
                FROM            Timetable c1
                INNER JOIN      SubjectClasses c2 ON
                                    c1.FileType = c2.FileType AND
                                    c1.FileYear = c2.FileYear AND
                                    c1.FileSemester = c2.FileSemester AND
                                    c1.ClassCampus = c2.ClassCampus AND
                                    c1.ClassCode = c2.ClassCode AND
                                    c1.StaffID IN ({$externalIdBindings2['sql']})
                JOIN            FileSemesters Semester ON
                                Semester.FileSemester = c1.FileSemester AND
                                Semester.FileYear = c1.FileYear AND
                                Semester.SystemCurrentFlag = 1
                LEFT JOIN       luFileType ON c2.FileType = luFileType.Code
                ORDER BY        luFileType.Description ASC,
                                c2.Description ASC
            ",
            array_merge(
                $externalIdBindings1['bindings'],
                $externalIdBindings2['bindings']
            )
        );

        $results = new Collection();

        foreach ($subjectIterator as $lessonRow) {
            $lesson = new Lesson();
            $lesson->setId($lessonRow['id']);
            $lesson->setName($lessonRow['name']);
            $lesson->setCode($lessonRow['code']);
            $lesson->setCampus($lessonRow['campus']);
            $lesson->setType($lessonRow['type']);
            $results->add($lesson);
        }

        return $results;
    }
}
