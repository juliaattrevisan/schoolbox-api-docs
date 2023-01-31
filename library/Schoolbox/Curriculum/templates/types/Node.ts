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
    usage?: Usage[]; // all the places the node has been used
    usageCount?: number; // the total number of linked activities (not just the currently loaded)
};

/**
 * Just the field properties that can be updated
 */
export type NodeToUpdate = {
    code: string | null;
    description: string | null;
    name: string;
};

/**
 * An activity linked to the node
 */
export type Usage = {
    discriminator: "course" | "folder" | "unit";
    object: UsageObject;
};

/**
 * The further details of the the usage
 */
export type UsageObject = {
    id: number;
    subjectCode?: string;
    title: string;
    links: {
        folder?: string;
        modify?: string;
        view?: string;
    };
};
