<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Meriden\Pastoral\CommandLineTool;

use Aura\Router\Router;
use Custom\Meriden\Pastoral\StudentInvolvement\Factory;
use Custom\Meriden\Pastoral\StudentInvolvement\Subject;
use Schoolbox\User;
use Zend\Uri\UriInterface;

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
     * @param UriInterface $baseUrl
     * @param int          $year
     * @param User         $student
     * @param string|null  $roleTypeOverride If provided, show only results for
     *                                      this role type
     * @return array|string[]
     */
    public function generate(UriInterface $baseUrl, int $year, User $student, ?string $roleTypeOverride): array
    {
        $queryString = $roleTypeOverride
            ? ('?' . http_build_query(['role-type' => $roleTypeOverride]))
            : '';

        // Find the page for the current year
        $params  = [
            'student' => $student,
            'id'      => $student->getId(),
            'year'    => $year,
        ];
        $results = [
            $baseUrl
                ->setPath($this->router->generate('customMeridenPastoral.index', $params))
                ->setQuery($queryString)
                ->toString(),
        ];

        // Find the pages for each subject: sort by alphabetical order
        $subjectList = $this->factory->buildSubjectList($year, $student);
        usort(
            $subjectList,
            static function (Subject $a, Subject $b) {
                return strnatcasecmp($a->getName(), $b->getName());
            }
        );

        foreach ($subjectList as $subject) {
            $params    = [
                'student'     => $student,
                'id'          => $student->getId(),
                'year'        => $year,
                'subject'     => $subject,
                'subjectCode' => $subject->getSubjectCode(),
            ];
            $results[] = $baseUrl
                ->setPath($this->router->generate('customMeridenPastoral.subject', $params))
                ->setQuery($queryString)
                ->toString();
        }

        return $results;
    }
}
