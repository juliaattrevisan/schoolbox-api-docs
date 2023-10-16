<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @textbox      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Textbox;

use Alaress\Html\ToText;
use Component_Config_Collapse;
use Component_Config_ShowComponentTitle;
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

        // Get componentInstanceId indexed by textboxId.
        $textboxInstanceInfoByTextboxId = $this->getTextboxInstanceInfoByTextboxId($textboxIds);

        // Find the matching textboxes.
        $textboxes = $this->textboxMapper->findByColumn('textBoxID', $textboxIds);

        $fullResults = [];

        foreach ($matches as $match) {
            $textbox = $textboxes->getBy('id', $match->id);

            if (!($textbox instanceof TextboxModel)) {
                continue;
            }

            // Find all the instances of $textbox.
            $textboxInstanceInfo = $textboxInstanceInfoByTextboxId[$textbox->getId()] ?? null;

            if (count($textboxInstanceInfo) === 0) {
                // This textbox is not used in any pages.
                // TODO: Deal with this case.
                echo "TextboxID {$textbox->getId()} is not used on any pages!";
                die;
            }

            foreach ($textboxInstanceInfo as $tio) {
                $folderId = $tio['folder_id'];

                $textboxFolder = $this->folderMapper->findById($folderId);

                // Check if user has access to textbox's folder
                $textboxAccess = $this->access->getFolderAccess($viewer, $textboxFolder->getId());
                if ($textboxAccess < UserAccess::ACCESS_READ) {
                    continue;
                }

                $componentInstanceId = $tio['component_instance_id'];

                // If component_instance_id is null, that means the textbox is not currently used in any component.
                // Textboxes not currently used in any component should be shown only to users with write access.
                if ($componentInstanceId === null && $textboxAccess < UserAccess::ACCESS_WRITE) {
                    continue;
                }

                // After json_decode, $componentConfig will be an array with the following optional keys:
                //  * Component_Config_Collapse::KEY,
                //  * Component_Config_ShowComponentTitle::KEY
                $componentConfigJson = $tio['configJson'];
                $componentConfig = json_decode($componentConfigJson, true);

                if ($componentConfig === null) {
                    throw new UnexpectedValueException('Component config JSON could not be decoded.');
                }

                // NOTE: Component_Config_ShowComponentTitle is poorly named.  The UI makes clear that this option applies only to read-only users.
                //       To confirm, go to a homepage, and edit the configuration of a textbox component.  The checkbox label is 'Show Title to Read-Only Users'.
                $componentIsCollapsed = boolval($componentConfig[Component_Config_Collapse::KEY] ?? 0); // Default is not collapsed.
                $showTitleToReadOnlyUsers = boolval($componentConfig[Component_Config_ShowComponentTitle::KEY] ?? 1); // Default is to show title.

                // Textboxes in the collapsed state should be shown only to users with write access.
                if (!$showTitleToReadOnlyUsers && $componentIsCollapsed && $textboxAccess < UserAccess::ACCESS_WRITE) {
                    continue;
                }

                $textboxBodyTextOnly = (new ToText($textbox->getBody()))->getText();

                // Matches whose component title is hidden should only count as matches if they match on something other
                // than the title.  The only other thing textbox matches might match against is the body.
                // (See match criteria for textboxes in QueryParamBuilder->buildBaseWhereParams()).
                if (
                    $textboxAccess === UserAccess::ACCESS_READ &&
                    !$showTitleToReadOnlyUsers &&
                    !str_contains(strtolower($textboxBodyTextOnly), $keyword)
                ) {
                    continue;
                }

                // Because the textbox body contains HTML, this match might have come about only because the
                // keyword was found in HTML code that is invisible to the user (eg. 'style=').
                // Eliminate those kinds of matches.
                if (!str_contains($textbox->getHeading(), $keyword)) {
                    if (!str_contains($textboxBodyTextOnly, $keyword)) {
                        continue;
                    }
                }

                $fullResults[] = new FullResult(
                    $match,
                    $match->id,
                    (
                        // Hide title if it should be hidden.
                        ($textboxAccess === UserAccess::ACCESS_READ && !$showTitleToReadOnlyUsers)
                        ? 'Untitled Textbox'
                        : $textbox->getHeading()
                    ),
                    (
                        // If not componentInstanceId, the URL should point to the Manage Textboxes page.
                        $componentInstanceId === null
                        ? $this->buildBaseUrl()
                            ->withPath("/textbox/folder/{$textboxFolder->getId()}")
                            ->withFragment("textbox-" . $textbox->getId())
                            ->__toString()
                        : $this->buildBaseUrl()
                            ->withPath('/homepage/' . $textboxFolder->getId())
                            ->withFragment("textbox-$componentInstanceId-" . $textbox->getId())
                            ->__toString()
                    ),
                    $match->type,
                    $match->relevance,
                    $textbox->getUpdatedAt(),
                    $textboxFolder,
                    ($componentInstanceId === null ? 'Hidden from view' : null),
                    null,
                    'textbox',
                    $this->truncateAroundKeyword($keyword, $textboxBodyTextOnly) ?? $this->truncate($textboxBodyTextOnly),
                    null,
                    [$textboxFolder,], // Context: textbox's folder.
                    [], // texbox: none.
                );
            }
        }

        // Filter out anything missing
        return array_filter($fullResults, fn (?FullResult $x) => isset($x));
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
     *             'configJson' => '{"collapse": 1, "showComponentTitle": 0}' // Both keys are optional.
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
            // and join to a table of "component_instance_id, configJson" to get configJson.
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
            IFNULL(component_configJson.configJson, "{}") AS configJson
            FROM component_config_instance cci
            JOIN component_instance ci ON ci.id=cci.component_instance_id
            JOIN component_config_definition ccd ON ccd.id=cci.component_config_definition_id
            LEFT JOIN (
                SELECT
                cci2.component_instance_id,
                JSON_OBJECT(ccd2.key, IFNULL(cci2.value, ccd2.default_value)) AS configJson
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
}
