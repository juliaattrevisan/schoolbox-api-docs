/**
 * A badge that will be assigned to a student
 */

import type { Badge } from "@schoolboxBadge/templates/types/Badge";
import type { UserAuthor } from "@schoolboxLibrary/types/User";

// This seems to be some frontend created extension of Badge special to the search page, the backend doesn't generate it
export interface SPBadgeAward extends Badge {
    expiry: number | null; // how many days the award is valid for
    type: "achievement" | "goal";
}

/** @see /library/Schoolbox/Learning/Badge/Award/Translator/ModelToArray.php */
export type BadgeAward = {
    id: number;
    badge: Badge;
    status: "goal" | "achievement";
    reason: string | null;
    isDeleted: boolean;
    isGoal: boolean;
    /** The parent award, if applicable */
    award: BadgeAward | null;
    recipient: UserAuthor | null;
    awardedBy: UserAuthor | null;
    awardedAt: string | null;
    reasonedBy: UserAuthor | null;
    reasonedAt: string | null;
    expiresAt: string | null;
    viewedOn: string | null;
    _links: {
        achievementWithdraw: string | null;
        goalWidthdraw: string | null;
        goalAward: string | null;
        awardUpdate: string | null;
    };
};
