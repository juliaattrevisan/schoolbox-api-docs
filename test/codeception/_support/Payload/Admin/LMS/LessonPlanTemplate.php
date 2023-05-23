<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Payload\Admin\LMS;

use Payload\Pastoral\FormTrait;
use Payload\PostTrait;

class LessonPlanTemplate extends \Payload\Request
{
    use PostTrait;
    use FormTrait;

    /**
     * create a lesson plan template
     * @param string $lessonPlanTemplateName
     * @param string $fieldName
     * @param string $visibility
     * @param string $body
     */
    public function __construct(string $lessonPlanTemplateName, array $lessons)
    {
        $this->setReferer("/adminv2/lesson-plan/template/create");
        //lesson plan title
        $this->setFormParam('title', $lessonPlanTemplateName);
        //create one or more lesson plan fields
        foreach ($lessons as $lesson) {
            $this->setFormParam('field[new' . $lesson[0] . '][title]', $lesson[1]);
            $this->setFormParam('field[new' . $lesson[0] . '][visibility]', $lesson[2]);
            $this->setFormParam('field[new' . $lesson[0] . '][body]', $lesson[3]);
        }
    }

    protected function getUrl(): string
    {
        return "/adminv2/lesson-plan/template/create";
    }
}
