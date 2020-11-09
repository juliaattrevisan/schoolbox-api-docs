<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Meriden\Pastoral\CommandLineTool;

use Aura\Router\Router;
use Custom\Meriden\Pastoral\StudentInvolvement\Factory;
use Schoolbox\User;

/**
 * Class UrlGenerator
 */
class UrlGenerator
{
    /**
     * @var Router
     */
    private $router;


    /**
     * @var Factory
     */
    private $factory;


    /**
     * Constructor
     *
     * @param Router  $router
     * @param Factory $factory
     */
    public function __construct(Router $router, Factory $factory)
    {
        $this->router  = $router;
        $this->factory = $factory;
    }


    /**
     * Generate a list of URLs for this student.
     *
     * @param int  $year
     * @param User $student
     * @return array
     */
    public function generate(int $year, User $student): array
    {
        $results = [];

        // Find the page for the current year
        $params = [
            'student'     => $student,
            'id'          => $student->getId(),
            'year'        => $year,
        ];
        $results[] = [
            'type'   => 'index',
            'params' => $params,
            'url'    => $this->router->generate('customMeridenPastoral.index', $params),
        ];

        // Find the pages for each subject
        foreach ($this->factory->buildSubjectList($year, $student) as $subject) {
            $params = [
                'student'     => $student,
                'id'          => $student->getId(),
                'year'        => $year,
                'subject'     => $subject,
                'subjectCode' => $subject->getSubjectCode(),
            ];
            $results[] = [
                'type'   => 'subject',
                'params' => $params,
                'url'    => $this->router->generate('customMeridenPastoral.subject', $params),
            ];
        }

        return $results;
    }
}
