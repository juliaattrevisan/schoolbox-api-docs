<?php
/**
 * None
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Crocodoc\Renderer\Strategy;

class Image extends Base
{

    /**
     * The template file to inject
     *
     * @var string
     */
    protected $templateFile = 'submission/inlineGrading/injectables/views/image.html';

    /**
     * Control file, to determine how to return work
     *
     * @var string
     */
    protected $controlFile = 'submission/inlineGrading/injectables/controls/redirect-return-control.html';

    /**
     * Render injectable
     *
     * @return mixed|void
     */
    public function render()
    {
        $this->getPage()->setFile('injectable', $this->getTemplateFile());
        $this->getPage()->parse('INJECTED_INTERFACE_TEMPLATE', 'injectable');

        /* Make the image display
         */
        $this->getPage()->setVar(
            array(
                'INJECTABLE_SUBMISSION_ID' => $this->getSubmission()->getId(),
            )
        );

        /* Upload File
         */
        $this->parseInjectableUpload();

        /* Control how Return Work is done
         */
        $this->getPage()->setVar(
            array(
                'INJECTED_INTERFACE_CONTROL' => ''
            )
        );
    }
}