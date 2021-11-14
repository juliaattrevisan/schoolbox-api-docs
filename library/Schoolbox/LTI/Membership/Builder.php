<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Membership;

use Closure;
use Laminas\Uri\Uri;
use Laminas\Uri\UriInterface;
use OAT\Library\Lti1p3Core\Registration\RegistrationInterface;
use OAT\Library\Lti1p3Core\Role\RoleInterface;
use OAT\Library\Lti1p3Core\User\UserIdentity;
use OAT\Library\Lti1p3Nrps\Model\Context\Context;
use OAT\Library\Lti1p3Nrps\Model\Member\Member;
use OAT\Library\Lti1p3Nrps\Model\Member\MemberCollection;
use OAT\Library\Lti1p3Nrps\Model\Member\MemberInterface;
use OAT\Library\Lti1p3Nrps\Model\Membership\Membership;
use OAT\Library\Lti1p3Nrps\Model\Membership\MembershipInterface;
use OAT\Library\Lti1p3Nrps\Model\Message\Message;
use OAT\Library\Lti1p3Nrps\Model\Message\MessageInterface;
use OAT\Library\Lti1p3Nrps\Service\Server\Builder\MembershipServiceServerBuilderInterface;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\Lti\Model as LtiAssessment;
use Schoolbox\LTI\ResourceLink\LaunchFactory;
use Schoolbox\LTI\Context\Folder\Codec as FolderCodec;
use Schoolbox\LTI\ResourceLink\Assessment\Codec as AssessmentCodec;
use Schoolbox\LTI\User\Codec as UserCodec;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Url\Factory;
use Schoolbox\User;
use UnexpectedValueException;

/**
 * NPRS membership service server builder.
 *
 * Returns memberships for a given context (in this case, a folder) and a
 * resource link (in this case, an assessment within the folder).
 */
class Builder implements MembershipServiceServerBuilderInterface
{
    /**
     * Folder, for creating the membership's context.
     */
    private Folder $folder;

    /**
     * User access mapper, for finding the users in the context.
     */
    private UserAccessMapper $userAccessMapper;

    /**
     * Assessment data source, for finding the assessment to use as a resource
     * link.
     */
    private AssessmentDataSource $assessmentDataSource;

    /**
     * Config, for creating the codec for contexts.
     */
    private IConfigLoader $config;

    /**
     * User codec, for encoding users into the membership output.
     */
    private UserCodec $userCodec;

    /**
     * Launch factory, for building parameters for the resource link launch.
     */
    private LaunchFactory $launchFactory;

    /**
     * Constructor
     *
     * @param Folder               $folder
     * @param UserAccessMapper     $userAccessMapper
     * @param AssessmentDataSource $assessmentDataSource
     * @param IConfigLoader        $config
     * @param UserCodec            $userCodec
     * @param LaunchFactory        $launchFactory
     */
    public function __construct(
        Folder $folder,
        UserAccessMapper $userAccessMapper,
        AssessmentDataSource $assessmentDataSource,
        IConfigLoader $config,
        UserCodec $userCodec,
        LaunchFactory $launchFactory
    ) {
        $this->folder               = $folder;
        $this->userAccessMapper     = $userAccessMapper;
        $this->assessmentDataSource = $assessmentDataSource;
        $this->config               = $config;
        $this->userCodec            = $userCodec;
        $this->launchFactory        = $launchFactory;
    }

    /**
     * Builds memberships for a given context.
     *
     * @param RegistrationInterface $registration
     * @param string|null           $role
     * @param int|null              $limit
     * @param int|null              $offset
     * @return MembershipInterface
     */
    public function buildContextMembership(
        RegistrationInterface $registration,
        ?string $role = null,
        ?int $limit = null,
        ?int $offset = null
    ): MembershipInterface {
        $members = $this->buildMemberCollection(
            $this->roleIncludesLearners($role) ? $this->findLearnersForFolder() : null,
            $this->roleIncludesInstructors($role) ? $this->findInstructorsForFolder() : null,
            $limit,
            $offset,
        );

        return $this->buildMembership($members);
    }

