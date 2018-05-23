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
     * Render form for the import page.
     */
    public function getImport(): void
    {
        $this->response->content->set(
            $this->buildHtmlOutput('@courseImportAtlas/import.twig', [], 'Atlas Import', 'course')
        );
    }
}
