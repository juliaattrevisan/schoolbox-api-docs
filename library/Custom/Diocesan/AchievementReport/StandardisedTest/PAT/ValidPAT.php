<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\PAT;

use MSSQL;
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
     * @param MSSQL $db
     */
    public function __construct(MSSQL $db)
    {
        $this->db = $db;
    }


    /**
     * Certain year levels always display the tab.
     * Other year levels display the tab only if there is data there
     *
     * @param User  $user
     * @param array $externalUser
     * @return bool
     */
    public function isSatisifiedBy(User $user, array $externalUser): bool
    {

        /* Years 3-10 always show the tab
         */
        if (\in_array((int)$user->getYear(), [3,4,5,6,7,8,9,10], true) ) {
            return true;
        }

        /* 11-13 requires us to check constituent pieces - if any exist, the PAT tab exists
         */
        if (\in_array((int)$user->getYear(), [11,12,13], true)) {
            $tablePayload = (new Table\Data($user, $externalUser, $this->db, $this->year));
            $mathematicsPayload = (new Mathematics\Data($user, $externalUser, $this->db, $this->year))->findData();
            $listeningComprehensionPayload = (new ListeningComprehension\Data($user, $externalUser, $this->db, $this->year))->findData();
            $readingVocabularyPayload = (new ReadingVocabulary\Data($user, $externalUser, $this->db, $this->year))->findData();
            $readingComprehensionPayload = (new ReadingComprehension\Data($user, $externalUser, $this->db, $this->year))->findData();

            return
                (new Table\ValidHasData())->isSatisfiedBy((int)$this->user->getYear(), $tablePayload->hasData()) ||
                (new Mathematics\ValidHasData())->isSatisfiedBy((int)$this->user->getYear(), $mathematicsPayload->hasData()) ||
                (new ListeningComprehension\ValidHasData())->isSatisfiedBy((int)$this->user->getYear(), $listeningComprehensionPayload->hasData()) ||
                (new ReadingVocabulary\ValidHasData())->isSatisfiedBy((int)$this->user->getYear(), $readingVocabularyPayload->hasData()) ||
                (new ReadingComprehension\ValidHasData())->isSatisfiedBy((int)$this->user->getYear(), $readingComprehensionPayload->hasData());
        }

        /* Everyone else has nothing
         */
        return false;
    }
}
