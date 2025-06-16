import type { AlaressDateFormat } from "@schoolboxLibrary/types/AlaressDateFormat";
import type { User } from "@schoolboxLibrary/types/User";

/**
 * A category a template can belong to.
 */
export type Category = {
    id: number;
    name: string;
};

/**
 * A template that contains predefined content for the editor.
 */
export type Template = {
    id: number;
    category_id: number;
    title: string;
    description: string;
    body: string;
    createdAt: AlaressDateFormat;
    createdBy: User;
};
