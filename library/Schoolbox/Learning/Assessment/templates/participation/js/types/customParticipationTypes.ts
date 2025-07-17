import type { User } from "@schoolboxLibrary/types/User";

/**
 * The expected payload for a plan associated with a participant
 * this will be related to the original activity, and whether it's been modified
 */
export type Plan = {
    closeDate: string | null;
    createdAt: string;
    deletedAt: string | null;
    description: string | null;
    dueDate: string | null;
    id: number;
    isDynamic: boolean;
    name: string | null;
    openDate: string | null;
    publishDate: string | null;
    updatedAt: string;
};

/**
 * The expected payload for an assessable item, which is the link to
 * a new or copied activity that will be used in the customised plan
 */
export type Assessable = {
    id: number;
    title: string;
    _links: {
        modify: string;
    };
};

/**
 * The expected payload for the customised plan associated with the participant
 * which will be related to the customised activities, whether it's cloned or new
 */
export type CustomisedPlan = Plan & {
    assessable: Assessable;
};

/**
 * The expected payload for each participant in the activity
 */
export type Participant = {
    id: number;
    createdAt: string;
    excludedAt: string | null;
    plan: Plan;
    updatedAt: string;
    user: User;
    customisedPlan: CustomisedPlan;
    _links: {
        exclude: string;
        include: string;
        learningPlanNew: string;
    };
};