    /**
     * Builds memberships for a given resource link.
     *
     * @param RegistrationInterface $registration
     * @param string                $resourceLinkIdentifier
     * @param string|null           $role
     * @param int|null              $limit
     * @param int|null              $offset
     * @return MembershipInterface
     */
    public function buildResourceLinkMembership(
        RegistrationInterface $registration,
        string $resourceLinkIdentifier,
        ?string $role = null,
        ?int $limit = null,
        ?int $offset = null
    ): MembershipInterface {
        $resourceLinkCodec = (new AssessmentCodec($this->buildBaseUrl()));

        // Extract an ID out of the resource link
        $assessmentId = $resourceLinkCodec->decode($resourceLinkIdentifier);

        // Assert the assessment is an LTI assessment
        $assessment = null;
        if (isset($assessmentId)) {
            $assessment = $this->assessmentDataSource->findByIdForReview($assessmentId);
        }
        if (!$assessment instanceof LtiAssessment) {
            throw new UnexpectedValueException('Resource link identifier does not identify a resource link');
        }

        // Assert the assessment's folder matches the membership's folder
        if ($assessment->getBaseContext()->getFolder()->getId() !== $this->folder->getId()) {
            throw new UnexpectedValueException('Resource link identifier identifies a resource outside of the context');
        }

        // Assert the assessment's LTI tool's registration matches $registration
        // (see http://www.imsglobal.org/spec/lti-nrps/v2p0#access-restriction)
        if ($assessment->getLtiTool()->getClientId() !== $registration->getClientId()) {
            throw new UnexpectedValueException('Resource link identifier identifies a resource belonging to another tool');
        }

        $members = $this->buildMemberCollection(
            $this->roleIncludesLearners($role) ? $this->findLearnersForLtiAssessment($assessment) : null,
            $this->roleIncludesInstructors($role) ? $this->findInstructorsForLtiAssessment($assessment) : null,
            $limit,
            $offset,
        );

        return $this->buildMembership($members, $resourceLinkIdentifier);
    }

    /**
     * Map function: convert a user to a member object.
     *
     * @param User   $user
     * @param string $role
     * @return MemberInterface
     */
    private function mapUserToMember(User $user, string $role): MemberInterface
    {
        // Share minimal information by default, as per
        // http://www.imsglobal.org/spec/lti-nrps/v2p0#sharing-of-personal-data
        // (Not yet sure how to receive data sharing consent)
        $encodedUser = $this->userCodec->encode($user);
        return new Member(
            new UserIdentity($encodedUser),
            MemberInterface::STATUS_ACTIVE,
            [$role],
            // Why must I provide the above as properties below?
            [
                'user_id' => $encodedUser,
                'status'  => MemberInterface::STATUS_ACTIVE,
                'roles'   => [$role],
            ]
        );
    }

    /**
     * Map function: convert a user to a learner-type member object.
     *
     * @param User $user
     * @return MemberInterface
     */
    private function mapUserToLearner(User $user): MemberInterface
    {
        return $this->mapUserToMember($user, 'Learner');
    }

    /**
     * Map function: convert a user+assessment to a learner-type member object,
     * with a message appropriate for the assessment.
     *
     * @param User          $user
     * @param LtiAssessment $assessment
     * @return MemberInterface
     */
    private function mapUserToLearnerWithAssessment(User $user, LtiAssessment $assessment): MemberInterface
    {
        $member = $this->mapUserToLearner($user);
        return $member->setMessage($this->buildMessage($user, $assessment));
    }

    /**
     * Map function: convert a user to an instructor-type member object.
     *
     * @param User $user
     * @return MemberInterface
     */
    private function mapUserToInstructor(User $user): MemberInterface
    {
        return $this->mapUserToMember($user, 'Instructor');
    }

    /**
     * Map function: convert a user+assessment to an instructor-type member
     * object, with a message appropriate for the assessment.
     *
     * @param User          $user
     * @param LtiAssessment $assessment
     * @return MemberInterface
     */
    private function mapUserToInstructorWithAssessment(User $user, LtiAssessment $assessment): MemberInterface
    {
        $member = $this->mapUserToInstructor($user);
        return $member->setMessage($this->buildMessage($user, $assessment));
    }

    /**
     * Sort function: sort memberships by user ID.
     *
     * @param MemberInterface $a
     * @param MemberInterface $b
     * @return int
     */
    private function sortMemberships(MemberInterface $a, MemberInterface $b): int
    {
        return $a->getUserIdentity()->getIdentifier() <=> $b->getUserIdentity()->getIdentifier();
    }

    /**
     * Does this role param include learners?
     *
     * @param string|null $role
     * @return bool
     */
    private function roleIncludesLearners(?string $role): bool
    {
        return !isset($role) || in_array($role, [RoleInterface::NAMESPACE_CONTEXT . '#Learner', 'Learner']);
    }

    /**
     * Does this role param include instructors?
     *
     * @param string|null $role
     * @return bool
     */
    private function roleIncludesInstructors(?string $role): bool
    {
        return !isset($role) || in_array($role, [RoleInterface::NAMESPACE_CONTEXT . '#Instructor', 'Instructor']);
    }

    /**
     * Find learners in this builder's folder.
     *
     * @return GenericCollection|MemberInterface[]
     */
    private function findLearnersForFolder(): GenericCollection
    {
        return $this
            ->buildEmptyMemberCollection()
            ->merge(
                $this->userAccessMapper
                    ->findUsersInCategory(
                        $this->folder,
                        null,
                        UserAccess::ACCESS_READ,
                        true,
                        true,
                    )
                    ->map(Closure::fromCallable([$this, 'mapUserToLearner']))
            );
    }

