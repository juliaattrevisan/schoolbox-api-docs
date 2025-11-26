/**
 * To define the structure of each column
 */
export type Header = {
    id: number;
    key: string;
    title: string;
    type: string; // should be overwritten by specific types
    width: number;
    isSticky: boolean;
    isSortable: boolean;
};
