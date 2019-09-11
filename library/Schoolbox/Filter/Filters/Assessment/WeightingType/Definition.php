<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\WeightingType;

use InvalidArgumentException;
use Schoolbox\Collection;
use Schoolbox\Filter;


/**
 * Available options:
 * □ Weighted
 * □ Unweighted
 *
 */
class Definition extends Filter\AbstractDefinition
{

    // The name to expose in the UI
    protected const NAME = 'Weighting';

    const KEY_WEIGHTED = 'weighted';
    const KEY_UNWEIGHTED = 'unweighted';


    /** @var Collection|string[] */
    private $weightingTypes;


    /**
     * @param Filter\Model                       $filter
     * @param Filter\Page\Option\Mapper          $filterOptionMapper
     * @param Filter\Page\Option\Defaults\Mapper $filterDefaultMapper
     * @param Collection                         $weightingTypes
     */
    public function __construct(
        Filter\Model $filter,
        Filter\Page\Option\Mapper $filterOptionMapper,
        Filter\Page\Option\Defaults\Mapper $filterDefaultMapper,
        Collection $weightingTypes
    ) {
        parent::__construct($filter, $filterOptionMapper, $filterDefaultMapper);
        $this->weightingTypes = $weightingTypes->shallowClone();
    }


    /**
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
        return $this->weightingTypes->getAll();
    }


    /**
     * @inheritdoc
     */
    public static function getPages(): array
    {
        return [
            Filter\Page\Model::PAGE_PENDING_ASSESSMENTS,
            Filter\Page\Model::PAGE_GRADES,
            Filter\Page\Model::PAGE_CLASS_MARK_BOOK,
            Filter\Page\Model::PAGE_COURSE_MARK_BOOK,
            Filter\Page\Model::PAGE_RUBRIC_MARK_BOOK,
            Filter\Page\Model::PAGE_UPCOMING_ASSESSMENTS,
        ];
    }


    /**
     * @inheritdoc
     */
    public function getLabel(string $key): string
    {
        if (self::KEY_UNWEIGHTED === $key) {
            return 'Unweighted';
        }
        if (self::KEY_WEIGHTED === $key) {
            return 'Weighted';
        }
        throw new InvalidArgumentException("Unknown Weighting key: $key");
    }

    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Assessment/WeightingType/templates';
    }
}
