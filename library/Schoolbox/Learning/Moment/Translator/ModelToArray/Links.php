<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Translator\ModelToArray;

use Schoolbox\API\Translator\AbstractLinks;

/**
 * Translator for loading links into the array.
 */
class Links extends AbstractLinks
{
    /**
     * @param \Schoolbox\Learning\Moment\Model $moment
     * @return array
     */
    public function __invoke(\Schoolbox\Learning\Moment\Model $moment): array
    {
        $translation = [
            '_links' => [],
        ];

        // Does this evidence have a discussion thread?
        // If so: load and translate it, and inject it into the evidence
        // If not: create a link to create a thread.
        if (!$moment->getDiscussionThread()) {
            // Output the create thread link.
            // TBD: is it output as a simple link, or a href/auth object?
            // The old Aura link outputs the object, newer links are output
            // as simple values (and are not output at all if permission denied)
            $translation['_links']['createThread'] = $this->getRouteIfAuthorized(
                'evidenceDiscussionThreads.createThread',
                [
                    'context'   => $moment,
                    'contextId' => $moment->getId(),
                ]
            );
        }

        $translation['_links']['detail'] = $this->getRouteIfAuthorized(
            'learningMomentEvidence.getEvidenceDetail',
            [
                'evidenceId' => $moment->getId(),
                'userId'     => $moment->getRecipient()->getId(),
            ]
        );

        $translation['_links']['modifyForm'] = $this->getRouteIfAuthorized(
            'learningMomentEvidence.getModifyForm',
            [
                'evidenceId' => $moment->getId(),
                'creatorId'  => $moment->getCreator()->getId(),
                'userId'     => $moment->getRecipient()->getId(),
            ]
        );

        $translation['_links']['put'] = $this->getRouteIfAuthorized(
            'learningMomentEvidence.putEvidence',
            [
                'evidenceId' => $moment->getId(),
                'creatorId'  => $moment->getCreator()->getId(),
                'userId'     => $moment->getRecipient()->getId(),
            ]
        );

        $translation['_links']['draft'] = $this->getRouteIfAuthorized(
            'learningMomentEvidence.putDraft',
            [
                'evidence'   => $moment,
                'evidenceId' => $moment->getId(),
                'creatorId' => $moment->getCreator()->getId(),
                'userId'    => $moment->getRecipient()->getId(),
            ]
        );

        $translation['_links']['submit'] = $this->getRouteIfAuthorized(
            'learningMomentEvidence.putSubmit',
            [
                'evidence'   => $moment,
                'evidenceId' => $moment->getId(),
                'creatorId'  => $moment->getCreator()->getId(),
                'userId'     => $moment->getRecipient()->getId(),
            ]
        );

        $translation['_links']['publish'] = $this->getRouteIfAuthorized(
            'learningMomentEvidence.putPublish',
            [
                'evidence'   => $moment,
                'evidenceId' => $moment->getId(),
                'creatorId'  => $moment->getCreator()->getId(),
                'userId'     => $moment->getRecipient()->getId(),
            ]
        );

        $translation['_links']['reject'] = $this->getRouteIfAuthorized(
            'learningMomentEvidence.putReject',
            [
                'evidence'   => $moment,
                'evidenceId' => $moment->getId(),
                'creatorId'  => $moment->getCreator()->getId(),
                'userId'     => $moment->getRecipient()->getId(),
            ]
        );

        $translation['_links']['delete'] = $this->getRouteIfAuthorized(
            'learningMomentEvidence.deleteEvidence',
            [
                'evidenceId' => $moment->getId(),
                'creatorId'  => $moment->getCreator()->getId(),
            ]
        );

        return $translation;
    }
}
