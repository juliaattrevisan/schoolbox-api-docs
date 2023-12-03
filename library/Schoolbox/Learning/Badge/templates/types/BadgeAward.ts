/**
 * A badge that will be assigned to a student
 */

import type { Badge } from "@schoolboxBadge/templates/types/Badge";

export interface BadgeAward extends Badge {
    expiry: number | null; // how many days the award is valid for
    type: "achievement" | "goal";
}
