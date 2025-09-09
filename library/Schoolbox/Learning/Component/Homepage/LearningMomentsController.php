<?php

namespace Schoolbox\Learning\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class LearningMomentsController extends Component_Homepage_ControllerAbstract
{
    /**
     * Business logic to collate data ready for output
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute(): bool
    {
        if (!parent::execute()) {
            return false;
        }

        // Only show if learning moments are enabled.
        if (!$this->getConfigLoader()->get(ConfigMapper::NAME_LEARNING_MOMENTS_ENABLED)) {
            return false;
        }

        // Do not show to parents.
        if ($this->getUser()->getRole()->isParent()) {
            return false;
        }

        // Only show to students if learning moments are enabled for the student's year level.
        if ($this->getUser()->getRole()->isStudent() && !$this->getUser()->getYear()?->getLearningMomentsEnabled()) {
            return false;
        }

        return true;
    }

    /**
     * Output the data we collected in execute()
     *
     * @param String $templateHandle
     * @return bool
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function renderTemplate($templateHandle): bool
    {
        /**
         * If the current user is a staff member with access to create learning moments for students,
         * then find all the students that they can create learning moments for
         */
        $students = null;
        if ($this->getUser()->getRole()->isStaff() && $this->getUser()->getRole()->getAccessEvidence()) {
            /* Find a set of users who are somehow enrolled in $folder as students,
             * and/or involved in $folder as group members
             *
             * Note that a folder can be both a group and a class-coded folder which derives its
             * members from the Externals
             */
            $userAccess = $this->getMapperFactory()->get(UserAccessMapper::class);
            $students = $userAccess->findUsersInCategory($this->getSource(), \Schoolbox\Role::STUDENT);

            /* we need to filter out any students that you can't actually create for, else you'll get a 403 */
            $students = $students->filter(fn(\Schoolbox\User $student) => $student->getLearningMomentsEnabled());
        };

        return $this->renderTwigTemplate(
            $templateHandle,
            '@learning/component/learningMomentsHomepage.twig',
            [
                'user' => $this->getUser(),
                'component' => $this,
                'folder' => $this->getSource(),
                'students' => $students,
            ]
        );
    }
}
