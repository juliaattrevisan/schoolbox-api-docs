<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence\Translator\ModelToArray;

use Schoolbox\API\Translator\AbstractLinks;
use Schoolbox\Learning\Moment\Evidence;

/**
 * Translator for loading links into the array.
 */
class Links extends AbstractLinks
{
    /**
     * @param Evidence\Model $evidence
     * @return array
     */
    public function __invoke(Evidence\Model $evidence): array
    {
        $translation = [
            '_links' => [],
        ];

        // Does this evidence have a discussion thread?
        // If so: load and translate it, and inject it into the evidence
        // If not: create a link to create a thread.
        if (!$evidence->getDiscussionThread()) {
            // Output the create thread link.
            // TBD: is it output as a simple link, or a href/auth object?
            // The old Aura link outputs the object, newer links are output
            // as simple values (and are not output at all if permission denied)
            $translation['_links']['createThread'] = $this->getRouteIfAuthorized(
                'evidenceDiscussionThreads.createThread',
                [
                    'context'   => $evidence,
                    'contextId' => $evidence->getId(),
                ]
            );
        }


        $translation['_links']['detail'] = $this->getRouteIfAuthorized(
            'learningMomentEvidence.getEvidenceDetail',
            [
                'evidenceId' => $evidence->getId(),
                'userId'     => $evidence->getRecipient()->getId(),
            ]
        );

        $translation['_links']['modifyForm'] = $this->getRouteIfAuthorized(
            'learningMomentEvidence.getModifyForm',
            [
                'evidenceId' => $evidence->getId(),
                'creatorId'  => $evidence->getCreator()->getId(),
            ]
        );

        $translation['_links']['put'] = $this->getRouteIfAuthorized(
            'learningMomentEvidence.putEvidence',
            [
                'evidenceId' => $evidence->getId(),
                'creatorId'  => $evidence->getCreator()->getId(),
            ]
        );

        $translation['_links']['submit'] = $this->getRouteIfAuthorized(
            'learningMomentEvidence.putSubmit',
            [
                'evidenceId' => $evidence->getId(),
                'creatorId'  => $evidence->getCreator()->getId(),
            ]
        );

        $translation['_links']['delete'] = $this->getRouteIfAuthorized(
            'learningMomentEvidence.deleteEvidence',
            [
                'evidenceId' => $evidence->getId(),
                'creatorId'  => $evidence->getCreator()->getId(),
            ]
        );

        return $translation;
    }
}
