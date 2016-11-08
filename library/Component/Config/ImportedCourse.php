<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 */

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
        $courseImplementationMapper = new \Schoolbox\Learning\Course\DataModel\Implementation\Mapper();

        // has an internal block for the select element

        $allCourses = $this->getContext() ?
            $courseImplementationMapper->findByFolderId($this->getContext()->getId()) :
            new \Schoolbox\Collection();;


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

        foreach ($allCourses as $currentCourse) {
            $page->setVar(
                array(
                    'COMPONENT_CONFIG_COURSESELECT_NAME'       => Scrub::text($currentCourse->getSpecification()->getFolder()->getName()),
                    'COMPONENT_CONFIG_COURSESELECT_VALUE' 	    => $currentCourse->getId(),
                    'COMPONENT_CONFIG_COURSESELECT_SELECTED'   => $currentCourse->getId() == $configValue ? 'selected="selected"' : '',
                )
            );

            $page->parse("course_select_block", "course_select", true);
        }
    }
}
