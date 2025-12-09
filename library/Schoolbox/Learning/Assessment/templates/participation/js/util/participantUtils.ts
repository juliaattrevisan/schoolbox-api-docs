/**
 * Any utility functions for counting node usages
 */

import type { Participant } from "../types/customParticipationTypes";

/**
 * Checks whether a participant has customisation
 * @param participant - the participant to check for customisation
 * @returns whether the participant has customisation
 */
export function hasCustomisation(participant: Participant): boolean {
    return participant.excludedAt !== null || participant.plan.name !== null || participant.customisedPlan !== null;
}
