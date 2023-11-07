<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @textbox      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Textbox;

use Alaress\Authorizer\Authorizer;
use Component_Config_Collapse;
use Component_Config_ShowComponentTitle;
use Aura\Router\Exception\RouteNotFound;
use Aura\Router\Generator;
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Textbox\Model as TextboxModel;
use Schoolbox\Resource\Textbox\Mapper as TextboxMapper;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\Hydrator\BaseHydrator;
use Schoolbox\Search\Api\Hydrator\FullResult;
use Schoolbox\Search\Api\SearchFilter;
use Schoolbox\User;
use UnexpectedValueException;

/**
 * Hydrator for textbox results.
 */
class Hydrator extends BaseHydrator
{
    /**
     * Base type for this type of result.
     */
    public const BASE_TYPE = 'textbox';

    /**
     * Constructor
     *
     * @param TextboxMapper $textboxMapper Used to fetch textboxes for hydration.
     * @param FolderMapper  $folderMapper  Used to fetch the folder to which a textbox belongs.
     * @param Access        $access        Used to determine if the current user may view this page.
     */
    public function __construct(
        private TextboxMapper $textboxMapper,
        private FolderMapper $folderMapper,
        private Access $access,
        private Generator $routeGenerator,
        private Authorizer $authorizer,
    ) {
    }

    /**
     * @inheritdoc
     */
    protected function getBaseType(): string
    {
        return self::BASE_TYPE;
    }

    /**
     * @inheritdoc
     */
    public function hydrate(string $keyword, SearchFilter\DataObject $filter, array $matches, User $viewer): array
    {
        // Find all textboxes that match $matches.
        $acceptedMatches = array_filter($matches, [$this, 'accepts']);

        $textboxIds = array_map(fn (RawResult $match) => $match->id, $acceptedMatches);
        if (empty($textboxIds)) {
            return [];
        }

        // Find the matching textboxes.
        $textboxes = $this->textboxMapper->findByColumn('textBoxID', $textboxIds);

        // Get an array of componentInstanceIds from the compound textboxInstanceIds.
        $componentInstanceIds = array_map(
            function ($match) {
                [$textboxIdThrowaway, $componentInstanceId, $folderIdThrowaway] = explode('-', $match->id);
                return $componentInstanceId;
            },
            $matches
        );

        // Get config info for each component instance.
        $configByComponentInstanceId = $this->getConfigByComponentInstanceId($componentInstanceIds);

        $fullResults = [];

        foreach ($matches as $match) {
            $textboxInstanceId = $match->id;
            [$textboxId, $componentInstanceId, $folderId] = explode('-', $textboxInstanceId);
            $textbox = $textboxes->getBy('id', $textboxId);

            if (!($textbox instanceof TextboxModel)) {
                continue;
            }

            // The $textboxRootFolder is the folder on which the textbox was originally created.
            // It is the folder on whose 'Manage Textboxes' page the textbox will appear.
            $textboxRootFolder = $textbox->getCategory();

            $textboxBodyTextOnly = $this->htmlToText($textbox->getBody());

            // Because the textbox body contains HTML, this match might have come about only because the
            // keyword was found in HTML code that is invisible to the user (eg. 'style=').
            // Eliminate those kinds of matches.
            if (mb_stripos($textbox->getHeading(), $keyword, 0, self::ENCODING) === false) {
                if (mb_stripos($textboxBodyTextOnly, $keyword, 0, self::ENCODING) === false) {
                    continue;
                }
            }

// TODO: Write another query to find textboxes that are not used on any page, and add that into the QueryParamBuilder main query somehow.
//
//            if (count($textboxInstanceInfo) === 0) {
//                // This textbox is not used on any page.
//
//                // Textboxes not currently used in any component should be shown
//                // only to users with write access to the textbox's main folder.
//                $textboxAccess = $this->access->getFolderAccess($viewer, $textboxRootFolder->getId());
//                if ($textboxAccess < UserAccess::ACCESS_WRITE) {
//                    continue;
//                }
//
//                // Create search result with link to the textbox's 'Manage Textboxes' page.
//                $fullResults[] = new FullResult(
//                    $match,
//                    $match->id,
//                    // No component, so no config, so:
//                    //  * $componentIsCollapsed does not apply,
//                    //  * $showTitleToReadOnlyUsers will have default value, true.
//                    // Conclusion: No reason not to display heading.
//                    $textbox->getHeading(),
//                    $this->buildBaseUrl()
//                         ->withPath("/textbox/folder/{$textboxRootFolder->getId()}")
//                         ->withFragment("textbox-" . $textbox->getId())
//                         ->__toString(),
//                    $match->type,
//                    $match->relevance,
//                    $textbox->getUpdatedAt(),
//                    $textboxRootFolder,
//                    'Hidden from view',
//                    null,
//                    'textbox',
//                    $this->truncateAroundKeyword($keyword, $textboxBodyTextOnly) ?? $this->truncate($textboxBodyTextOnly),
//                    null,
//                    [$textboxRootFolder,], // Context: textbox's folder.
//                    [], // texbox: none.
//                );
//            }

            try {
                $textboxFolder = $this->folderMapper->findById($folderId); // This is the page on which the textbox component is used.
            } catch (Exception\Deleted $e) {
                // We have found a soft-deleted folder.  Skip this textbox instance.
                // Note that it is difficult to JOIN the `folder` table to the SQL query used in this->getTextboxInstanceInfoByTextboxId(),
                // because of the way components are defined (and enum field determines the table to join for different modules).
                // Easier to just skip deleted folders here.
                continue;
            }

            // Check if user has access to textbox's folder
            $textboxAccess = $this->access->getFolderAccess($viewer, $textboxFolder->getId());
            if ($textboxAccess < UserAccess::ACCESS_READ) {
                continue;
            }

            // After json_decode, $componentConfig will be an array with the following optional keys:
            //  * Component_Config_Collapse::KEY,
            //  * Component_Config_ShowComponentTitle::KEY
            $componentConfig = $configByComponentInstanceId[$componentInstanceId];

            if ($componentConfig === null) {
                throw new UnexpectedValueException('Component config JSON could not be decoded.');
            }

            // Component_Config_ShowComponentTitle is poorly named.  The UI makes clear that this option applies only to read-only users.
            // To confirm, go to a homepage, and edit the configuration of a textbox component.  The checkbox label is 'Show Title to Read-Only Users'.
            $componentIsCollapsed = boolval($componentConfig[Component_Config_Collapse::KEY] ?? 0); // Default is not collapsed.
            $showTitleToReadOnlyUsers = boolval($componentConfig[Component_Config_ShowComponentTitle::KEY] ?? 1); // Default is to show title.

            // Textboxes that have been hidden to read-only users (by having title hidden and body collapsed)
            // should appear in search results only for users with write access.
            if (!$showTitleToReadOnlyUsers && $componentIsCollapsed && $textboxAccess < UserAccess::ACCESS_WRITE) {
                continue;
            }

            // Matches whose component title is hidden should only count as matches if they match on something other
            // than the title.  The only other thing textbox matches might match against is the body.
            // (See match criteria for textboxes in QueryParamBuilder->buildBaseWhereParams()).
            if (
                $textboxAccess === UserAccess::ACCESS_READ &&
                !$showTitleToReadOnlyUsers &&
                mb_stripos($textboxBodyTextOnly, $keyword, 0, self::ENCODING) === false
            ) {
                continue;
            }

            // Create search result with link to the homepage on which the textbox has been used.
            $fullResults[] = new FullResult(
                $match,
                $match->id,
                (
                    // Hide title if it should be hidden.
                    ($textboxAccess === UserAccess::ACCESS_READ && !$showTitleToReadOnlyUsers)
                    ? 'Untitled Textbox'
                    : $textbox->getHeading()
                ),
                $this->buildBaseUrl()
                    ->withPath('/homepage/' . $textboxFolder->getId())
                    ->withFragment("textbox-$componentInstanceId-" . $textbox->getId())
                    ->__toString(),
                $match->type,
                $match->relevance,
                $textbox->getUpdatedAt(),
                $textboxFolder,
                null, // Could put the heading of $textboxRootFolder here, but probably not relevant to the user.
                null,
                'textbox',
                $this->truncateAroundKeyword($keyword, $textboxBodyTextOnly) ?? $this->truncate($textboxBodyTextOnly),
                null,
                [$textbox->getCategory(),], // Context: textbox's folder
                $this->getSearchResultLinks($textbox, $viewer)
            );
        }

        return $fullResults;
    }

