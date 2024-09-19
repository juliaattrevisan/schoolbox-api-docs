/**
 * General types used on both the profile page and profile editor
 */

export type ProfileFieldSettingOption = {
    id: number | string;
    name: string;
    type: string; // currently unused, e.g. "campus" or "role"
};

export type ProfileFieldSetting = {
    key: string;
    displayAs: string;
    options: ProfileFieldSettingOption[];
};

export type ProfileSelectedField = {
    field: string;
    displayAs: string;
    fieldSettings: Record<ProfileFieldSetting["key"], ProfileFieldSettingOption["id"][]>;
};

export type ProfileRoleType = {
    name: string; // only ever "student", "staff", or "parent" at the moment... for display purposes make Title Case if required
    fields: {
        // Selected Fields
        // These are implicitly ordered by sequence any time
        // the front- and back-end exchange payload data
        // They represent the fields that are currently shown on the profile page
        selected: ProfileSelectedField[];
    };
};
