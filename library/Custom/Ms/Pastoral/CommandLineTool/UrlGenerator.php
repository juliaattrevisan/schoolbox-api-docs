<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Ms\Pastoral\CommandLineTool;

use Aura\Router\Generator;
use Custom\Ms\Pastoral\StudentInvolvement\Report\Factory;
use Psr\Http\Message\UriInterface;
use Schoolbox\User;

/**
 * Class UrlGenerator
 */
class UrlGenerator
{
    /**
     * @var Generator
     */
    private $routeGenerator;


    /**
     * @var Factory
     */
    private $factory;


    /**
     * Constructor
     *
     * @param Generator $routeGenerator
     * @param Factory   $factory
     */
    public function __construct(Generator $routeGenerator, Factory $factory)
    {
        $this->routeGenerator = $routeGenerator;
        $this->factory        = $factory;
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
     * @return array<string>
     * @throws \Aura\Router\Exception\RouteNotFound
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
                $results[] = (string)$baseUrl
                    ->withPath($this->routeGenerator->generate('customMeridenPastoral.index', $params))
                    ->withQuery(http_build_query($query));
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
                $results[] = (string)$baseUrl
                    ->withPath($this->routeGenerator->generate('customMeridenPastoral.subject', $params))
                    ->withQuery(http_build_query($query));
            }
        }
        return $results;
    }
}
