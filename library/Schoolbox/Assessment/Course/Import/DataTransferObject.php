<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course\Import;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;

/**
 * Provides a consistent interface to transfer import data from DB all the way to twig.
 *
 * The reason we need this is because although the DB maintains a link between AssessmentImpls and AssessmentSpecs,
 * no such property is exposed in the domain models.
 *
 * We have two alternatives:
 * 1. Pass around an array with AssessmentImpl and AssessmentSpec as a pair, or
 * 2. Pass around an object that stores the pair, but provides a consistent interface via methods
 *
 * This class is the manifestation of the latter approach
 *
 */
class DataTransferObject
{
    /**
     * All UnitImpls must link to their UnitSpec.
     * If the UnitImpl does not yet exist, its ID will be null.
     *
     * @var UnitImplementation[]|GenericCollection<UnitImplementation>
     */
    private $unitImplementations;

    /**
     * A Collection of IAssessable pairs, each Collection indexed by UnitSpec.id
     *
     * Each pair may contain:
     * 1. Just an AssessmentSpec               => UnitSpec not imported, or AssessmentSpec not imported, or Impl deleted.
     * 2. Both AssessmentSpec + AssessmentImpl => UnitSpec imported and AssessmentSpec imported. Spec may be deleted.
     * 3. Just an AssessmentImpl               => Assessment was created directly in UnitImpl. Impl cannot be deleted.
     *
     * @var IAssessable[][][] $unitSpecAssessments
     * @var IAssessable|null $unitSpecAssessments [unitSpec.id][]['spec']
     * @var IAssessable|null $unitSpecAssessments [unitSpec.id][]['impl']
     */
    private $unitSpecAssessments;

    /**
     * Maps the IDs of AssessmentImpls to AssessmentSpecs ID.
     *
     * AssessmentSpec ID will be null if AssessmentImpl was created directly in UnitImpl
     *
     * We need this as this relationship is only in the DB, not exposed at Domain level.
     *
     * @var int[]
     */
    private $assessmentImplToSpecMap;


    /**
     * Maps the IDs AssessmentSpecs to ID of an AssessmentImpl
     *
     * We need this for efficient O(1) lookup.
     *
     * Only contains those specs that have been implemented.
     *
     * @var int[]
     */
    private $assessmentSpecToImplMap;


    /**
     * @param int[]|null[] $assessmentImplToSpecMap Maps extant Impl Ids to Spec Id (null if Impl created in UnitImpl)
     */
    public function __construct(array $assessmentImplToSpecMap)
    {
        $this->assessmentImplToSpecMap = $assessmentImplToSpecMap;
        $this->unitImplementations = new GenericCollection(UnitImplementation::class);
        $this->unitSpecAssessments = new GenericCollection(GenericCollection::class);

        // Suppress warnings. We expect some values may be null. That's cool, we don't need 'em.
        $this->assessmentSpecToImplMap = @array_flip($assessmentImplToSpecMap);
    }

    /**
     * @param UnitSpecification $unitSpec
     * @return IAssessable[][]
     */
    public function getAssessmentPairs(UnitSpecification $unitSpec)
    {
        return $this->unitSpecAssessments->getOffset($unitSpec->getId());
    }

    /**
     * @param UnitImplementation $unitImpl
     * @return bool Whether it was added. False if already existed.
     */
    public function addUnitImplementation(UnitImplementation $unitImpl)
    {
        $unitSpecId = $unitImpl->getSpecification()->getId();
        if ($this->unitImplementations->getOffset($unitSpecId) instanceof UnitImplementation) {
            return false;
        }
        $this->unitImplementations->setOffset($unitSpecId, $unitImpl);
        return true;
    }

    /**
     * Adds an AssessmentSpec that has no non-deleted AssessmentImpl
     *
     * @param UnitSpecification $unitSpec
     * @param IAssessable $assessmentSpec
     * @return bool
     */
    public function addAssessmentSpec(UnitSpecification $unitSpec, IAssessable $assessmentSpec)
    {
        // Ensure this AssessmentSpec is being added correctly
        if (array_key_exists($assessmentSpec->getId(), $this->assessmentSpecToImplMap)) {
            throw new \DomainException("AssessmentSpec has an Impl. Must be added as a Pair: {$assessmentSpec->getId()}");
        }
        $this->checkUnitSpecCollection($unitSpec);
        $this->unitSpecAssessments->getOffset($unitSpec->getId())->add(new AssessmentPair($assessmentSpec, null));
        return true;
    }

    /**
     * Adds an AssessmentImpl that was created directly in UnitImpl
     *
     * @param UnitSpecification $unitSpec
     * @param IAssessable $assessmentImpl
     * @return bool
     */
    public function addAssessmentImpl(UnitSpecification $unitSpec, IAssessable $assessmentImpl)
    {
        // Ensure this AssessmentImpl does not have a Spec
        if (array_key_exists($assessmentImpl->getId(), $this->assessmentImplToSpecMap)) {
            throw new \DomainException("AssessmentImpl has a Spec. Must be added as a Pair: {$assessmentImpl->getId()}");
        }
        $this->checkUnitSpecCollection($unitSpec);
        $this->unitSpecAssessments->getOffset($unitSpec->getId())->add(new AssessmentPair(null, $assessmentImpl));
        return true;
    }

    /**
     * Adds matching pair AssessmentSpec and AssessmentImpl
     *
     * @param UnitSpecification $unitSpec
     * @param IAssessable $assessmentSpec
     * @param IAssessable $assessmentImpl
     * @return bool
     */
    public function addAssessmentPair(UnitSpecification $unitSpec, IAssessable $assessmentSpec, IAssessable $assessmentImpl)
    {
        // Ensure these Assessments are a valid pair
        if ($this->assessmentImplToSpecMap[$assessmentImpl->getId()] != $assessmentSpec->getId()) {
            throw new \DomainException("Assessments not a valid pair: Spec: {$assessmentSpec->getId()}, Impl: {$assessmentImpl->getId()}");
        }
        $this->checkUnitSpecCollection($unitSpec);
        $this->unitSpecAssessments->getOffset($unitSpec->getId())->add(new AssessmentPair($assessmentSpec,
            $assessmentImpl));
        return true;
    }

    /**
     * Call from TWIG to get a list of UnitImpls to traverse
     *
     * @return UnitImplementation[]|GenericCollection<UnitImplementation>
     */
    public function getUnitImplementations()
    {
        return $this->unitImplementations;
    }

    /**
     * Call from TWIG to get a set of IAssessable pairs
     *
     * @param UnitSpecification $unitSpec
     * @return AssessmentPair[]|GenericCollection<AssessmentPair> Set of IAssessable pairs
     */
    public function getUnitSpecAssessments(UnitSpecification $unitSpec)
    {
        return $this->unitSpecAssessments->getOffset($unitSpec->getId());
    }

    /**
     * Ensures an AssessmentPair collection exists for the specified UnitSpec
     * @param UnitSpecification $unitSpec
     * @return bool
     */
    protected function checkUnitSpecCollection(UnitSpecification $unitSpec)
    {
        // Ensure AssessmentPair collection exists for this UnitSpec
        if (!$this->unitSpecAssessments->getOffset($unitSpec->getId()) instanceof GenericCollection) {
            $this->unitSpecAssessments->setOffset($unitSpec->getId(), new GenericCollection(AssessmentPair::class));
        }
    }

}