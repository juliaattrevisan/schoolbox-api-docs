<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @textbox      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Textbox;

use Alaress\Authorizer\Authorizer;
use Component_Config_Collapse;
use Component_Config_Componentname;
use Component_Config_ShowComponentTitle;
use Aura\Router\Exception\RouteNotFound;
use Aura\Router\Generator;
use InvalidArgumentException;
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Textbox\Model as TextboxModel;
use Schoolbox\Resource\Textbox\Mapper as TextboxMapper;
use Schoolbox\Search\Api\Hydrator\BaseHydrator;
use Schoolbox\Search\Api\Hydrator\FullResult;
use Schoolbox\Search\Api\SearchFilter;
use Schoolbox\User;

/**
 * Hydrator for textbox results.
 */
class Hydrator extends BaseHydrator
{
    private const CONFIG_KEY_COMPACT_MODE = 'compactMode';

    /**
     * Base type for this type of result.
     */
    public const BASE_TYPE = 'textbox';

    /**
     * Title to display if neither the textbox nor the textbox component has a non-blank title.
     */
    public const TITLE_FOR_UNTITLED_TEXTBOX = 'Untitled textbox';

    /**
     * Constructor
     *
     * @param TextboxMapper $textboxMapper Used to fetch textboxes for hydration.
     * @param FolderMapper  $folderMapper  Used to fetch the folder to which a textbox belongs.
     * @param Access        $access        Used to determine if the current user may view this page.
     * @param Generator     $routeGenerator
     * @param Authorizer    $authorizer
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
     * @throws Exception
     */
    public function hydrate(string $keyword, SearchFilter\DataObject $filter, array $matches, User $viewer): array
    {
        // Find all textboxes that match $matches.
        $acceptedMatches = array_filter($matches, [$this, 'accepts']);

        if (count($acceptedMatches) === 0) {
            return [];
        }

        // Get arrays of textboxIds and componentInstanceIds.
        $textboxIds = [];
        $componentInstanceIds = [];
        foreach ($acceptedMatches as $match) {
            [$textboxId, $componentInstanceId, $folderId] = self::explodeTextboxInstanceId($match->id);
            $textboxIds[] = $textboxId;
            $componentInstanceIds[] = $componentInstanceId;
        }

        // Find the matching textboxes for later lookup.
        $textboxes = $this->textboxMapper->findByColumn('id', $textboxIds);

        // Get config info for each component instance for later lookup.
        $configByComponentInstanceId = $this->getConfigByComponentInstanceId($componentInstanceIds);

        $fullResults = [];

        foreach ($acceptedMatches as $key => $match) {
            [$textboxId, $componentInstanceId, $folderId] = self::explodeTextboxInstanceId($match->id);

            $textbox = $textboxes->getBy('id', $textboxId);

            if (!($textbox instanceof TextboxModel)) {
                continue;
            }

            // The $textboxRootFolder is the folder on which the textbox was originally created.
            // It is the folder on whose 'Manage Textboxes' page the textbox will appear.
            $textboxRootFolder = $textbox->getFolder();

            // The user's access level to the textbox depends on the page to which the textbox is most closely associated.
            // If the textbox is in a component, the page in which the component has been placed is identified by $folderId.
            // Otherwise, use the textbox's root folder to determine access level.
            $textboxAccess = $this->access->getFolderAccess(
                $viewer,
                $folderId ?? $textboxRootFolder->getId()
            );

            // If the user does not have access, eliminate this result.
            if ($textboxAccess < UserAccess::ACCESS_READ) {
                continue;
            }

            // $componentConfig (if not null) will be an array with the following optional keys:
            //  * Component_Config_Collapse::KEY,
            //  * Component_Config_ShowComponentTitle::KEY
            //  * Component_Config_Componentname::KEY
            //  * self::CONFIG_KEY_COMPACT_MODE
            $componentConfig = $configByComponentInstanceId[$componentInstanceId] ?? null;

            // Get relevant component config values.
            // Note Component_Config_ShowComponentTitle is poorly named.  The UI makes clear that this option applies only to read-only users.
            // To confirm, go to a homepage, and edit the configuration of a textbox component.  The checkbox label is 'Show Title to Read-Only Users'.
            $componentIsCollapsed = (bool)($componentConfig[Component_Config_Collapse::KEY] ?? 0); // Default is not collapsed.
            $showTitleToReadOnlyUsers = (bool)($componentConfig[Component_Config_ShowComponentTitle::KEY] ?? 1); // Default is to show title.
            $compactMode = (bool)($componentConfig[self::CONFIG_KEY_COMPACT_MODE] ?? 0); // Default is not compact.
            $componentName = (
                // If value is null or blank string, set to null.
                empty($componentConfig[Component_Config_Componentname::KEY])
                ? null
                : $componentConfig[Component_Config_Componentname::KEY]
            );
            $textboxHeading = (
                ($textboxAccess === UserAccess::ACCESS_READ && !$showTitleToReadOnlyUsers)
                ? ''
                : $componentName ?? trim($textbox->getHeading())
            );

            // This formerly eliminated matches on invisible HTML tags
            // (eg. 'style='): now that the search index is prebuilt without
            // these tags, this is no longer necessary.

            $textboxBodyTextOnly = $this->htmlToText($textbox->getBody());
            if ($componentInstanceId === null) {
                // This textbox is not used on any page.

                // Textboxes not currently used in any component should be shown
                // only to users with write access to the textbox's main folder.
                if ($textboxAccess < UserAccess::ACCESS_WRITE) {
                    continue;
                }

                // Create search result with link to the textbox's 'Manage Textboxes' page.
                $fullResults[$key] = new FullResult(
                    $match,
                    $match->id,
                    // No component, so no config, so:
                    //  * $componentIsCollapsed does not apply,
                    //  * $showTitleToReadOnlyUsers will have default value, true.
                    // Conclusion: No reason not to display heading.
                    ($textboxHeading === '' ? self::TITLE_FOR_UNTITLED_TEXTBOX : $textboxHeading),
                    $this->buildBaseUrl()
                         ->withPath("/textbox/folder/{$textboxRootFolder->getId()}")
                         ->withFragment("textbox-" . $textbox->getId())
                         ->__toString(),
                    $match->type,
                    'Textbox',
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

                continue;
            }

            try {
                $textboxFolder = $this->folderMapper->findById($folderId); // This is the page on which the textbox component is used.
            } catch (Exception\Deleted $e) {
                // Note that textboxes in soft-deleted folders should not have been selected by the query in
                // QueryParamBuilder, so this is a redundant check.
                continue;
            }

            // Textboxes that have been hidden to read-only users (by having title hidden and body collapsed)
            // should appear in search results only for users with write access.
            if (!$showTitleToReadOnlyUsers && $componentIsCollapsed && $textboxAccess < UserAccess::ACCESS_WRITE) {
                continue;
            }

            // Create search result with link to the homepage on which the textbox has been used.
            $fullResults[$key] = new FullResult(
                $match,
                $match->id,
                (
                    // Hide title if it should be hidden.
                    ($textboxHeading === '' || $textboxAccess === UserAccess::ACCESS_READ && !$showTitleToReadOnlyUsers)
                    ? self::TITLE_FOR_UNTITLED_TEXTBOX
                    : $textboxHeading
                ),
                $this->buildBaseUrl()
                    ->withPath('/homepage/' . $folderId)
                    ->withFragment(
                        $this->buildHomepageTextboxFragment(
                            $componentInstanceId,
                            $textbox->getId(),
                            $textboxAccess,
                            $showTitleToReadOnlyUsers,
                            $compactMode
                        )
                    )
                    ->__toString(),
                $match->type,
                'Textbox',
                $match->relevance,
                $textbox->getUpdatedAt(),
                $textboxFolder,
                null, // Could put the heading of $textboxRootFolder here, but probably not relevant to the user.
                null,
                'textbox',
                $this->truncateAroundKeyword($keyword, $textboxBodyTextOnly) ?? $this->truncate($textboxBodyTextOnly),
                null,
                [$textbox->getFolder(),], // Context: textbox's folder
                $this->getSearchResultLinks($textbox, $viewer)
            );
        }

        return $fullResults;
    }

    /**
     * @param string $textboxInstanceId - Expected to be a string like "$textboxId-$componentInstanceId-$folderId".
     * @return array
     */
    public static function explodeTextboxInstanceId(string $textboxInstanceId): array
    {
        $tokens = explode('-', $textboxInstanceId);

        if (count($tokens) !== 3) {
            throw new InvalidArgumentException('String $textboxInstanceId is in unexpected format.');
        }

        // Convert strings to integer or null.
        for ($i = 0; $i < count($tokens); ++$i) {
            if ($tokens[$i] === '') {
                $tokens[$i] = null;
            } else {
                $tokens[$i] = intval($tokens[$i]);
            }
        }

        return [
            $tokens[0], // textboxId,
            $tokens[1], // componentInstanceId,
            $tokens[2]  // folderId.
        ];
    }

    /**
     * Build the fragment for the homepage URL that will link to the textbox.
     * @return string The fragment will be in one of the following formats:
     *  If the titlebar is rendered (ie. not in compact mode) because the viewer has
     *  write access or the title is shown to read-only users:
     *  "component-title-$componentInstanceId"
     *  Otherwise: "textbox-$componentInstanceId-$textboxId"
     */
    private function buildHomepageTextboxFragment(
        int $componentInstanceId,
        int $textboxId,
        int $textboxAccess,
        bool $showTitleToReadOnlyUsers,
        bool $compactMode
    ): string {
        $titlebarIsRendered = !$compactMode
            && ($textboxAccess >= UserAccess::ACCESS_WRITE || $showTitleToReadOnlyUsers);

        if ($titlebarIsRendered) {
            return "component-title-$componentInstanceId";
        }

        return "textbox-$componentInstanceId-$textboxId";
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
            AND ccd.key IN (:configCollapseKey, :showComponentTitleKey, :componentNameKey, :compactModeKey)',
            [
                ':configCollapseKey' => Component_Config_Collapse::KEY,
                ':showComponentTitleKey' => Component_Config_ShowComponentTitle::KEY,
                ':componentNameKey' => Component_Config_Componentname::KEY,
                ':compactModeKey' => self::CONFIG_KEY_COMPACT_MODE,
            ]
        )->fetchAll();

        $configByComponentInstanceId = [];

        foreach ($rows as $row) {
            $componentInstanceId = $row['component_instance_id'];

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

        $textboxFolder = $textbox->getFolder();

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
