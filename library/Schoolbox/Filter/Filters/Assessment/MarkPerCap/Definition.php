<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\MarkPerCap;

use InvalidArgumentException;
use Schoolbox\Filter;
use Schoolbox\Filter\Model;
use Schoolbox\Filter\Page;
use Schoolbox\GenericCollection;
use Schoolbox\L10n\RosettaStone;
use function sprintf;

/**
 * The admin page has the following options:
 * □ (none) - No Rubric (Either not a due-work, or due-work not using rubric)
 * □ Mark Per Capability ON
 * □ Mark Per Capability OFF
 */
class Definition extends Filter\AbstractDefinition
{

    // The name to expose in the UI
    protected const NAME = 'Mark per Capability';

    public const KEY_NONE = 'none'; // assessment that is not a due-work, or a due-work not using rubrics
    public const KEY_ON   = 'on';   // assessments that have a rubric that IS marked-per-cap
    public const KEY_OFF  = 'off';  // assessments that have a rubric that is NOT marked-per-cap

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
     * @return string[]
     */
    public static function getStaticCustomKeys(): array
    {
        return [
            self::KEY_NONE,
            self::KEY_ON,
            self::KEY_OFF,
        ];
    }

    /**
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
        return self::getStaticCustomKeys();
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

        // Rubric markbook is only concerned with assessments that have rubrics
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
            return '(none) - Not using Rubric';
        }
        if (self::KEY_ON === $key) {
            return "Enabled";
        }
        if (self::KEY_OFF === $key) {
            return "Disabled";
        }
        throw new InvalidArgumentException(sprintf("Unknown '%s' key: '%s'", self::NAME, $key));
    }

    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Assessment/MarkPerCap/templates';
    }
}
