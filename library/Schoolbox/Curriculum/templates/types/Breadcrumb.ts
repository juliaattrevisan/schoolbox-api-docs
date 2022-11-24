/**
 * A curriculum breadcrumb, used to traverse forwards
 * and backwards through the node tree
 */

export type Breadcrumb = {
    depth?: number;
    id: number;
    name: string;
};
