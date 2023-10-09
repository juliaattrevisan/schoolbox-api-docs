<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @textbox      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Textbox;

use Alaress\Html\ToText;
use Component_Config_Collapse;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Textbox\Model as TextboxModel;
use Schoolbox\Resource\Textbox\Mapper as TextboxMapper;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\Hydrator\BaseHydrator;
use Schoolbox\Search\Api\Hydrator\FullResult;
use Schoolbox\User;

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
     * @param Access        $access        Used to determine if the current user may view this page.
     */
    public function __construct(
        private TextboxMapper $textboxMapper,
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
    public function hydrate(string $keyword, array $matches, User $viewer): array
    {
        // Find all textboxes that match $matches.
        $acceptedMatches = array_filter($matches, [$this, 'accepts']);

        $textboxIds = array_map(fn (RawResult $match) => $match->id, $acceptedMatches);
        if (empty($textboxIds)) {
            return [];
        }

        // Get componentInstanceId indexed by textboxId.
        $db = $this->textboxMapper->getDatabase();
        $componentInstanceIdByTextboxId = $db->executeQuery(
            'SELECT value AS textbox_id, component_instance_id
            FROM component_config_instance cci
            JOIN component_config_definition ccd ON ccd.id=cci.component_config_definition_id
            WHERE ccd.key=:componentKey
            AND value IN (' . implode(',', array_map('intval', $textboxIds)) . ')',
            [TextboxMapper::HOMEPAGE_COMPONENT_KEY]
        )->fetchColumnArray('component_instance_id', 'textbox_id');


        // Get component_config_definition 'collapse' value by componentInstanceId.
        // This will tell us for each component, whether the component is in the collapsed state.
        $collapseValueByComponentInstanceId = $db->executeQuery(
            'SELECT component_instance_id, value AS collapse_value
            FROM component_config_instance cci
            JOIN component_config_definition ccd ON ccd.id=cci.component_config_definition_id
            WHERE ccd.key=:collapse
            AND cci.component_instance_id IN (' . implode(',', array_map('intval', array_values($componentInstanceIdByTextboxId))) . ')',
            ['collapse:' => Component_Config_Collapse::KEY]
        )->fetchColumnArray('collapse_value', 'component_instance_id');

        // Find the matching textboxes.
        $textboxes = $this->textboxMapper->findByColumn('textBoxID', $textboxIds);

        // And map to a full result.
        $fullResults = array_map(
            function (RawResult $match) use ($textboxes, $viewer, $keyword, $componentInstanceIdByTextboxId, $collapseValueByComponentInstanceId) {
                $textbox = $textboxes->getBy('id', $match->id);

                if (!($textbox instanceof TextboxModel)) {
                    return null;
                }

                // Check if user has access to textbox's folder
                $textboxAccess = $this->access->getFolderAccess($viewer, $textbox->getCategory()->getId());
                if ($textboxAccess < UserAccess::ACCESS_READ) {
                    return null;
                }

                // Find component_instance_id for use in URL fragment.
                $componentInstanceId = $componentInstanceIdByTextboxId[$textbox->getId()] ?? null;

                // If component_instance_id is null, that means the textbox is not currently used in any component.
                // Textboxes not currently used in any component should be shown only to users with write access.
                if ($componentInstanceId === null && $textboxAccess < UserAccess::ACCESS_WRITE) {
                    return null;
                }

                // If collapseValue is null, that means the textbox component is collapsed.
                // Textboxes in the collapsed state should be shown only to users with write access.
                $componentIsCollapsed = boolval($collapseValueByComponentInstanceId[$componentInstanceId] ?? null);
                if ($componentIsCollapsed === true && $textboxAccess < UserAccess::ACCESS_WRITE) {
                    return null;
                }

                $textboxBody = (new ToText($textbox->getBody()))->getText();
                return new FullResult(
                    $match,
                    $match->id,
                    $textbox->getHeading() . ($componentInstanceId === null ? ' (not used in any component)' : ''),
                    (
                        $componentInstanceId === null ? '' :
                        $this->buildBaseUrl()
                        ->withPath('/homepage/' . $textbox->getCategory()->getId())
                        ->withFragment("textbox-$componentInstanceId-" . $textbox->getId())
                        ->__toString()
                    ),
                    $match->type,
                    $match->relevance,
                    $textbox->getUpdatedAt(),
                    $textbox->getCategory(),
                    null,
                    null,
                    'textbox',
                    $this->truncateAroundKeyword($keyword, $textboxBody) ?? $this->truncate($textboxBody),
                    null,
                    [$textbox->getCategory(),], // Context: textbox's folder
                    [], // texbox: none.
                );
            },
            $matches
        );

        // Filter out anything missing
        return array_filter($fullResults, fn (?FullResult $x) => isset($x));
    }
}
