<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox;

use Alaress\Authorizer\Authorizer;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Quiz;
use Schoolbox\Learning\Assessment\Quiz\Model;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Scrub;
use SitePage;

class Breadcrumbs
{
    public const TPL = 'breadcrumbTpl';

    /**
     * TODO: This function could be made static.
     *
     * @param SitePage $page
     * @param string   $handle
     * @param array    $data
     * @return void
     */
    public function renderIntoSitePage(SitePage $page, string $handle, array $data): void
    {
        $page->setFile(self::TPL, 'resources/breadcrumbs.html');
        $page->setBlock(self::TPL, 'breadcrumb', 'breadcrumb_block');
        $page->setVar('breadcrumb_block', '');
        $page->setBlock(self::TPL, 'breadcrumb_last', 'breadcrumb_last_block');
        $page->setVar('breadcrumb_last_block', '');

        $keys    = array_keys($data);
        $lastKey = end($keys);

        foreach ($data as $key => $crumb) {
            $page->setVar('BREADCRUMB_TEXT', Scrub::text($crumb['text']));
            $page->setVar('BREADCRUMB_CLASS', $crumb['class']);
            if ($key !== $lastKey) {
                $page->setVar('BREADCRUMB_HREF', $crumb['href']);
                $page->parse('breadcrumb_block', 'breadcrumb', true);
            } else {
                $page->parse('breadcrumb_last_block', 'breadcrumb_last', true);
            }
        }

        $page->parse($handle, self::TPL);
    }

    /**
     * I don't have a better place for this to live, sorry -_-;
     * TODO: This function could be made static.
     *
     * @param FolderModel $folder
     * @param string      $returnUrl
     * @return array
     * @throws Exception
     * @throws Deleted
     */
    public function getFolderCrumbs(FolderModel $folder, string $returnUrl = ''): array
    {
        if (!$returnUrl || !str_contains(urldecode(base64_decode($returnUrl)), '/course/modify/')) {
            // not a course modify path, or no return URL: just give us the homepage version
            return [
                [
                    'text'  => $folder->getName(),
                    'href'  => "/homepage/{$folder->getId()}",
                    'class' => '',
                ]
            ];
        }

        // course modify path: obtain parent and use that
        $parent = \Schoolbox\Resource\Folder\Mapper::getInstance()->findById($folder->getParent()->getId());

        return [
            [
                'text'  => 'Courses',
                'href'  => '/course/list',
                'class' => '',
            ],
            [
                'text'  => $parent->getName(),
                'href'  => "/course/modify/{$parent->getCourseSpecification()->getId()}",
                'class' => '',
            ],
            [
                'text'  => $folder->getName(),
                'href'  => "/homepage/{$folder->getId()}",
                'class' => '',
            ],
        ];
    }

