<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Custom\Newic;

use Alaress\Database\Exception;
use External\Canvas\Redirect\AbstractRedirect;
use PDOException;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\User;

/**
 * Redirect from schoolbox homepage to canvas somewhere
 * by checking the folder code against the local canvas db cache
 *
 * If there's a matching course, manufacture a url to that place in
 * canvas instance; otherwise return null
 */
class Redirect extends AbstractRedirect
{
    /**
     * Newington identifier
     */
    public const SITE_SCHOOL_CODE = 'newic';


    /**
     * Canvas urls for Due Works and Quizzes respectively
     * Note that Due Works and Quizzes each have their own url
     */
    private const DUE_WORK_URL = '%s/assignments/%s';
    private const QUIZ_URL     = '%s/quizzes/%s';


    /**
     * The button renders with this text
     */
    private const NAME = 'View in Canvas';


    /**
     * For the given user, folder, assessment,
     * Find a corresponding url in canvas.
     *
     * If the user isn't allowed to go to canvas, immediately return null
     *
     * @param User        $user
     * @param IAssessable $assessment
     * @return array|null
     * @throws Exception
     * @throws PDOException
     */
    public function generateAssessmentRedirect(
        User $user,
        IAssessable $assessment
    ): ?array {
        /* If the currently logged in user is staff or students,
         * They can visit a link to canvas
         * Parents can't
         */
        if (!$user->getRole()->isStaff() && !$user->getRole()->isStudent()) {
            return null;
        }

        /* Go digging in the submission_box and assess tables via assessment_specification_implementation
         * to find the external_id that was given to this assessment's specification
         *
         * Assessment implementations don't get an external_id set when you run import.php
         * so you have to go ask its specification for it. I don't know what that is.
         * It's an Andyism
         */

        /* Due work
         */
        if ($assessment instanceof DueWork) {
            /* Find out if external_id exists in canvas as an assignment identifier
             */
            $query = "
                SELECT id AS canvas_id
                FROM assignments
                WHERE id = :assessmentCanvasId";

            if (!$this->canvasDb->isCanvasData2) {
                $query = "
                SELECT canvas_id
                FROM assignment_dim
                WHERE canvas_id = :assessmentCanvasId";
            }
            return $this->buildAssessmentUrl(
                $assessment,
                $query,
                $this->findDueWorkExternalId($assessment),
                self::DUE_WORK_URL
            );
        }

        /* Quiz
         */
        if ($assessment instanceof Quiz) {
            /* Find out if external_id exists in canvas as a quiz identifier
             */
            $query = "
                SELECT assignment_id AS canvas_id
                FROM quizzes
                WHERE assignment_id = :assessmentCanvasId";

            if (!$this->canvasDb->isCanvasData2) {
                $query = "
                SELECT canvas_id
                FROM quiz_dim
                WHERE canvas_id = :assessmentCanvasId";
            }
            return $this->buildAssessmentUrl(
                $assessment,
                $query,
                $this->findQuizExternalId($assessment),
                self::QUIZ_URL
            );
        }

        /* I suppose Tasks and Projects might fall under this clause somehow
         * no trabajo
         */
        return null;
    }


    /**
     * For the given Due Work assessment,
     * find its specification record and then grab its corresponding external_id value
     * returns null if it can't be found for whatever reason
     *
     * @param IAssessable $assessment
     * @return string|null
     * @throws PDOException
     */
    private function findDueWorkExternalId(IAssessable $assessment): ?string
    {
        $query = "
            SELECT sb2.external_id
            FROM submission_box sb
            INNER JOIN assessment_specification_implementation asi ON sb.assessment_id = asi.implementation_assessment_id
            INNER JOIN submission_box sb2 ON asi.specification_assessment_id = sb2.assessment_id
            WHERE sb.assessment_id = :assessmentId
        ";

        $statement = $this->db->prepare($query);
        $statement->execute([
                                'assessmentId' => $assessment->getId(),
                            ]);

        $row = $statement->fetch();

        /* We didn't find the external_id via the specification
         * I guess we just leave
         */
        if (!$row) {
            return null;
        }

        /* Otherwise, grab it and go
         */
        return $row['external_id'];
    }


    /**
     * For the given Quiz assessment,
     * find its specification record and then grab its corresponding external_id value
     * returns null if it can't be found for whatever reason
     *
     * @param IAssessable $assessment
     * @return string|null
     * @throws PDOException
     */
    private function findQuizExternalId(IAssessable $assessment): ?string
    {
        $query = "
            SELECT a2.external_id
            FROM assess a
            INNER JOIN assessment_specification_implementation asi ON a.assessment_id = asi.implementation_assessment_id
            INNER JOIN assess a2 ON asi.specification_assessment_id = a2.assessment_id
            WHERE a.assessment_id = :assessmentId
        ";

        $statement = $this->db->prepare($query);
        $statement->execute([
                                'assessmentId' => $assessment->getId(),
                            ]);

        $row = $statement->fetch();

        /* We didn't find the external_id via the specification
         * I guess we just leave
         */
        if (!$row) {
            return null;
        }

        /* Otherwise, grab it and go
         */
        return $row['external_id'];
    }


    /**
     * Build a (url, name) tuple corresponding to a canvas url for the given assessment
     *
     * @param IAssessable $assessment
     * @param string      $query
     * @param string|null $canvasId
     * @param string      $canvasUrl
     * @return array|null
     * @throws Exception
     * @throws PDOException
     */
    private function buildAssessmentUrl(IAssessable $assessment, string $query, ?string $canvasId, string $canvasUrl): ?array
    {
        /* Didn't find a canvas identifier, thus I can't build the url; just leave
         */
        if (null === $canvasId) {
            return null;
        }

        /* For the given duework/quiz assessment, look at the external id
         * If there's one there, try to look in canvas cache db
         * to see where it should go. You'll need $folder to figure
         * out the class code too.
         *
         * The existing function generateFirstAvailableRedirect()
         * can be used to generate the first half of the url
         * to which you can append the suffixes
         * /assignments/123456 or /quizzes/123456
         */
        $prefix = $this->generateRedirect($assessment->getBaseContext()->getFolder());

        /* If we didn't find a meaningful $prefix, the folder code doesn't exist in canvas
         * so we can't construct the first part of the url; get the heck out of here
         * Otherwise, continue and append the assessment suffix
         */
        if (null === $prefix) {
            return null;
        }

        /* Otherwise continue shanking
         */
        $statement = $this->canvasDb->getConnector()->prepareQuery($query);

        $statement->execute([
                                'assessmentCanvasId' => $canvasId,
                            ]);

        $row = $statement->fetch();

        /* No assignment found; exit
         */
        if (!$row) {
            return null;
        }

        /* A tuple containing the name of the third party application which spawned this
         * assessment, since external_id is system agnostic - not everyone uses Canvas
         */
        return [
            'url'  => sprintf(
                $canvasUrl,
                rtrim($prefix, '/'),
                $row['canvas_id']
            ),
            'name' => self::NAME,
        ];
    }
}
