<?php
/**
 * Crocodoc
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Crocodoc\Renderer\Strategy;


use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Assessment\Component\SubmissionBox\Submission;

class Crocodoc extends Base
{
    /**
     * The template file to inject
     *
     * @var string
     */
    protected $templateFile = 'submission/inlineGrading/injectables/views/crocodoc.html';

    /**
     * Control file, to determine how to return work
     *
     * @var string
     */
    protected $controlFile = 'submission/inlineGrading/injectables/controls/crocodoc-return-control.html';

    /**
     * Render injectable
     *
     * @return mixed|void
     */
    public function render()
    {
        $this->getPage()->setFile('injectable', $this->getTemplateFile());
        $this->getPage()->parse('INJECTED_INTERFACE_TEMPLATE', 'injectable');

        /* Upload File
         */
        $this->parseInjectableUpload();

        /* Control how Return Work is done
         */
        $this->getPage()->setFile('injectableControl', $this->getControlFile());

        /* Determine download url
         * This downloads it from crocodoc to Schoolbox
         */
        $downloadUrl = '/submission/inlineGrading/download/' . $this->getSubmission()->getId();

        $this->getPage()->setVar(
            array(
                'INJECTABLE_CONTROL_URL'           => $downloadUrl,
                'INJECTABLE_CONTROL_NEXT_URL'      => $this->getNextUrl(),
                'INJECTABLE_CONTROL_SUBMISSION_ID' => $this->getSubmission()->getId(),
            )
        );

        $this->getPage()->parse('INJECTED_INTERFACE_CONTROL', 'injectableControl');
    }


    /**
     * Typehint override
     *
     * @return Submission\File
     */
    protected function getSubmission()
    {
        return $this->submission;
    }

}