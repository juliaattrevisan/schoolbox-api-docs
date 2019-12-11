<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\PAT\Table;

use Custom\Diocesan\AchievementReport\StandardisedTest\Base;

/**
 * Class Data
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest\PAT\Table
 */
class Data extends Base
{

    /**
     * I changed the SELECT aliases from the original, in the interest of posterity
     *
     * @return array
     */
    public function findData(): array
    {
        $query = "
            SELECT
                R.YEARLEVEL AS year,
                MAX(R.MATHS) AS mathematics,
                MAX(R.LC) AS listeningComprehension,
                MAX(R.RV) AS readingVocabulary,
                MAX(R.RC) AS readingComprehension,
                MAX(R.PG ) AS punctuationGrammar
            FROM
            (
            select YearLevel,THISSTUDENTSTANINE,
            CASE WHEN IndividualMathsPATStanineMarker<>0 THEN THISSTUDENTSTANINE ELSE 0 END AS MATHS,
            CASE WHEN IndividualListeningComprehensionPATStanineMarker<>0 THEN THISSTUDENTSTANINE ELSE 0 END AS LC,
            CASE WHEN IndividualReadingVocabularyPATStanineMarker<>0 THEN THISSTUDENTSTANINE ELSE 0 END AS RV,
            CASE WHEN IndividualReadingComprehensionPATStanineMarker<>0 THEN THISSTUDENTSTANINE ELSE 0 END AS RC,
            CASE WHEN IndividualPATPunctuationGrammarStanineMarker<>0 THEN THISSTUDENTSTANINE ELSE 0 END AS PG
            from uvSchoolBoxPATSTARSummary
            where ThisStudentID='{$this->externalUser['StudentID']}' and THISSTUDENTSTANINE<>0
            ) AS R
            GROUP BY R.YearLevel
            ORDER BY R.YearLevel DESC
        ";

        $result = $this->getDb()->getAll($query);

        /* Ensure everything is grouped by year
         */
        $stack = [];

        foreach ($result as $row) {
            $stack[$row['year']] = $row;
        }

        /* Decide if the table had any data
         */
        if (\count($stack)) {
            $this->hasData = true;
        }

        return $stack;
    }


    /**
     * Find out if this student has data from any year
     *
     * @return bool
     */
    public function hasDataFromAnyYear(): bool
    {
        $result = $this->findData();

        return (bool)\count($result);
    }
}
