/**
 * Types used by the profile editor
 */

import type { ProfileRoleType } from "./profileTypes";

export type ProfileEditorAvailableField = {
    field: string;
    displayAs: string;
    icon: string | null;
    css: string | null;
};

export type ProfileEditorRoleType = ProfileRoleType & {
    fields: {
        // Available Fields
        // These should be sorted into alphabetical order
        // and represent what could be dragged onto the profile screen's palette
        available: ProfileEditorAvailableField[];
    };
};

export type ProfileEditorEndpoints = {
    profileEditorGet: string;
};
