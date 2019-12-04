<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest;

use Schoolbox\User;

/**
 * Class ValidPAT
 *
 * Decide whether the PAT navigation tab should display.
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest
 */
class ValidPAT
{

    /**
     * @var User
     */
    protected $user;


    /**
     * An externalUser array
     *
     * @var array
     */
    protected $externalUser = [];


    /**
     * MSSQL db handler
     *
     * @var MSSQL
     */
    protected $db;


    /**
     * The year for which to obtain data
     *
     * @var string
     */
    protected $year = 0;


    /**
     * ValidPAT constructor.
     *
     * @param User     $user
     * @param array    $externalUser
     * @param MSSQL    $db
     * @param int|null $year
     */
    public function __construct(MSSQL $db)
    {
        $this->db = $db;
    }


    /**
     * Certain year levels always display the tab.
     * Other year levels display the tab only if there is data there
     *
     * @return bool
     */
    public function isSatisifiedBy(User $user, array $externalUser): bool
    {
        /* Years 3-10 always show the tab
         */
        if (\in_array($yearLevel, [3,4,5,6,7,8,9,10], true) ) {
            return true;
        }

        /* 11-13 requires us to check constituent pieces - if any exist, the PAT tab exists
         */
        if (\in_array($yearLevel, [11,12,13], true)) {

            $mathematicsPayload = (new PATMathematics($user, $externalUser, $this->db, $this->year))->findData();
            $listeningComprehensionPayload = (new PATListeningComprehension($user, $externalUser, $this->db, $this->year))->findData();
            $readingVocabularyPayload = (new PATReadingVocabulary($user, $externalUser, $this->db, $this->year))->findData();
            $readingComprehensionPayload = (new PATReadingComprehension($user, $externalUser, $this->db, $this->year))->findData();

            return
                (new ValidPATMathematics())->isSatisfiedBy($this->user->getYear(), $mathematicsPayload->hasData()) ||
                (new ValidPATListeningComprehension())->isSatisfiedBy($this->user->getYear(), $listeningComprehensionPayload->hasData()) ||
                (new ValidPATReadingVocabulary())->isSatisfiedBy($this->user->getYear(), $readingVocabularyPayload->hasData()) ||
                (new ValidPATReadingComprehension())->isSatisfiedBy($this->user->getYear(), $readingComprehensionPayload->hasData());
        }

        /* Everyone else has nothing
         */
        return false;
    }
}
