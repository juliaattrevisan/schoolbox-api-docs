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
    };
    _usageCounts: {
        // just the counts for each acitivity type
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
    ASSESSMENT = "assessment",
    BADGE = "badge",
    FOLDER = "folder",
}

/**
 * An activity linked to the node
 */
export type Usage = {
    discriminator:
        | ActivityTypes.COURSE
        | ActivityTypes.UNIT
        | ActivityTypes.ASSESSMENT
        | ActivityTypes.FOLDER
        | ActivityTypes.BADGE;
    object: CourseUsageObject | UnitUsageObject | AssessmentUsageObject | BadgeUsageObject | FolderUsageObject;
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
export interface AssessmentUsageObject extends UsageObject {
    _links: {
        view: string;
    };
    unit: {
        title: string;
    };
}

/**
 * The further details of the the folder usage
 */
export interface FolderUsageObject extends UsageObject {
    _links: {
        view: string;
    };
    parent: {
        title: string;
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

/**
 * The further details of the badge usage
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
