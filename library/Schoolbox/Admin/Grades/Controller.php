<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Grades;

use Alaress\Web\Controller\Base;

class Controller extends Base
{
    private const PAGE_CURRENT = 'grades';


    /**
     * Get index for modify grades form.
     *
     * @api   GET /adminv2/grades
     * @since 20.2
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getIndex(): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@adminGrades/index.twig', [
                    'pageCurrent' => self::PAGE_CURRENT,
                ]
            )
        );
    }


    /**
     * Modify admin grades.
     *
     * @api   POST /adminv2/grades
     * @since 20.2
     */
    public function postSaveGrades(): void
    {
        // do something
    }
}
