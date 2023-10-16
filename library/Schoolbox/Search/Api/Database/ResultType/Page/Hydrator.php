<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Page;

use Alaress\Authorizer\Authorizer;
use Aura\Router\Exception\RouteNotFound;
use Aura\Router\Generator;
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\Mapper;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\Hydrator\BaseHydrator;
use Schoolbox\Search\Api\Hydrator\FullResult;
use Schoolbox\Search\Api\SearchFilter;
use Schoolbox\User;

/**
 * Hydrate a set of raw page results into a set of full page results.
 *
 * Page results are restricted by whether the current user has read permission
 * to the given page.
 */
class Hydrator extends BaseHydrator
{
    /**
     * This hydrator's base type.
     */
    public const BASE_TYPE = 'page';

    /**
     * Constructor
     *
     * @param Mapper           $folderMapper     Used to fetch folders for hydration.
     * @param UserAccessMapper $userAccessMapper
     * @param Access           $access           Used to determine if the current user may view this page.
     * @param Generator        $routeGenerator   Used to build a route to the result search for pages.
     * @param Authorizer       $authorizer       Used to find page access permissions.
     */
    public function __construct(
        private Folder\Mapper $folderMapper,
        private UserAccessMapper $userAccessMapper,
        private Access $access,
        private Generator $routeGenerator,
        private Authorizer $authorizer
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getBaseType(): string
    {
        return self::BASE_TYPE;
    }

    /**
     * @inheritdoc
     */
    public function hydrate(string $keyword, SearchFilter\DataObject $filter, array $matches, User $viewer): array
    {
        // Find all folders which match $matches
        $acceptedMatches = array_filter($matches, [$this, 'accepts']);

        // Which IDs?
        $folderIds = array_map(fn (RawResult $match) => $match->id, $acceptedMatches);
        if (empty($folderIds)) {
            return [];
        }

        $folders = $this->folderMapper->findByColumn('id', $folderIds);

        // Map those folders to full results
        $fullResults = array_map(
            function (RawResult $match) use ($folders, $viewer) {
                $folder = $folders->getBy('id', $match->id);

                if (!($folder instanceof Folder\Model)) {
                    return null;
                }

                // Can the viewer even view this folder?
                if ($this->access->getFolderAccess($viewer, $folder->getId()) < UserAccess::ACCESS_READ) {
                    return null;
                }

                return new FullResult(
                    $match,
                    $match->id,
                    $folder->getTitle(),
                    '/homepage/' . $match->id,
                    $match->type,
                    $match->relevance,
                    null,
                    null,
                    null,
                    // Image: folder's image if there is one
                    $folder->getCoverImage()
                        ? $this->buildStorageImageUrl($folder->getCoverImage())->__toString()
                        : null,
                    // Icon: dependent on folder type?
                    match ($match->type) {
                        self::BASE_TYPE . '.course'    => 'course',
                        self::BASE_TYPE . '.unit'      => 'unit',
                        self::BASE_TYPE . '.class'     => 'teacher',
                        self::BASE_TYPE . '.homepage'  => 'home',
                        default                        => 'folder',
                    },
                    null,
                    null,
                    $this->folderMapper->findParents($folder, null, true)->getAll(),
                    $this->getSearchResultLinks($folder, $viewer),
                    // TODO: Figure out what to do with this.
                    //[
                    //    'subResults' => $this->buildBaseUrl()
                    //        ->withPath(
                    //            $this->routeGenerator->generate(
                    //                'searchApi.getSearchWithinFolder',
                    //                [
                    //                    'folder'   => $folder,
                    //                    'folderId' => $folder->getId(),
                    //                ]
                    //            )
                    //        )
                    //        ->withQuery(http_build_query(['keyword' => $keyword]))
                    //        ->__toString(),
                    //],
                );
            },
            $acceptedMatches,
        );
        return array_filter($fullResults, fn (?FullResult $x) => isset($x));
    }


    /**
     * Return an array of sub-arrays, each sub-array containing info for one link.
     * The links will be in alphabetical order of title.
     *
     * @param Folder\Model $folder
     * @param User  $viewer
     * @return array
     * @throws RouteNotFound
     * @throws Exception
     */
    private function getSearchResultLinks(Folder\Model $folder, User $viewer): array
    {
        $links = [];

        $isGroupPage  = $folder->isGroup();
        $isClassPage  = $folder->isClass();
        $isCoursePage = $folder->hasCourseSpecification();

        $folderAccessLevel = $this->access->getFolderAccess($viewer, $folder->getId());

        // Add links depending on page type and permissions, in alphabetical order of link title.

        if ($isClassPage) {
            $route = 'learning.getClassListPage';
            $params = ['folderId' => $folder->getId(), 'folder' => $folder];
            if ($this->authorizer->isPathAuthorizedForUser($route, $params, $viewer)) {
                $links[] = [
                    'title' => 'Class List',
                    'icon' => 'icon-class-list',
                    'url' => $this->routeGenerator->generate($route, $params),
                ];
            }
            $route = 'learningMarkbook.getMarkbookForClass';
            // Params needed here are the same as set above.
            if ($this->authorizer->isPathAuthorizedForUser($route, $params, $viewer)) {
                $links[] = [
                    'title' => 'Class Markbook',
                    'icon' => 'icon-curriculum',
                    'url' => $this->routeGenerator->generate($route, $params),
                ];
            }
        }

        if ($isCoursePage) {
            $courseSpec = $folder->getCourseSpecification();
            $route = 'course.modifyCourse';
            $params = ['courseId' => $courseSpec->getId(), 'course' => $courseSpec];
            if ($this->authorizer->isPathAuthorizedForUser($route, $params, $viewer)) {
                $links[] = [
                    'title' => 'Modify Course',
                    'icon' => 'icon-modify',
                    'url' => $this->routeGenerator->generate($route, $params),
                ];
            }
        }

        if ($folderAccessLevel >= UserAccess::ACCESS_WRITE) {
            $links[] = [
                'title' => 'Modify Folder',
                'icon' => 'icon-modify',
                'url' => $this->buildBaseUrl()
                    ->withPath("/resource/folder/{$folder->getId()}/modify")
                    ->__toString(),
            ];
        }

        if ($isGroupPage) {
            $route = 'group.getModify';
            $params = ['folderId' => $folder->getId(), 'folder' => $folder];
            if ($this->authorizer->isPathAuthorizedForUser($route, $params, $viewer)) {
                $links[] = [
                    'title' => 'Manage Group',
                    'icon' => 'icon-group',
                    'url' => $this->routeGenerator->generate($route, $params),
                ];
            }
        }

        return $links;
    }
}
