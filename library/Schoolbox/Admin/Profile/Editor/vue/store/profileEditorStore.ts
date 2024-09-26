import { defineStore } from "pinia";
import type { ProfileEditorRoleType, ProfileEditorEndpoints, ProfileEditorPayload } from "../profileEditorTypes";

export const useProfileEditorStore = defineStore("profileEditorStore", () => {
    const isLoading = window.Vue.ref<boolean>(true);
    const roleTypes = window.Vue.ref<ProfileEditorPayload["data"]["roleTypes"]>([]);
    const fieldSettings = window.Vue.ref<ProfileEditorPayload["data"]["fieldSettings"]>({});

    const selectedRoleType = window.Vue.ref<ProfileEditorRoleType | null>(null);

    const endpoints = window.Vue.ref<ProfileEditorEndpoints>();

    /**
     * initialise the store with the relevant endpoints and data
     * @param $endpoints the endpoints required for the profile editor
     * @param $roleTypes the current role types
     * @param $fieldSettings the current field settings
     */
    function init(
        $endpoints: ProfileEditorEndpoints,
        $roleTypes: ProfileEditorPayload["data"]["roleTypes"],
        $fieldSettings: ProfileEditorPayload["data"]["fieldSettings"],
    ): void {
        endpoints.value = $endpoints;
        roleTypes.value = $roleTypes;
        fieldSettings.value = $fieldSettings;
    }

    /**
     * Set the currently selected role type
     * @param roleType the role type to set as selected
     */
    function setSelectedRoleType(roleType: ProfileEditorRoleType): void {
        selectedRoleType.value = roleType;
    }

    /**
     * Whether a field can be selected or not
     * @param field - The field to check
     * @returns true if the field is disabled (and can't be selected), false otherwise
     */
    function isFieldDisabled(field: string): boolean {
        if (selectedRoleType.value === null) return true;
        if (selectedRoleType.value.fields.selected.some((selectedField) => selectedField.field === field)) {
            return true;
        }
        return false;
    }

    /**
     * Set the field as selected
     * @param field - The key of the field to select
     * @param index - The index to insert the field at (default 0)
     */
    function addSelectedField(field: string, index: number = 0): void {
        if (selectedRoleType.value === null) return;
        if (isFieldDisabled(field)) return;

        const availableField = selectedRoleType.value.fields.available.find((f) => f.field === field);
        if (availableField === undefined) throw new Error(`Field ${field} not found`);

        // a map of each field setting key to an empty array to start with (all settings are deselected)
        const emptyFieldSettings = Object.fromEntries(Object.keys(fieldSettings.value).map((key) => [key, []]));

        selectedRoleType.value.fields.selected.splice(index, 0, {
            field: availableField.field,
            displayAs: availableField.displayAs,
            icon: availableField.icon,
            css: availableField.css,
            fieldSettings: emptyFieldSettings,
        });
    }

    return {
        init,
        isLoading,
        roleTypes,
        fieldSettings,
        selectedRoleType,
        setSelectedRoleType,
        isFieldDisabled,
        addSelectedField,
    };
});
