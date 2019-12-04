<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest;

/**
 * Class PATTable
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest
 */
class PATTable extends Base
{

    /**
     * Whether there was any data found
     *
     * @var bool
     */
    private $hasData = false;


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

            $this->hasData = true;
        }

        return $stack;
    }


    /**
     * @return bool
     */
    public function hasData(): bool
    {
        return $this->hasData;
    }
}
