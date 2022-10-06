<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox;

use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Quiz;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Scrub;
use SitePage;

class Breadcrumbs
{
    const TPL = 'breadcrumbTpl';

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
     * @param Quiz\Model  $assessment
     * @param int         $assessId
     * @param String      $assessTitle
     * @param String|null $sectionTitle
     * @return array
     *
     * Return an array of breadcrumbs suitable for use in self::renderIntoSitePage().
     * @throws \Exception
     */
    public static function getAssessmentCrumbs(
        Quiz\Model $assessment,
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
        $context = $assessment->getContext();
        if ($context instanceof Project) {
            // Quiz belongs to subject->unit->project (eg. '9ENGA'->'Romeo and Juliet'->'Project'), so add breadcrumbs for subject, unit, and project.
            $project        = $context;
            $projectContext = $project->getContext();

            if ($projectContext instanceof UnitImplementation) {
                $unitSpec       = $projectContext->getSpecification();
                $unitSpecFolder = $unitSpec->getFolder();
                $subjectFolder  = $projectContext->getFolder();
                $breadcrumbs[]  = [
                    'text'  => $subjectFolder->getTitle(),
                    'href'  => "/homepage/{$subjectFolder->getId()}",
                    'class' => ''
                ];
            } elseif ($projectContext instanceof UnitSpecification) {
                // NOTE: UnitSpecifications are not attached to subjects.
                //       Think of them as templates of units that become implementations only when they are attached to subjects.
                $unitSpec       = $projectContext;
                $unitSpecFolder = $unitSpec->getFolder();
            } else {
                throw ErrorMapper::getInstance()->generateException(
                    0, null, "Unknown type '" . get_class($projectContext) . "' for project context."
                );
            }

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
        } elseif ($context instanceof UnitImplementation) {
            // Quiz belongs to subject->unit (eg. '9ENGA'->'Romeo and Juliet'), so add breadcrumbs for subject and unit.
            $unit           = $context;
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
        } elseif ($context instanceof UnitSpecification) {
            // Quiz belongs to subject->unit specification (eg. spec for '9ENGA'->'Romeo and Juliet'), so add breadcrumb for unit only.
            // NOTE: UnitSpecifications are not attached to subjects.
            //       Think of them as templates of units that become implementations only when they are attached to subjects.
            $unitSpec       = $context;
            $unitSpecFolder = $unitSpec->getFolder();
            $breadcrumbs[]  = [
                'text'  => $unitSpecFolder->getTitle(),
                'href'  => "/homepage/{$unitSpecFolder->getId()}",
                'class' => ''
            ];
        } else {
            // Quiz belongs directly to subject (eg. 9ENGA), so add breadcrumb for subject only.
            $subjectFolder = $context;
            $breadcrumbs[]  = [
                'text'  => $subjectFolder->getTitle(),
                'href'  => "/homepage/{$subjectFolder->getId()}",
                'class' => ''
            ];
        }

        // This is the breadcrumb for the assessment itself.
        $breadcrumbs[] = [
            'text'  => $assessTitle,
            'href'  => "/learning/assessments/{$assessment->getId()}/status",
            'class' => ''
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
