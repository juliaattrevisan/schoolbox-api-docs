<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\RubricUsage;

use InvalidArgumentException;
use Schoolbox\Filter;
use Schoolbox\Filter\Model;
use Schoolbox\Filter\Page;
use Schoolbox\GenericCollection;
use Schoolbox\L10n\RosettaStone;
use function sprintf;

/**
 * @see Factory for this filter's description.
 */
class Definition extends Filter\AbstractDefinition
{

    // The name to expose in the UI
    protected const NAME = 'Rubric Usage';

    public const KEY_NONE         = 'none';            // filter to assessments that don't use capabilities or indicators (non DW + DW that don't use rubric)
    public const KEY_CAPABILITIES = 'capabilities';    // filter to assessments that use some capability
    public const KEY_INDICATORS   = 'indicators';      // filter to assessments that use some indicator

    /** @var RosettaStone */
    private $rosettaStone;


    /**
     * @param Model                       $filter
     * @param Page\Option\Mapper          $filterOptionMapper
     * @param Page\Option\Defaults\Mapper $filterDefaultMapper
     * @param RosettaStone                $rosettaStone
     */
    public function __construct(
        Model $filter,
        Page\Option\Mapper $filterOptionMapper,
        Page\Option\Defaults\Mapper $filterDefaultMapper,
        RosettaStone $rosettaStone
    ) {
        parent::__construct($filter, $filterOptionMapper, $filterDefaultMapper);
        $this->rosettaStone = $rosettaStone;
    }


    /**
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
        return [
            self::KEY_NONE,
            self::KEY_CAPABILITIES,
            self::KEY_INDICATORS,
        ];
    }


    /**
     * @inheritdoc
     */
    public static function getPages(): array
    {
        return [
//            Filter\Page\Model::PAGE_PENDING_ASSESSMENTS,   // TODO remove when finished testing
            Filter\Page\Model::PAGE_RUBRIC_MARK_BOOK,
        ];
    }


    /**
     * @param Filter\Page\Model $pageFilter
     * @return GenericCollection
     */
    protected function unsetInvalidOptions(Filter\Page\Model $pageFilter): GenericCollection
    {
        $options = parent::unsetInvalidOptions($pageFilter);

        // Rubric markbook is only concerned with assessments that use Capabilities or Indicators
        if ($pageFilter->getPage() === Filter\Page\Model::PAGE_RUBRIC_MARK_BOOK) {
            foreach ($options as $idx => $option) {
                if ($option->getKey() === self::KEY_NONE) {
                    $options->offsetUnset($idx);
                }
            }
        }
        return $options;
    }


    /**
     * @inheritdoc
     */
    public function getLabel(string $key): string
    {
        if (self::KEY_NONE === $key) {
            return '(none) - Does not Rubric';
        }
        if (self::KEY_CAPABILITIES === $key) {
            return "Folder {$this->rosettaStone->translate('rubricGroup', true, true)}";
        }
        if (self::KEY_INDICATORS === $key) {
            return "Folder {$this->rosettaStone->translate('rubricCriterion', true, true)}";
        }
        throw new InvalidArgumentException(sprintf("Unknown '%s' key: '%s'", self::NAME, $key));
    }

    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Assessment/RubricUsage/templates';
    }
}
