<?php

/**
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 */

use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;

class Component_Config_ImportedCourse extends Component_Config_Abstract
{

    /**
     * Parse
     *
     * @param SitePage $page
     * @param string $key
     * @param string $keyblock
     * @param mixed $configValue
     * @param Component_ControllerAbstract $component
     */
    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        $courseImplementationMapper = new CourseImplementationMapper();

        // has an internal block for the select element

        $options = ['order' => ['created_at' => 'DESC']];

        $allCourseImpls = $this->getContext() ?
            $courseImplementationMapper->findByFolderId($this->getContext()->getId(), $options) :
            new \Schoolbox\Collection();
        ;


        $page->setBlock($keyblock, 'course_select', 'course_select_block');
        $page->setVar('course_select_block', '');


        $page->setVar(
            array(
                'COMPONENT_CONFIG_COURSESELECT_NAME'       => Scrub::text(' (Import New Course) '),
                'COMPONENT_CONFIG_COURSESELECT_VALUE'      => '0',
                'COMPONENT_CONFIG_COURSESELECT_SELECTED'   => '',
            )
        );

        $page->parse("course_select_block", "course_select", true);

        // Detect if we have imported the same CourseSpec multiple times on this page.
        // If so, append the date of import in brackets so the user can distinguish them.

        /**
         * Indexed by CourseSpec.id
         * @var int[] $courseSpecCounts
         */
        $courseSpecCounts = [];
        foreach ($allCourseImpls as $currentCourse) {
            $courseSpecCounts[$currentCourse->getSpecification()->getId()]++;
        }

        foreach ($allCourseImpls as $currentCourse) {
            $courseName = Scrub::text($currentCourse->getSpecification()->getFolder()->getName());
            if ($courseSpecCounts[$currentCourse->getSpecification()->getId()] >= 2) {
                $courseName .= " - Imported " . $currentCourse->getCreatedAt()->getShort();
            }

            $page->setVar(
                array(
                    'COMPONENT_CONFIG_COURSESELECT_NAME'       => $courseName,
                    'COMPONENT_CONFIG_COURSESELECT_VALUE'      => $currentCourse->getId(),
                    'COMPONENT_CONFIG_COURSESELECT_SELECTED'   => $currentCourse->getId() == $configValue ? 'selected="selected"' : '',
                )
            );

            $page->parse("course_select_block", "course_select", true);
        }
    }
}
