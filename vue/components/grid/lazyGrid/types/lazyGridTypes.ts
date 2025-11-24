/**
 * To define the structure of each column
 */
export type Header = {
    id: number;
    key: string;
    title: string;
    width: number;
    isSticky: boolean;
    isSortable: boolean;
};
