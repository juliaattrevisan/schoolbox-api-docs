<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Ms\Pastoral\CommandLineTool;

use Aura\Router\Router;
use Custom\Ms\Pastoral\StudentInvolvement\Report\Factory;
use Schoolbox\User;
use Laminas\Uri\UriInterface;

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
     * @param string|null  $roleTypeOverride   If provided, show only results
     *                                         for this role type
     * @param int|null     $graphWidthOverride If provided, hardcode graph width
     *                                         to this value
     * @return array|string[]
     */
    public function generate(
        UriInterface $baseUrl,
        int $year,
        User $student,
        ?string $roleTypeOverride,
        ?int $graphWidthOverride
    ): array {
        $query = [];
        if (isset($roleTypeOverride)) {
            $query['role-type'] = $roleTypeOverride;
        }
        if (isset($graphWidthOverride)) {
            $query['graph-width'] = $graphWidthOverride;
        }

        $results           = [];
        $hasSubjectReports = false;
        $tutorGroupList    = $this->factory->buildTutorGroupList($student);
        foreach ($tutorGroupList as $tutorGroup) {
            if ((int)$tutorGroup->getYear() === $year) {
                $hasSubjectReports = $tutorGroup->hasSubjectReports();

                // Find the page for this year
                $params    = [
                    'student' => $student,
                    'id'      => $student->getId(),
                    'year'    => $year,
                ];
                $results[] = $baseUrl
                    ->setPath($this->router->generate('customMeridenPastoral.index', $params))
                    ->setQuery($query)
                    ->toString();
                break;
            }
        }


        // Find the subject pages, only if the tutor group report has them
        if ($hasSubjectReports) {
            $subjectList = $this->factory->buildSubjectList($year, $student);
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
                    ->setQuery($query)
                    ->toString();
            }
        }
        return $results;
    }
}
