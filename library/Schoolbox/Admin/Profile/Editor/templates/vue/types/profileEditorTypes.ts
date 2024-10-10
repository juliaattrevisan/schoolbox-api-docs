/**
 * Types used only by the profile editor
 */

export type ProfileEditorEndpoints = {
    adminProfileEditorPostFieldAdd: string;
    adminProfileEditorPostFieldRemove: string;
    adminProfileEditorPostFieldSettings: string;
    adminProfileEditorPostFieldSequence: string;
};

export type ProfileEditorFieldSettingOption = {
    id: number | string;
    name: string;
    type: string; // currently unused, e.g. "campus" or "role"
};

export type ProfileEditorFieldSettingKey = string;
export type ProfileEditorFieldSettingDefinition = {
    displayAs: string;
    tooltip: string;
    options: ProfileEditorFieldSettingOption[];
};

export type ProfileEditorAvailableField = {
    field: string;
    displayAs: string;
    icon: string | null;
    css: string | null;
};

export type ProfileEditorSelectedField = {
    field: string; // matches the field key from the available fields
    displayAs: string;
    icon: string | null;
    css: string | null;
    // A map of each field setting key relevant to the field to an array of the ids of each selected option
    fieldSettings: Record<ProfileEditorFieldSettingKey, ProfileEditorFieldSettingOption["id"][]>;
};

export type ProfileEditorMandatoryField = {
    field: string;
    displayAs: string;
    icon: string | null;
    css: string | null;
    // A map of each field setting key relevant to the field to an array of the ids of each selected option
    fieldSettings: Record<ProfileEditorFieldSettingKey, ProfileEditorFieldSettingOption["id"][]>;
};

// An "Active" field is one that is currently shown on the profile page,
// this can either be a selected field or a mandatory field
// This is not a
export type ProfileEditorActiveField = ProfileEditorSelectedField | ProfileEditorMandatoryField;

export type ProfileEditorRoleType = {
    roleType: string; // only ever "student", "staff", or "parent" at the moment... for display purposes make Title Case if required
    fields: {
        // Available Fields
        // These should be sorted into alphabetical order
        // and represent what could be dragged onto the profile screen's palette
        available: ProfileEditorAvailableField[];
        // Selected Fields
        // These are implicitly ordered by sequence any time
        // the front- and back-end exchange payload data
        // They represent the fields that are currently shown on the profile page
        selected: ProfileEditorSelectedField[];
        // Mandatory Fields
        // These are implicitly ordered by sequence any time
        // the front- and back-end exchange payload data
        // These are the fields that must be shown on the profile page, currently used only for the portrait image
        // Notes:
        // - The user cannot add to, remove, or resequence these fields - they just show as-is
        // - These are not included in availableFields
        // - These show up on the left-hand side of the profile editor as a separate section
        mandatory: ProfileEditorMandatoryField[];
    };
};

/**
 * The initial payload for the profile editor including all role types and
 * the definition of each of the field settings that may be applied to a field
 */
export type ProfileEditorPayload = {
    data: {
        roleTypes: ProfileEditorRoleType[];
        // A map of each field setting key to its definition (display name and options)
        fieldSettings: Record<ProfileEditorFieldSettingKey, ProfileEditorFieldSettingDefinition>;
    };
};
