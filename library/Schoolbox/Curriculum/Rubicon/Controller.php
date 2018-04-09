<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Curriculum\Rubicon;

use Alaress\Web\Controller\Base;

/**
 * Rubicon Atlas controller.
 *
 * So far, contains only a test page.
 */
class Controller extends Base
{
    /**
     * Test page for Atlas Rubicon
     */
    public function getTestPage(): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->getResponse()->content->set(
            $this->buildHtmlOutput('@curriculumRubicon/testPage.twig', [])
        );
    }
}
