<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Membership;

use Closure;
use OAT\Library\Lti1p3Core\Registration\RegistrationInterface;
use OAT\Library\Lti1p3Core\Role\RoleInterface;
use OAT\Library\Lti1p3Core\User\UserIdentity;
use OAT\Library\Lti1p3Nrps\Model\Context\Context;
use OAT\Library\Lti1p3Nrps\Model\Member\Member;
use OAT\Library\Lti1p3Nrps\Model\Member\MemberCollection;
use OAT\Library\Lti1p3Nrps\Model\Member\MemberInterface;
use OAT\Library\Lti1p3Nrps\Model\Membership\Membership;
use OAT\Library\Lti1p3Nrps\Model\Membership\MembershipInterface;
use OAT\Library\Lti1p3Nrps\Service\Server\Builder\MembershipServiceServerBuilderInterface;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

/**
 * NPRS membership service server builder.
 *
 * Returns memberships for a given context (in this case, a folder) and a
 * resource link (in this case, an assessment within the folder).
 */
class Builder implements MembershipServiceServerBuilderInterface
{
    /**
     * @var Folder
     */
    private Folder $folder;

    /**
     * @var UserAccessMapper
     */
    private UserAccessMapper $userAccessMapper;

    /**
     * @var AssessmentDataSource
     */
    private AssessmentDataSource $assessmentDataSource;

    /**
     * Constructor
     *
     * @param Folder               $folder
     * @param UserAccessMapper     $userAccessMapper
     * @param AssessmentDataSource $assessmentDataSource
     */
    public function __construct(
        Folder $folder,
        UserAccessMapper $userAccessMapper,
        AssessmentDataSource $assessmentDataSource
    ) {
        $this->folder               = $folder;
        $this->userAccessMapper     = $userAccessMapper;
        $this->assessmentDataSource = $assessmentDataSource;
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

        return new Membership(
            // TODO identifiers usually are fully qualified
            (string)$this->folder->getId(),
            new Context(
                (string)$this->folder->getId(),
                $this->folder->getCodeString(),
                $this->folder->getTitle(),
            ),
            new MemberCollection($members),
        );
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
        $members = $this->buildMemberCollection(
            $this->roleIncludesLearners($role) ? $this->findLearnersForResourceLink($resourceLinkIdentifier) : null,
            $this->roleIncludesInstructors($role) ? $this->findInstructorsForFolder() : null,
            $limit,
            $offset,
        );

        return new Membership(
            // TODO identifiers usually are fully qualified
            (string)$this->folder->getId(),
            new Context(
                (string)$this->folder->getId(),
                $this->folder->getCodeString(),
                $this->folder->getTitle(),
            ),
            new MemberCollection($members),
        );
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
        return new Member(
            new UserIdentity(
                $user->getUsername(),
                $user->getFullName(),
                $user->getEmail()
            ),
            MemberInterface::STATUS_ACTIVE,
            [$role],
            // Why must I provide the above as properties below?
            [
                'user_id'     => $user->getUsername(),
                'status'      => MemberInterface::STATUS_ACTIVE,
                'name'        => $user->getFullName(),
                'given_name'  => $user->getFirstname(),
                'family_name' => $user->getLastname(),
                'email'       => $user->getEmail(),
                'roles'       => [$role],
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
     * Finds learners for this resource link.
     *
     * (This will be the participants in the assessment identified by the
     *  resource link.)
     *
     * @param string $resourceLinkIdentifier
     * @return GenericCollection|MemberInterface[]
     */
    private function findLearnersForResourceLink(string $resourceLinkIdentifier): GenericCollection
    {
        // Extract an ID out of the resource link
        // TODO add codec for resource links, contexts as there are with users
        $assessment = $this->assessmentDataSource->findByIdForReview($resourceLinkIdentifier);

        // TODO also include a message
        //      (which tells how the user should launch the link)

        return $this
            ->buildEmptyMemberCollection()
            ->merge(
                $assessment
                    ->getParticipantUsers()
                    ->map(Closure::fromCallable([$this, 'mapUserToLearner']))
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
}
