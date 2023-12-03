/**
 * A badge that can be assigned to a student as a goal or achievement
 */

import { Node } from "@schoolboxCurriculum/types/Node";

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
    image: string | null;
    icon: string;
    allowAsGoal: boolean;
    animate: boolean;
    category: {
        id: string;
        name: string;
    };
    achievementExpiry: string | null;
    goalExpiry: string | null;
    curriculumNodes: Node[];
    tags: string | null;
    _links: {
        update: string;
        delete: string;
        achievementRecipients: string;
        goalRecipients: string;
        achievementWithdraw: string;
        goalWithdraw: string;
        icon: string;
        awardAsAchievement: string;
        setAsGoal: string;
        setAsGoalForSelf: string | null;
    };
}
