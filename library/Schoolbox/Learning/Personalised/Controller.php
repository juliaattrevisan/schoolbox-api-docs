<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Personalised;

use Alaress\Web\Controller\Base;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\User;

/**
 * Controller for the personalised learning screen.
 */
class Controller extends Base
{
    /**
     * @var FolderMapper
     */
    protected $folderMapper;

    /**
     * Constructor
     *
     * @param FolderMapper $folderMapper
     */
    public function __construct(FolderMapper $folderMapper)
    {
        $this->folderMapper = $folderMapper;
    }

    /**
     * Redirects to the personalised learning screen for the current user.
     *
     * @api GET /learning/personalised/
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
     * @api   GET /learning/personalised/:studentId/
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

    /**
     * Retrieves the resources to display in this student's calendar.
     *
     * TODO consider not AJAXing this
     *
     * @api   GET /learning/personalised/resources/:studentId/
     * @param User $student
     */
    public function getResourcesForStudent(User $student)
    {
        $resultSet = [];

        foreach ($this->folderMapper->findForUser($student) as $folder) {
            $baseResult = [
                'folderId'    => $folder->getId(),
                'folderTitle' => $folder->getTitle(),
            ];

            // Resources for: units, content areas, projects, activities
            $resultSet[] = [
                'id'    => $folder->getId().'-units',
                'title' => 'Units',
            ] + $baseResult;
            $resultSet[] = [
                'id'    => $folder->getId().'-contentAreas',
                'title' => 'Content Areas',
            ] + $baseResult;
            $resultSet[] = [
                'id'    => $folder->getId().'-projects',
                'title' => 'Projects',
            ] + $baseResult;
            $resultSet[] = [
                'id'    => $folder->getId().'-activities',
                'title' => 'Activities',
            ] + $baseResult;
        }

        $this->getResponse()->content->set($this->buildJsonOutput($resultSet));
    }
}
