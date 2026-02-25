import type { Metadata } from "@js/util/sbx/apiUtils";

/**
 * Defines the type of  headers used,
 * which defines what will be displayed in each column.
 */
export enum HeaderType {
    USER = "User",
    DATE_TIME = "DateTime",
    BASIC = "Basic",
}

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
    data: Record<string, unknown>[];
    metadata: Metadata;
};

/**
 * The expected routes that can be used to load data
 */
export type Routes = {
    getData: string;
    exportCsv?: string;
};

/**
 * The value for a basic cell, with optional link support.
 */
export type BasicType = {
    title: string;
    _links?: {
        view?: string;
    };
};
