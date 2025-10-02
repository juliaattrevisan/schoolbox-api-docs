/**
 * A badge that can be assigned to a student as a goal or achievement
 */

import type { StorageFile } from "@js/resource/resourceUtils";
import type { Node } from "@schoolboxCurriculum/types/Node";

/** @see /library/Schoolbox/Learning/Badge/Translator/ModelToArray.php */
export interface Badge {
    id: number;
    createdAt: string;
    createdBy: {
        id: number;
        _links: {
            profile: string;
            avatar: string;
        };
        title: string;
        firstName: string;
        preferredName: string | null;
        pronoun: string | null;
        lastName: string;
        fullName: string;
        isDeleted: boolean;
    };
    updatedAt: string;
    updatedBy: {
        id: number;
        _links: {
            profile: string;
            avatar: string;
        };
        title: string;
        firstName: string;
        preferredName: string | null;
        pronoun: string | null;
        lastName: string;
        fullName: string;
        isDeleted: boolean;
    };
    name: string;
    body: string;
    image: StorageFile | null;
    icon: string;
    allowAsGoal: boolean;
    animate: boolean;
    category: {
        id: string;
        name: string;
    } | null;
    achievementExpiry: number | null; // how many days the achievement is valid for
    goalExpiry: number | null; // how many days the goal is valid for
    curriculumNodes: Pick<Node, "id" | "name" | "code">[];
    tags: null;
    _links: {
        update: string;
        delete: string;
        achievementRecipients: string;
        goalRecipients: string;
        achievementWithdraw: string;
        goalWithdraw: string;
        icon: string;
        awardAsAchievement?: string;
        setAsGoal?: string;
        setAsGoalForSelf?: string | null;
    };
}