    /**
     * @param Authorizer  $authorizer
     * @param Model       $quiz
     * @param int         $assessId
     * @param String      $assessTitle
     * @param String|null $sectionTitle
     * @return array
     * @see library/Schoolbox/Learning/Assessment/Breadcrumb/Factory::produceFromAssessment() - similar to this function but for aura-paradigm pages.
     *
     * Return an array of breadcrumbs suitable for use in self::renderIntoSitePage().
     * @throws \Exception
     */
    public static function getQuizCrumbs(
        Authorizer $authorizer,
        Quiz\Model $quiz,
        int $assessId,
        string $assessTitle,
        ?string $sectionTitle = null
    ): array {
        $breadcrumbs = [
            [
                'text'  => 'Learning',
                'href'  => '/learning/classes',
                'class' => ''
            ],
        ];

        // Maybe add more breadcrumbs here, depending on the type of the object to which the quiz belongs.
        $quizContext = $quiz->getContext();
        if ($quizContext instanceof Project) {
            // Quiz belongs to project.
            $project        = $quizContext;
            $projectContext = $project->getContext();

            if ($projectContext instanceof UnitImplementation) {
                // Quiz belongs to subject->unit->project (eg. '9ENGA'->'Romeo and Juliet'->'Project'), so add breadcrumbs for subject.
                $unitSpec       = $projectContext->getSpecification();
                $unitSpecFolder = $unitSpec->getFolder();
                $subjectFolder  = $projectContext->getFolder();
                $breadcrumbs[]  = [
                    'text'  => $subjectFolder->getTitle(),
                    'href'  => "/homepage/{$subjectFolder->getId()}",
                    'class' => ''
                ];
            } elseif ($projectContext instanceof UnitSpecification) {
                // Quiz belongs to unit->project specification, so cannot add breadcrumb for subject.
                // NOTE: UnitSpecifications are not attached to subjects.
                //       Think of them as templates of units that become implementations only when they are attached to subjects.
                $unitSpec       = $projectContext;
                $unitSpecFolder = $unitSpec->getFolder();
            } else {
                throw new \UnexpectedValueException("Unknown type '" . get_class($projectContext) . "' for project context.");
            }

            // Add breadcrumbs for unit and project.
            $breadcrumbs[] = [
                'text'  => $unitSpecFolder->getTitle(),
                'href'  => "/homepage/{$unitSpecFolder->getId()}",
                'class' => ''
            ];
            $breadcrumbs[] = [
                'text'  => $project->getTitle(),
                'href'  => "/learning/assessments/{$project->getId()}",
                'class' => ''
            ];
        } elseif ($quizContext instanceof UnitImplementation) {
            // Quiz belongs to subject->unit (eg. '9ENGA'->'Romeo and Juliet'), so add breadcrumbs for subject and unit.
            $unit           = $quizContext;
            $subjectFolder  = $unit->getFolder();
            $unitSpecFolder = $unit->getSpecification()->getFolder();
            $breadcrumbs[]  = [
                'text'  => $subjectFolder->getTitle(),
                'href'  => "/homepage/{$subjectFolder->getId()}",
                'class' => ''
            ];
            $breadcrumbs[] = [
                'text'  => $unitSpecFolder->getTitle(),
                'href'  => "/homepage/{$unitSpecFolder->getId()}",
                'class' => ''
            ];
        } elseif ($quizContext instanceof UnitSpecification) {
            // Quiz belongs to unit specification, so add breadcrumb for unit only (not subject).
            // NOTE: UnitSpecifications are not attached to subjects.
            //       Think of them as templates of units that become implementations only when they are attached to subjects.
            $unitSpec       = $quizContext;
            $unitSpecFolder = $unitSpec->getFolder();
            $breadcrumbs[]  = [
                'text'  => $unitSpecFolder->getTitle(),
                'href'  => "/homepage/{$unitSpecFolder->getId()}",
                'class' => ''
            ];
        } else {
            // Quiz belongs directly to subject (eg. 9ENGA), so add breadcrumb for subject only.
            $subjectFolder = $quizContext;
            $breadcrumbs[]  = [
                'text'  => $subjectFolder->getTitle(),
                'href'  => "/homepage/{$subjectFolder->getId()}",
                'class' => ''
            ];
        }

        // This is the breadcrumb for the quiz itself.
        $breadcrumbs[] = [
            'text'  => $assessTitle,
            'class' => '',
            'href'  => (
                // In some circumstances the user will not be authorised to view the 'status' page.  In that case, show the 'summary' page instead.
                $authorizer->isPathAuthorizedForUser(
                    'learningAssessment.getStatus', [
                        'assessment'   => $quiz,
                        'assessmentId' => $quiz->getId(),
                        'studentId'    => $_SESSION['user']->getId(),
                    ],
                    $_SESSION['user']
                ) ?
                "/learning/assessments/{$quiz->getId()}/status" :
                "/learning/assessments/{$quiz->getId()}"
            )
        ];

        if ($sectionTitle === null) {
            $breadcrumbs[] = [
                'text'  => 'Horizontal Marking',
                'href'  => '#',
                'class' => 'active'
            ];
        } else {
            $breadcrumbs[] = [
                'text'  => 'Horizontal Marking',
                'href'  => "/assess/reviewHorizontal/$assessId",
                'class' => 'active'
            ];
            $breadcrumbs[] = [
                'text'  => Scrub::text($sectionTitle),
                'href'  => "#",
                'class' => 'active'
            ];
        }

        return $breadcrumbs;
    }
}
