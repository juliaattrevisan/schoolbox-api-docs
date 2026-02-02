import type { Metadata } from "@js/util/sbx/apiUtils";

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

/**
 * The expected payload
 */
export type Payload = {
    data: string[];
    metadata: Metadata;
};
