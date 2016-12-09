<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Personalised;

use Alaress\Web\Controller\Base;
use Schoolbox\User;

/**
 * Controller for the personalised learning screen.
 */
class Controller extends Base
{
    /**
     * Redirects to the personalised learning screen for the current user.
     *
     * @api GET /learning/personal/
     */
    public function getPersonalised()
    {
        $this->getResponse()->redirect->to(
            $this->getRouter()->generate(
                'learningPersonalised.getPersonalisedForStudent',
                [
                    'studentId' => $this->getUser()->getId(),
                ]
            )
        );
    }

    /**
     * Displays personalised learning screen for the given user.
     *
     * @api   GET /learning/personal/:studentId/
     * @param User $student
     */
    public function getPersonalisedForStudent(User $student)
    {
        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@learningPersonalised/personalised.twig',
                [
                    'student' => $student,
                ],
                'Personalised Learning for '.$student->formatName(),
                'submission'
            )
        );
    }
}
