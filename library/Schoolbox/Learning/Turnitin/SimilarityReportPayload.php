<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin;

use Aura\Router\Router;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\UserMapper;

/**
 * Decorate a payload for the Similarity Report report
 */
class SimilarityReportPayload
{

    /**
     * These consts were defined in twig alone; they don't exist in backend as concepts
     * The implementation here mirrors that which is used in Status screen's jewellery
     * This is an obscure place for these concepts, but they are purely cosmetic ux things
     */
    private const STATUS_INCOMPLETE = 'incomplete';
    private const STATUS_FAILED = 'failed';
    private const STATUS_PENDING = 'pending';
    private const STATUS_REVIEWED = 'reviewed';


    /**
     * @var File\Mapper
     */
    private File\Mapper $turnitinFileMapper;


    /**
     * @var UserMapper
     */
    private UserMapper $userMapper;

    /**
     * @var Router
     */
    protected Router $router;


    /**
     * Constructor
     *
     * @param File\Mapper $turnitinFileMapper
     * @param UserMapper  $userMapper
     * @param Router      $router
     */
    public function __construct(File\Mapper $turnitinFileMapper, UserMapper $userMapper, Router $router)
    {
        $this->turnitinFileMapper = $turnitinFileMapper;
        $this->userMapper = $userMapper;
        $this->router = $router;
    }


    /**
     * Generate a payload for $assessment's Similarity Report report
     *
     * @param IAssessable $assessment
     * @return array
     * @throws \PDOException|\Aura\Router\Exception\RouteNotFound
     */
    public function generate(IAssessable $assessment): array
    {
        /* This partial payload is indexed by user ids
         */
        $payload = $this->turnitinFileMapper->findMostRecentForAssessment($assessment->getId());

        /* Decorate it with some user metadata
         */
        $users = $this->userMapper->findByColumn('id', array_keys($payload))->indexBy('id');

        foreach ($users as $userId => $user) {
            $payload[$userId]['user'] = [
                'id' => $userId,
                'title' => $user->getTitle(),
                'firstname' => $user->getFirstname(),
                'lastname' => $user->getLastname(),
                'fullname' => $user->getFullName(),
                'role' => $user->getRole()->getName()
            ];
        }

        /* Find the routes for each file's Inline Grader and Turnitin Reports
         */
        foreach ($payload as $userId => &$data) {
            $data['submission']['_links']['inline_grader'] = $this->router->generate('learningDueWorkResponse.getParticularResponse', [
                'assessmentId' => $assessment->getId(),
                'assessment' => $assessment,
                'studentId' => $userId
            ]);

            foreach ($data['files'] as &$file) {
                $file['_links']['turnitin_report'] = $this->router->generate('learningDueWorkResponse.getTurnitinReport', [
                    'assessmentId' => $assessment->getId(),
                    'assessment' => $assessment,
                    'responseId' => $data['submission']['id'],
                    'turnitinSubId' => $file['turnitin']['turnitin_submission_id']
                ]);
            }

            unset($file);
        }

        unset($data);

        /* Fish out a user's participation record to determine their response/feedback status (!!)
         * I really wish I didn't have to use the andy hammer, but oh well. Time to go nuclear
         */
        foreach ($assessment->getAllParticipants() as $participant) {
            /* If this participanty wasn't in the partial payload, they don't have a submission yet
             * There is no reason to investigate their status any further
             */
            if (!isset($payload[$participant->getUser()->getId()])) {
                continue;
            }

            /* These status strings are derived from frontend templates
             * TODO SURELY we have these in backend land somewhere, right
             */
            if ($participant->isResponseRequired()) {
                /* This maps to assessment/templates/status.twig "statusIncomplete"
                 */
                $status = self::STATUS_INCOMPLETE;
            } elseif ($participant->isResubmissionRequired()) {
                /* This maps to assessment/templates/dueWork/status.twig "statusFailed"
                 */
                $status = self::STATUS_FAILED;
            } elseif ($participant->isFeedbackRequired()) {
                /* This maps to assessment/templates/status.twig "statusPending"
                 */
                $status = self::STATUS_PENDING;
            } else {
                /* This maps to assessment/templates/status.twig "statusReviewed"
                 */
                $status = self::STATUS_REVIEWED;
            }

            /* Shank it in
             * Note that this mirrors the frontend implementation in assessment/templates/dueWork/status.twig
             * and its superclass template assessment/templates/status.twig
             */
            $payload[$participant->getUser()->getId()]['submission']['status'] = $status;
        }

        /* Ditch the array keys then json it
         * Over to frontend land
         */
        return array_values($payload);
    }
}
