<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Feed;

use Schoolbox\Collection;
use Schoolbox\Learning\Badge\Award\Mapper as BadgeAwardMapper;
use Schoolbox\Learning\Badge\Award\Model as BadgeAwardModel;
use Schoolbox\Learning\Moment\Evidence\Mapper as EvidenceMapper;

/**
 * Hydrator for the list of IDs retrieved by `\Schoolbox\Learning\Moment\Feed\Cursor`.
 */
class Hydrator
{
    /**
     * Mapper for retrieving evidence.
     */
    private EvidenceMapper $evidenceMapper;


    /**
     * Mapper for retrieving badge awards.
     */
    private BadgeAwardMapper $badgeAwardMapper;


    /**
     * Constructor
     *
     * @param EvidenceMapper   $evidenceMapper
     * @param BadgeAwardMapper $badgeAwardMapper
     */
    public function __construct(EvidenceMapper $evidenceMapper, BadgeAwardMapper $badgeAwardMapper)
    {
        $this->evidenceMapper   = $evidenceMapper;
        $this->badgeAwardMapper = $badgeAwardMapper;
    }


    /**
     * Hydrates the results from `\Schoolbox\Learning\Moment\Feed\Cursor::retrieveResults`
     * into a set of actual objects.
     *
     * @param Collection $results
     * @return Collection Each item  will contain:
     *                    * discriminator: identifier of object's type
     *                    * object:        the actual object
     */
    public function hydrateResults(Collection $results): Collection
    {
        $evidencePb  = $this->evidenceMapper->getPredicateBuilder();
        $hasEvidence = false;

        // Again, badge award not having IDs could be a problem
        // Just assume a badge can't be awarded twice at the same time
        $badgeAwardPb   = $this->badgeAwardMapper->getPredicateBuilder();
        $hasBadgeAwards = false;

        // Build predicate builders
        foreach ($results as $result) {
            switch ($result['discriminator'] ?? null) {
                case Cursor::DISCRIMINATOR_EVIDENCE:
                    $hasEvidence = true;
                    $evidencePb->all(
                        $this->evidenceMapper->getPredicateBuilder()
                            ->equals('id', $result['id'])
                            ->isNull('deleted_at')
                    );
                    break;

                case Cursor::DISCRIMINATOR_BADGE_AWARD:
                    $hasBadgeAwards = true;
                    $badgeAwardPb->all(
                        $this->badgeAwardMapper->getPredicateBuilder()
                            ->equals('badge_id', $result['id'])
                            ->equals('awarded_at', $result['date'])
                            ->isNull('deleted_at')
                    );
                    break;

                default:
                    // nothing happens
                    break;
            }
        }

        // Query
        $evidenceResults = $hasEvidence
            ? $this->evidenceMapper->findByPredicates(
                $this->evidenceMapper->getPredicateBuilder()->any($evidencePb)
            )
            : $this->evidenceMapper->createCollection();
        $evidenceResults = $evidenceResults->indexBy('id');

        $badgeAwardResults = $hasBadgeAwards
            ? $this->badgeAwardMapper->findByPredicates(
                $this->badgeAwardMapper->getPredicateBuilder()->any($badgeAwardPb)
            )
            : $this->badgeAwardMapper->createCollection();
        $badgeAwardResults = $badgeAwardResults->indexByCallback(
            function (BadgeAwardModel $model) {
                return $this->createBadgeAwardKey($model->getBadge()->getId(), $model->getAwardedAt()->getDatabase());
            }
        );

        // Insert matching results into the collection
        $return = new Collection();
        foreach ($results as $i => $result) {
            switch ($result['discriminator'] ?? null) {
                case Cursor::DISCRIMINATOR_EVIDENCE:
                    // Find the evidence
                    $return[$i] = [
                        'discriminator' => $result['discriminator'],
                        'object'        => $evidenceResults[$result['id']],
                    ];
                    break;

                case Cursor::DISCRIMINATOR_BADGE_AWARD:
                    // find the badge award
                    $return[$i] = [
                        'discriminator' => $result['discriminator'],
                        'object'        => $badgeAwardResults[$this->createBadgeAwardKey((int)$result['id'], $result['date'])],
                    ];
                    break;

                default:
                    // nothing happens
                    break;
            }
        }

        return $return;
    }


    /**
     * Helper method for determining the key of a badge award.
     *
     * Required because badge awards don't have an ID field.
     *
     * @param int    $badgeId
     * @param string $date
     * @return string
     */
    private function createBadgeAwardKey(int $badgeId, string $date): string
    {
        return sprintf('%d-%s', $badgeId, $date);
    }
}
