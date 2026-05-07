<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid;

use Alaress\Specification\NotEmpty;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper;
use Schoolbox\Assessment\Rubric\RowSet\Capability;
use Schoolbox\Mapper\Exception;

/**
 * Class Factory
 */
class Factory
{
    /**
     * Frontend encodes a rubric grid into a json string and sends multiple grids at a time, in each $_POST request.
     * You can get to each grid's payload via its rubric_instance.id but you have to manufacture the index itself.
     */
    public const string RUBRIC_PAYLOAD_INDEX = 'rubric[%d]';


    /**
     * @var MarkTypeMapper
     */
    private MarkTypeMapper $markTypeMapper;


    /**
     * @var GradeLevel\Group\Mapper
     */
    private GradeLevel\Group\Mapper $gradeGroupMapper;


    /**
     * Constructor.
     */
    public function __construct() {
        $this->markTypeMapper = MarkTypeMapper::getInstance();
        $this->gradeGroupMapper = GradeLevel\Group\Mapper::getInstance();
    }


    /**
     * Port of `\Schoolbox\Assessment\Rubric\Factory::buildFromPostData`,
     * but builds a `Grid` object instead.
     *
     * @param Grid $grid A grid (which already contains an assessment and rowset)
     * @param array $rubricData
     * @param bool $validate Whether to run the backend validation on this data
     *                       - should be FALSE for CSV import and export, as
     *                       attempting to import/export an invalid grid should
     *                       still succeed even if saving it will not.
     * @return Grid
     * @throws Exception
     * @see /library/Schoolbox/Assessment/Rubric/templates/vue/payload.ts
     */
    public function buildFromPostData(
        Grid $grid,
        array $rubricData,
        bool $validate = true
    ): Grid {
        /* The rubric instance corresponding to $grid might be a stub. That means it's a new grid and we actually need
         * to hydrate it with the settings from the frontend payload.
         */
        if ($grid->getRubricInstance()->getId() < 0) {
            $newCapabilityMarkType = $this->markTypeMapper->createModel()
                ->setId(MarkType::slugToId($rubricData['capabilityMarkType']));

            $newCapabilityGradeGroup = $this->gradeGroupMapper->createModel()
                ->setId($rubricData['capabilityGradeGroup']);

            $grid->getRubricInstance()
                ->setTitle($rubricData['title'])
                ->setWeight($rubricData['weight'])
                ->setCapabilityMarkType($newCapabilityMarkType)
                ->setCapabilityGradeGroup($newCapabilityGradeGroup)
                ->setMarkedUsingRange($rubricData['useMarkRange'] ?? false);
        }

        /* The post payload contains all the rubric grids associated with an assessment but we only need to look at one
         * grid for now since we've decided that any of the N grids could cause invalidation and thus exit early.
         *
         * These are the new settings that came from the ui
         */
        $levels = $rubricData['level'] ?? [];
        $capabilities = $rubricData['group'] ?? [];
        $indicators = $rubricData['criterion'] ?? [];
        $descriptors = $rubricData['descriptor'] ?? [];
        $usesMarkRange = $rubricData['useMarkRange'] ?? false;

        /* This was originally an assessment property but it's actually on the rubric instance now and isn't part of
         * the $_POST['rubric'] node, which is a bit interesting
         */
        $markPerCap = $grid->getRubricInstance()->isMarkPerCapability() ?? false;

        // Rebuild the set of levels from scratch each time
        $newLevels = $grid->getLevels()->emptyClone();

        foreach ($levels as $levelSequence => $level) {
            $levelName = $level['name'];
            $levelId = filter_var($level['id'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

            $newLevels->add((new Level())->setName($levelName)->setId($levelId));
        }

        $grid->setLevels($newLevels);

        // Must assume that all capabilities in formdata exist:
        // they will, because it was created when the capability was added
        // via the form.


        // Which capabilities are actually in use?
        $capabilityIdsInUse = [];

        foreach ($indicators as $indicator) {
            if (isset($indicator['on'])) {
                $capabilityIdsInUse[$indicator['groupId']] = true;
            }
        }


        // cache capabilities
        /** @var Capability[] $capabilityCache */
        $capabilityCache     = [];
        $newCapabilityUsages = $grid->getCapabilityUsages()->emptyClone();

        foreach ($capabilities as $capabilityData) {
            // This capability must exist and be in use in order for it to be saved
            $capabilityId   = (int)$capabilityData['id'];
            $capabilityName = $capabilityData['name'];

            if (!$capabilityId) {
                continue;
            }

            if (empty($capabilityIdsInUse[$capabilityId])) {
                continue;
            }

            // Validate capability max value
            $capabilityMaxValue = isset($capabilityData['maxValue']) ? (float)$capabilityData['maxValue'] : null;

            if ($validate) {
                $capabilityMaxSpec = new ValidCapabilityMax($markPerCap);

                if (!$capabilityMaxSpec->isSatisfiedBy($capabilityMaxValue)) {
                    throw new \InvalidArgumentException('Please provide capability maximum points that are numeric and equal to or greater than 0');
                }
            }

            $capability = $grid->getRowSet()->retrieveCapability($capabilityId);

            // If $group is null, then that's a problem; we're trying to save
            // usage for a group which doesn't exist


            // Can this group's name be set?
            if (!$capability->isInUseExcluding($grid->getAssessment())) {
                $capability->setName($capabilityName);
            }

            $capabilityUsage = (new CapabilityUsage($capability))->setMaxValue($capabilityMaxValue);
            $capabilityCache[$capabilityId]     = $capability;
            $newCapabilityUsages[$capabilityId] = $capabilityUsage;
        }

        $grid->setCapabilityUsages($newCapabilityUsages);


        // Cache indicators
        foreach ($indicators as $indicatorData) {
            $capabilityId     = (int)$indicatorData['groupId'];
            $indicatorId      = (int)$indicatorData['id'];
            $indicatorName    = $indicatorData['name'];
            $indicatorEnabled = isset($indicatorData['on']) && $indicatorData['on'];

            if (!$indicatorEnabled) {
                continue;
            }

            // spawn
            $capability = $capabilityCache[$capabilityId];
            $indicator  = $capability->retrieveIndicator($indicatorId);

            // If $indicator is null, then that's a problem; we're trying to
            // save usage for a indicator which doesn't exist


            // Can this indicator's name be set?
            if (!$indicator->isInUseExcluding($grid->getAssessment())) {
                $indicator->setName($indicatorName);
            }


            $indicatorUsage = new IndicatorUsage($indicator);

            foreach ($grid->getLevels() as $levelSequence => $level) {
                if (empty($descriptors[$indicatorId][$levelSequence])) {
                    continue;
                }

                $descriptor      = $descriptors[$indicatorId][$levelSequence];
                $descriptorId    = filter_var($descriptor['id'] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
                $descriptorText  = $descriptor['text'];
                $descriptorValue = $descriptor['points'];

                if ('' !== $descriptorText || '' !== $descriptorValue) {
                    $indicatorUsage->getDescriptors()[$levelSequence] = (new Descriptor())
                        ->setId($descriptorId)
                        ->setDescription($descriptorText);

                    // Validate descriptor
                    if ($validate) {
                        $descriptorSpec = new ValidDescriptor($usesMarkRange);

                        if (!$descriptorSpec->isSatisfiedBy($descriptorValue)) {
                            throw new \InvalidArgumentException('Please use positive numbers only, and disable Mark Range to use decimal places in descriptor point values');
                        }
                    }

                    // Only set the descriptor's value if it was valid
                    $indicatorUsage->getDescriptors()[$levelSequence]->setMaxValue('' !== $descriptorValue ? (float)$descriptorValue : null);
                }
            }

            // Validate sequence of descriptor points
            if ($validate) {
                $notEmptySpec = new NotEmpty();

                if (!$notEmptySpec->isSatisfiedBy($indicatorUsage->getDescriptors())) {
                    throw new \InvalidArgumentException('You require at least one non-empty descriptor in each selected indicator');
                }

                $monotonicDescriptorRowSpec = new ValidMonotonicDescriptorRow($usesMarkRange);

                if (!$monotonicDescriptorRowSpec->isSatisfiedBy($indicatorUsage->getDescriptors())) {
                    throw new \InvalidArgumentException('Please only use descriptor values that form an ascending or descending sequence within a row');
                }
            }

            $capabilityUsage = $grid->retrieveCapabilityUsage($capability);
            $capabilityUsage->getIndicatorUsages()[$indicatorId] = $indicatorUsage;
        }


        /* If backend validation was requested check that $grid is in a functional state
         * This mainly involves checking that at least one indicator was selected.
         *
         * Note that Andy left a spec pattern inheritance tree that prevents meaningful typesafety; I'm minded to just
         * delete that entire idea wholesale someday.
         */
        if ($validate && !(new ValidRubricGrid())->isSatisfiedBy($grid)) {
            throw new \InvalidArgumentException('Please have at least one selected indicator if using a rubric');
        }


        // Calculate max values for each capability usage.
        // If no value was passed: use calculated value
        foreach ($grid->getCapabilityUsages() as $capabilityUsage) {
            $capabilityMaxValue = $capabilityUsage->getMaxValue();

            if (null === $capabilityMaxValue) {
                $capabilityUsage->setMaxValue($capabilityUsage->calculateMaxValueFromIndicators());
            }
        }

        return $grid;
    }
}
