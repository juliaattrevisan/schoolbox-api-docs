/**
 * A curriculum node
 */

export type Node = {
    children: Node[] | null;
    code: string | null;
    description: string | null;
    enabled: boolean; // whether the Node is enabled or not
    id: number;
    parent: Node | null;
    name: string;
    selectedParentId?: number; // when a parent Node is selected (to enable/disable or delete), this will be the id of that Node
};
