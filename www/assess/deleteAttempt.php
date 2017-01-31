<?php
/**
 * Assessment Attempt deletion. Responses are all softdeleted if
 * the Attempt is softdeleted.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

/* Get a list of questions and answers to softdelete
 */

/* Check that you can delete these attempts first
 */
use Schoolbox\Application\Exception\NotFound;

$deleterer = new Deleterer();
$attemptsToDelete = [];

if (isset($_POST['userIds']) && isset($_POST['assessmentId'])) {

    $quiz = Schoolbox\Learning\Quiz\Mapper::getInstance()->findOneByColumn('assessment_id', $_POST['assessmentId']);

    $userAttemptIds = Schoolbox\Learning\Quiz\Attempt\Mapper::getInstance()->findIdsByColumnList([
        'user_id'     => $_POST['userIds'],
        'assess_id'   => $quiz->getId(),
        'reviewed_at' => null
    ]);

    foreach ($userAttemptIds as $attemptId) {
        $attemptsToDelete[] = $deleterer->getAttempt($attemptId);
    }
}

foreach ($_POST['attemptIds'] as $attemptId) {
    $attemptsToDelete[] = $deleterer->getAttempt($attemptId);
}

/* Multiple soft-delete
 */
foreach ($attemptsToDelete as $currentAttempt) {
    $deleterer->delete($currentAttempt);
}

SitePage::backToTheFuture(1);


class Deleterer
{
    /** @var Assess_Main  */
    protected $assessHandler;

    /** @var Assess_Attempt  */
    protected $attemptHandler;

    /** @var Assess_Response  */
    protected $responseHandler;

    /** @var Group  */
    protected $groupHandler;


    /**
     * Deleterer constructor.
     */
    public function __construct()
    {
        $this->assessHandler   = new Assess_Main();
        $this->attemptHandler  = new Assess_Attempt();
        $this->responseHandler = new Assess_Response();
        $this->groupHandler    = new Group();
    }


    /**
     * @param $attemptId
     * @return array|bool|null
     */
    public function getAttempt($attemptId)
    {
        if (is_numeric($attemptId)) {
            throw \Schoolbox\Error\Mapper::getInstance()->generateException(Schoolbox\Error\Mapper::ERROR_PERM_ATTEMPT_DELETE);
        }

        $attempt = $this->attemptHandler->getByID($attemptId);

        if (!$this->assessHandler->isValidUrlAccess($attempt['assess_id'])) {
            throw new NotFound();
        }

        $assess = $attempt['assess'] = $this->assessHandler->getByID($attempt['assess_id']);

        if (!$this->assessHandler->isOwner($assess['assess_id']) && ($this->groupHandler->getPermission($attempt['assess']['category_id']) < WRITE_ACCESS)) {
            throw Schoolbox\Error\Mapper::getInstance()->generateException(Schoolbox\Error\Mapper::ERROR_PERM_ATTEMPT_DELETE);
        }

        return $attempt;
    }


    /**
     * @param array $attempt
     */
    public function delete(array $attempt)
    {
        $this->responseHandler->deleteResponsesForAttempt($attempt['attempt_id']);
        $this->attemptHandler->update(array(
            'attempt_id' => $attempt['attempt_id'],
            'deleted_at' => 'NOW()'
        ));
    }
}
