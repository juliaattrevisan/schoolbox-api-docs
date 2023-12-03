/**
 * A badge that will be assigned to a student
 */

import type { Badge } from "@schoolboxBadge/types/Badge";

export interface BadgeAward extends Badge {
    expiry: number | null;
    type: "achievement" | "goal";
}
