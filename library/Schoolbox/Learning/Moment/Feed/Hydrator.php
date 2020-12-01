<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Feed;

use Schoolbox\Collection;use Schoolbox\Learning\Badge\Award\Mapper as BadgeAwardMapper;use Schoolbox\Learning\Moment\Evidence\Mapper as EvidenceMapper;/**
 * Hydrator for the list of IDs retrieved by `CombinedCursor`.
 */
class Hydrator
{

    private EvidenceMapper $evidenceMapper;

    private BadgeAwardMapper $badgeAwardMapper;

    public function __construct(
        EvidenceMapper $evidenceMapper,
        BadgeAwardMapper $badgeAwardMapper
    ) {
        $this->evidenceMapper = $evidenceMapper;
        $this->badgeAwardMapper = $badgeAwardMapper;
    }


    public function hydrateResults(Collection $results): Collection
    {
        $evidencePb = $this->evidenceMapper->getPredicateBuilder();

        // Again, badge award not having IDs could be a problem
        // Just assume a badge can't be awarded twice at the same time
        $badgeAwardPb = $this->badgeAwardMapper->getPredicateBuilder();

        foreach ($results as $result) {
            switch ($result['discriminator']) {
                case CombinedCursor::DISCRIMINATOR_EVIDENCE:
                    $evidencePb->equals('id', $result['id']);
                    break;

                case CombinedCursor::DISCRIMINATOR_BADGE_AWARD:
                    $badgeAwardSubPb = $this->badgeAwardMapper->getPredicateBuilder()->all(
                        $this->badgeAwardMapper->getPredicateBuilder()
                            ->equals('badge_id', $result['id'])
                            ->equals('awarded_at', $result['date'])
                    );

                    $badgeAwardPb->any($badgeAwardSubPb);
                    break;
            }
        }

        var_dump(
            $this->evidenceMapper->getPredicateBuilder()->any($evidencePb)->getPredicates(),
            $this->badgeAwardMapper->getPredicateBuilder()->any($badgeAwardPb)->getPredicates()
        );

        return new Collection();
    }
}
