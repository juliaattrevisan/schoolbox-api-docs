<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course\Import\Atlas;

use Alaress\Web\Controller\Base as BaseController;

class Controller extends BaseController
{
    /**
     * @var ApiBridge
     */
    private $apiBridge;


    public function __construct(ApiBridge $apiBridge)
    {
        $this->apiBridge = $apiBridge;
    }


    /**
     * Render form for the import page.
     */
    public function getImport(): void
    {
        $this->response->content->set(
            $this->buildHtmlOutput('@courseImportAtlas/import.twig', [], 'Atlas Import', 'course')
        );
    }


    /**
     * POST action for `getImport` mage form.
     */
    public function postImport(): void
    {
        if ($this->request->post->getBool('part-1')) {
            $this->postImportPart1();
            return;
        }

        // Part 2: I have chosen units, now import them
        if ($this->request->post->getBool('part-2')) {
            $this->postImportPart2();
            return;
        }
    }


    /**
     * POST part 1: I have chosen a plan, now show the parts
     */
    private function postImportPart1(): void
    {
        $this->response->content->set(
            $this->buildJsonOutput(['okay' => 'okay'])
        );
    }


    /**
     * POST part 2: I have chosen parts of a plan, now import them
     */
    private function postImportPart2(): void
    {
        $this->response->redirect->afterPost($this->router->generate('course.courseList'));
    }
}
