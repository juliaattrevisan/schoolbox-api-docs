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

        // For all textboxIds, get information on all the places where $textboxId has been instantiated.
        // Each textbox could be on multiple pages, or even in multiple components within the same page.
        // This is done outside the main loop to reduce the number of SQL queries run.
        $textboxInstanceInfoByTextboxId = $this->getTextboxInstanceInfoByTextboxId($textboxIds);

        // Find the matching textboxes.
        $textboxes = $this->textboxMapper->findByColumn('textBoxID', $textboxIds);

        $fullResults = [];

        foreach ($matches as $match) {
            $textbox = $textboxes->getBy('id', $match->id);

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
            if (mb_stripos($textbox->getHeading(), $keyword, 0, 'UTF-8') === false) {
                if (mb_stripos($textboxBodyTextOnly, $keyword, 0, 'UTF-8') === false) {
                    continue;
                }
            }

            // Find all the instances of $textbox.
            $textboxInstanceInfo = $textboxInstanceInfoByTextboxId[$textbox->getId()] ?? [];

            if (count($textboxInstanceInfo) === 0) {
                // This textbox is not used on any page.

                // Textboxes not currently used in any component should be shown
                // only to users with write access to the textbox's main folder.
                $textboxAccess = $this->access->getFolderAccess($viewer, $textboxRootFolder->getId());
                if ($textboxAccess < UserAccess::ACCESS_WRITE) {
                    continue;
                }

                // Create search result with link to the textbox's 'Manage Textboxes' page.
                $fullResults[] = new FullResult(
                    $match,
                    $match->id,
                    // No component, so no config, so:
                    //  * $componentIsCollapsed does not apply,
                    //  * $showTitleToReadOnlyUsers will have default value, true.
                    // Conclusion: No reason not to display heading.
                    $textbox->getHeading(),
                    $this->buildBaseUrl()
                         ->withPath("/textbox/folder/{$textboxRootFolder->getId()}")
                         ->withFragment("textbox-" . $textbox->getId())
                         ->__toString(),
                    $match->type,
                    $match->relevance,
                    $textbox->getUpdatedAt(),
                    $textboxRootFolder,
                    'Hidden from view',
                    null,
                    'textbox',
                    $this->truncateAroundKeyword($keyword, $textboxBodyTextOnly) ?? $this->truncate($textboxBodyTextOnly),
                    null,
                    [$textboxRootFolder,], // Context: textbox's folder.
                    [], // texbox: none.
                );
            }

            foreach ($textboxInstanceInfo as $tio) {
                $folderId = $tio['folder_id'];

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

                $componentInstanceId = $tio['component_instance_id'];

                // After json_decode, $componentConfig will be an array with the following optional keys:
                //  * Component_Config_Collapse::KEY,
                //  * Component_Config_ShowComponentTitle::KEY
                $componentConfigJson = $tio['config_json'];
                $componentConfig = json_decode($componentConfigJson, true);

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
                    mb_stripos($textboxBodyTextOnly, $keyword, 0, 'UTF-8') === false
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
        }

        return $fullResults;
    }

    /**
     * Return an array of arrays containing config information for the textbox components matching $textboxIds.
     *
     * Example of returned array:
     * [
     *     116 => [ // Key is textboxId.
     *         [
     *             'folder_id' => 83,
     *             'component_instance_id' => 4409,
     *             'config_json' => '{"collapse": 1, "showComponentTitle": 0}' // Both keys are optional.
     *         ],
     *         ...
     *     ]
     * ]
     *
     * @param $textboxIds
     * @return array
     */
    private function getTextboxInstanceInfoByTextboxId($textboxIds): array
    {
        $db = $this->textboxMapper->getDatabase();
        $rows = $db->executeQuery(
            // The same textbox can be instantiated on multiple pages, and even multiple times on the same page
            // (in different components).
            //
            // The basic idea of the query is to start with a table of "textboxId, folderId, component_instance_id",
            // and join to a table of "component_instance_id, config_json" to get config_json.
            //
            // Note the purpose of using JSON syntax here is to get all the config items for a textbox into a single
            // row, while limiting the number of joined subqueries to one.
            //
            // Note also that the query below limits results to textboxes in homepage modules only.
            // Textboxes can also appear on homepage_templates for example, but these textboxes will not show up in
            // search results at the moment because of the 'ci.module="homepage"' WHERE condition.
            'SELECT
            cci.value AS textbox_id,
            ci.identifier AS folder_id,
            cci.component_instance_id,
            IFNULL(component_configJson.config_json, "{}") AS config_json
            FROM component_config_instance cci
            JOIN component_instance ci ON ci.id=cci.component_instance_id
            JOIN component_config_definition ccd ON ccd.id=cci.component_config_definition_id
            LEFT JOIN (
                SELECT
                cci2.component_instance_id,
                JSON_OBJECT(ccd2.key, IFNULL(cci2.value, ccd2.default_value)) AS config_json
                FROM component_config_instance cci2
                LEFT JOIN component_config_definition ccd2 ON ccd2.id=cci2.component_config_definition_id
                WHERE ccd2.key IN (:collapse, :showComponentTitle)
                GROUP BY cci2.component_instance_id
            ) AS component_configJson ON (component_configJson.component_instance_id=cci.component_instance_id)
            WHERE ccd.key=:componentKey
            AND ci.module="homepage"
            AND cci.value IN (' . implode(',', array_map('intval', $textboxIds)) . ')',
            [
                ':componentKey' => TextboxMapper::HOMEPAGE_COMPONENT_KEY,
                ':collapse' => Component_Config_Collapse::KEY,
                ':showComponentTitle' => Component_Config_ShowComponentTitle::KEY
            ]
        );

        $rowsByTextboxId = [];

        foreach ($rows as $row) {
            $textboxId = $row['textbox_id'];
            unset($row['textbox_id']);

            if (!array_key_exists($textboxId, $rowsByTextboxId)) {
                $rowsByTextboxId[$textboxId] = [];
            }

            $rowsByTextboxId[$textboxId][] = $row;
        }

        return $rowsByTextboxId;
    }

    /**
     * Return an array of sub-arrays, each sub-array containing info for one link.
     * The links will be in alphabetical order of title.
     *
     * @param User         $viewer
     * @param TextboxModel $textbox
     * @return array array<array{"title": string, "icon": string, "url": string, "action"?: string}>
     * @throws RouteNotFound
     * @throws Exception
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