    /**
     * Finds learners for this assessment.
     *
     * This will be the participants in the assessment: the member record will
     * also contain a message which contains the claims in the resource link
     * launch request, as per
     * http://www.imsglobal.org/spec/lti-nrps/v2p0#message-section.
     *
     * @param LtiAssessment $assessment
     * @return GenericCollection|MemberInterface[]
     */
    private function findLearnersForLtiAssessment(LtiAssessment $assessment): GenericCollection
    {
        return $this
            ->buildEmptyMemberCollection()
            ->merge(
                $assessment
                    ->getParticipantUsers()
                    ->map(function (User $user) use ($assessment) {
                        return $this->mapUserToLearnerWithAssessment($user, $assessment);
                    })
            );
    }

    /**
     * Find instructors in this builder's folder.
     *
     * @return GenericCollection|MemberInterface[]
     */
    private function findInstructorsForFolder(): GenericCollection
    {
        return $this
            ->buildEmptyMemberCollection()
            ->merge(
                $this->userAccessMapper
                    ->findUsersInCategory(
                        $this->folder,
                        null,
                        UserAccess::ACCESS_WRITE,
                        true,
                        false,
                    )
                    ->map(Closure::fromCallable([$this, 'mapUserToInstructor']))
            );
    }

    /**
     * Finds instructors for this assessment.
     *
     * This will be the same as the folder instructors: the member record will
     * also contain a message which contains the claims in the resource link
     * launch request, as per
     * http://www.imsglobal.org/spec/lti-nrps/v2p0#message-section.
     *
     * @param LtiAssessment $assessment
     * @return GenericCollection|MemberInterface[]
     */
    private function findInstructorsForLtiAssessment(LtiAssessment $assessment): GenericCollection
    {
        return $this
            ->buildEmptyMemberCollection()
            ->merge(
                $this->userAccessMapper
                    ->findUsersInCategory(
                        $assessment->getBaseContext()->getFolder(),
                        null,
                        UserAccess::ACCESS_WRITE,
                        true,
                        false,
                    )
                    ->map(function (User $user) use ($assessment) {
                        return $this->mapUserToInstructorWithAssessment($user, $assessment);
                    })
            );
    }

    /**
     * Build an empty member collection
     *
     * @return GenericCollection|MemberInterface[]
     */
    private function buildEmptyMemberCollection(): GenericCollection
    {
        return new GenericCollection(MemberInterface::class);
    }

    /**
     * Build a member collection out of learners and instructors, and also apply
     * a limit and offset.
     *
     * @param GenericCollection|MemberInterface[]|null $learners
     * @param GenericCollection|MemberInterface[]|null $instructors
     * @param int|null                                 $limit
     * @param int|null                                 $offset
     * @return GenericCollection|MemberInterface[]
     */
    private function buildMemberCollection(
        ?GenericCollection $learners,
        ?GenericCollection $instructors,
        ?int $limit,
        ?int $offset
    ): GenericCollection {
        $result = $this->buildEmptyMemberCollection();
        if ($learners) {
            $result = $result->merge($learners);
        }
        if ($instructors) {
            $result = $result->merge($instructors);
        }
        return $result
            ->sortByCallback(Closure::fromCallable([$this, 'sortMemberships']))
            ->slice($offset ?? 0, $limit, true);
    }

    /**
     * Build the message part of the membership response for this
     * user+assessment.
     *
     * @param User          $user
     * @param LtiAssessment $assessment
     * @return MessageInterface
     */
    private function buildMessage(User $user, LtiAssessment $assessment): MessageInterface
    {
        // Inject claims from the assessment:
        // http://www.imsglobal.org/spec/lti-nrps/v2p0#message-section
        $message = [];
        $claims  = $this->launchFactory->buildAdditionalClaims($assessment, $user, $user);
        foreach ($claims as $claim) {
            $message[$claim::getClaimName()] = $claim->normalize();
        }
        return new Message($message);
    }

    /**
     * Builds the actual membership object from the list of members.
     *
     * @param iterable    $members
     * @param string|null $resourceLinkIdentifier
     * @return MembershipInterface
     */
    private function buildMembership(iterable $members, ?string $resourceLinkIdentifier = null): MembershipInterface
    {
        $baseUrl      = $this->buildBaseUrl();
        $contextId    = (new FolderCodec($baseUrl))->encode($this->folder->getId());
        $membershipId = $contextId . '/memberships';
        if (isset($resourceLinkIdentifier)) {
            $membershipId = (new Uri($membershipId))
                ->setQuery(['rlid' => $resourceLinkIdentifier])
                ->toString();
        }
        return new Membership(
            $membershipId,
            new Context(
                $contextId,
                $this->folder->getCodeString(),
                $this->folder->getTitle(),
            ),
            new MemberCollection($members),
        );
    }

    /**
     * Build a base URL.
     *
     * @return UriInterface
     */
    private function buildBaseUrl(): UriInterface
    {
        return (new Factory($this->config))->buildFullyQualified()->setScheme('https');
    }
}
