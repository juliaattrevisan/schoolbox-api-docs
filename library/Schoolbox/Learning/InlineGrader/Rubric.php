<?php
/**
 * Rubric
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\InlineGrader;

use Schoolbox\Learning\Assessment\DueWork\RubricContext;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Permission\Access\Access;
use Schoolbox\User;

class Rubric
{

    /**
     * @var Participant
     */
    private $participant;

    /**
     * @var DueWork\Model
     */
    private $assessment;

    /**
     * @var User
     */
    private $currentUser;

    /**
     * @var IResponse
     */
    private $response;

    /**
     * @var bool
     */
    private $isInteractionLocked = false;

    /**
     * @var DueWork\RubricContext
     */
    private $rubricContext;

    /**
     * Rubric constructor.
     *
     * @param DueWork\Model  $assessment
     * @param Participant    $participant
     * @param User           $currentUser
     * @param null|IResponse $response
     * @param bool           $isInteractionLocked
     * @param RubricContext  $rubricContext
     */
    public function __construct(
        DueWork\Model $assessment,
        Participant $participant,
        User $currentUser,
        ?IResponse $response,
        bool $isInteractionLocked,
        RubricContext $rubricContext
    ) {
        $this->assessment = $assessment;
        $this->participant = $participant;
        $this->currentUser = $currentUser;
        $this->response = $response;
        $this->isInteractionLocked = $isInteractionLocked;
        $this->rubricContext = $rubricContext;
    }

    /**
     * Generate rubric data
     */
    public function generate()
    {

        // Should rubric be readonly or markable?
        if ($this->isInteractionLocked && !$this->currentUser->isSuperuser()) {
            return [
                'rubricContext' => $this->rubricContext->generate($this->assessment, $this->participant->getUser(), $this->currentUser, Access::getInstance(), false)
            ];
        } elseif ($this->response) {
            return [
                'rubricContext' => $this->rubricContext->generateMarkableForResponse($this->assessment, $this->participant->getUser(), $this->response->getId())
            ];
        } else {
            return [
                'rubricContext' => $this->rubricContext->generateMarkable($this->assessment, $this->participant->getUser())
            ];
        }
    }
}
