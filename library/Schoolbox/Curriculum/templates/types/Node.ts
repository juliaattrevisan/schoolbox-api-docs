import type { Breadcrumb } from "@schoolboxCurriculum/types/Breadcrumb";

/**
 * A curriculum node
 */
export type Node = {
    children: Node[] | null;
    code: string | null;
    description: string | null;
    enabled: boolean; // whether the Node is enabled or not
    id: number;
    order: number;
    parent: Node | null;
    name: string;
    selectedParentId?: number; // when a parent Node is selected (to enable/disable or delete), this will be the id of that Node
    breadcrumbs?: Breadcrumb[]; // list of parents to reach this node
    _links: {
        // links to the endpoints for various actions
        delete: string;
        getUsage: string;
        read: string;
        update: string;
        getExport: string;
    };
    _usageCounts: {
        // just the counts for each activity type
        course: number;
        unit: number;
        assessment: number;
        badge: number;
        folder: number;
    };
    usage?: Usage[]; // all the details for all the places the node has been used
    usageCount?: number; // the total number of linked activities (not just the currently loaded)
};

/**
 * The different activity types available that can be linked to a node
 */
export enum ActivityTypes {
    COURSE = "course",
    UNIT = "unit",
    LEARNING_ACTIVITY = "assessment",
    LEARNING_ACTIVITY_PROJECT = "project",
    LEARNING_ACTIVITY_DUE_WORK = "dueWork",
    LEARNING_ACTIVITY_TASK = "task",
    LEARNING_ACTIVITY_QUIZ = "quiz",
    LEARNING_ACTIVITY_LESSON_PLAN = "lessonPlan",
    LEARNING_ACTIVITY_LTI = "lti",
    BADGE = "badge",
    FOLDER = "folder",
}

/**
 * An activity linked to the node
 */
export type Usage =
    | {
          discriminator: ActivityTypes.COURSE;
          object: CourseUsageObject;
      }
    | {
          discriminator: ActivityTypes.UNIT;
          object: UnitUsageObject;
      }
    | {
          discriminator: ActivityTypes.LEARNING_ACTIVITY;
          object: LearningActivityUsageObject;
      }
    | {
          discriminator: ActivityTypes.BADGE;
          object: BadgeUsageObject;
      }
    | {
          discriminator: ActivityTypes.FOLDER;
          object: UsageObject; // UNIMPLEMENTED - ACTUAL TYPE DIFFERS
      };

/**
 * The further details of the the usage
 */
interface UsageObject {
    id: number;
    title: string;
}

/**
 * The further details of the the course usage
 */
export interface CourseUsageObject extends UsageObject {
    folder: {
        parent: {
            title: string;
        };
        _links: {
            view: string;
        };
    };
}

/**
 *  The further details of the the unit usage
 */
export interface UnitUsageObject extends UsageObject {
    folder: {
        parent: {
            title: string;
        };
        _links: {
            view: string;
        };
    };
}

/**
 * The further details of the the assessment usage
 */
export interface LearningActivityUsageObject extends UsageObject {
    _links: {
        view: string;
    };
    type: ActivityTypes;
    unit: {
        title: string;
    };
}

/**
 * The further details of the the badge usage
 */
export interface BadgeUsageObject {
    id: number;
    name: string;
    category: {
        id: number;
        name: string;
    };
    yearLevel: string;
    image: string | null;
    icon: string | null;
    body: string;
    createdAt: string;
    updatedAt: string;
    allowAsGoal: boolean;
    animate: boolean;
    achievementExpiry: string | null;
    goalExpiry: string | null;
    _links: {
        update: string;
        delete: string;
        achievementRecipients: string;
        goalRecipients: string;
        achievementWithdraw: string;
        goalWithdraw: string;
        icon: string;
    };
}

/**
 * Just the field properties that can be updated
 */
export type NodeToUpdate = {
    code: string | null;
    description: string | null;
    name: string;
};