    /**
     * @param array $componentInstanceIds
     * @return array
     */
    private function getConfigByComponentInstanceId(array $componentInstanceIds): array
    {
        $db = $this->textboxMapper->getDatabase();

        $rows = $db->executeQuery(
            'SELECT cci.component_instance_id, ccd.key, cci.value
            FROM component_config_instance cci
            LEFT JOIN component_config_definition ccd ON ccd.id=cci.component_config_definition_id
            WHERE cci.component_instance_id IN (' . implode(',', array_map('intval', $componentInstanceIds)) . ')
            AND ccd.key IN (:configCollapseKey, :showComponentTitle)',
            [
                ':configCollapseKey' => Component_Config_Collapse::KEY,
                ':showComponentTitle' => Component_Config_ShowComponentTitle::KEY
            ]
        )->fetchAll();

        $configByComponentInstanceId = [];

        foreach ($rows as $row) {
            $componentInstanceId = $row['component_instance_id'];
            unset($row['component_instance_id']);

            if (!array_key_exists($componentInstanceId, $configByComponentInstanceId)) {
                $configByComponentInstanceId[$componentInstanceId] = [];
            }

            $configByComponentInstanceId[$componentInstanceId][$row['key']] = $row['value'];
        }

        return $configByComponentInstanceId;
    }

    /**
     * Return an array of sub-arrays, each sub-array containing info for one link.
     * The links will be in alphabetical order of title.
     *
     * @param User         $viewer
     * @param TextboxModel $textbox
     * @return array array<array{"title": string, "icon": string, "url": string, "action"?: string}>
     * @throws RouteNotFound
     */
    private function getSearchResultLinks(TextboxModel $textbox, User $viewer): array
    {
        $links = [];

        $textboxFolder = $textbox->getCategory();

        $route = 'textbox.getIndexForFolder';
        $params = ['folderId' => $textboxFolder->getId(), 'folder' => $textboxFolder];
        if ($this->authorizer->isPathAuthorizedForUser($route, $params, $viewer)) {
            $links[] = [
                'url' => $this->routeGenerator->generate($route, $params),
                'title' => 'Manage Textbox',
                'icon' => 'icon-textbox',
            ];
        }

        return $links;
    }
}
