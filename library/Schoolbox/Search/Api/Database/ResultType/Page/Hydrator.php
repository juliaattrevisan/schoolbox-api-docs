<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Page;

use Alaress\Database\Predicate\Builder;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\Hydrator\BaseHydrator;
use Schoolbox\Search\Api\Hydrator\FullResult;
use Schoolbox\Url\Uri;
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
    private const BASE_TYPE = 'page';

    /**
     * Constructor
     *
     * @param Folder\Mapper $folderMapper Used to fetch folders for hydration.
     * @param Access        $access       Used to determine if the current user may view this page.
     */
    public function __construct(
        private Folder\Mapper $folderMapper,
        private Access $access,
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
    public function hydrate(string $keyword, array $matches, User $viewer): array
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
                if ($folder instanceof Folder\Model) {

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
                        $match->date,
                        null,
                        // Image: folder's image if there is one
                        null,
                        // Icon: dependent on folder type
                        'folder',
                        null,
                        // Context: this folder's parentage
                    );
                }

                // Nothing was here after all
                return null;
            },
            $acceptedMatches,
        );
        return array_filter($fullResults, fn (?FullResult $x) => isset($x));
    }

    /**
     * Build a predicate builder for the user search.
     *
     * @param array $userIds A list of user IDs to search for.
     * @param User  $viewer  The user viewing the search results: this may
     *                       filter out users from the result set.
     * @return Builder
     */
    private function buildPredicateBuilder(array $userIds, User $viewer): Builder
    {
        $pb = $this->folderMapper->getPredicateBuilder()->in('id', $userIds);

        // Exclude disabled users if that config isn't on
        if (!$this->config->get('show_disabled_users')) {
            $pb = $pb->isTrue('enabled');
        }

        // Exclude roles from search
        $excludedRoles = [];
        if ($viewer->getRole()->isGuest()) {
            $excludedRoles[] = Role::PARENT;
            $excludedRoles[] = Role::STUDENT;
        } elseif (!$viewer->getRole()->isStaff()) {
            if (!$this->config->get('parent_search_enable')) {
                $excludedRoles[] = Role::PARENT;
            }
            if (!$this->config->get('student_search_enable')) {
                $excludedRoles[] = Role::STUDENT;
            }
        }

        if (!empty($excludedRoles)) {
            $pb = $pb->notIn('g.group_type', $excludedRoles);
        }
        return $pb;
    }
}
