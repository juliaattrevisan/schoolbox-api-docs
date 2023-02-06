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
        folder: number;
        unit: number;
    };
    usage?: Usage[]; // all the details for all the places the node has been used
    usageCount?: number; // the total number of linked activities (not just the currently loaded)
};

/**
 * The different activity types available that can be linked to a node
 */
export enum ActivityTypes {
    COURSE = "course",
    FOLDER = "folder",
    UNIT = "unit",
}

/**
 * An activity linked to the node
 */
export type Usage = {
    discriminator: ActivityTypes.COURSE | ActivityTypes.FOLDER | ActivityTypes.UNIT;
    object: UsageObject;
};

/**
 * The further details of the the usage
 */
export type UsageObject = {
    id: number;
    subjectCode?: string;
    title: string;
    _links: {
        view: string;
    };
    course?: {
        title: string;
    };
    folder?: {
        _links?: {
            view: string;
        };
    };
};

/**
 * Just the field properties that can be updated
 */
export type NodeToUpdate = {
    code: string | null;
    description: string | null;
    name: string;
};
