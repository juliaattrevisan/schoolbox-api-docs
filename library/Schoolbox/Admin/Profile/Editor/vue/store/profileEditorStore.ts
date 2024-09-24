import { defineStore } from "pinia";
import type { ProfileFieldSetting } from "../types/profileTypes";
import type { ProfileEditorRoleType, ProfileEditorEndpoints } from "../types/profileEditorTypes";
import type { ProfileEditorPayload } from "../types/profilePayloadTypes";

export const useProfileEditorStore = defineStore("profileEditorStore", () => {
    const isLoading = window.Vue.ref<boolean>(true);
    const roleTypes = window.Vue.ref<ProfileEditorRoleType[]>([]);
    const fieldSettings = window.Vue.ref<ProfileFieldSetting[]>([]);

    const selectedRoleType = window.Vue.ref<ProfileEditorRoleType | null>(null);

    const endpoints = window.Vue.ref<ProfileEditorEndpoints>();

    /**
     * Fetch the data required for the profile editor
     */
    async function fetchProfileEditorData(): Promise<void> {
        if (endpoints.value === undefined) throw new Error("Endpoints not set");

        isLoading.value = true;

        const response = await fetch(endpoints.value.profileEditorGet);
        const responseJson = (await response.json()) as unknown as ProfileEditorPayload;

        roleTypes.value = responseJson.data.roleTypes;
        fieldSettings.value = responseJson.data.fieldSettings;

        isLoading.value = false;
    }

    /**
     * initialise the store with the relevant endpoints
     * and load the initial data for the profile editor
     * @param $endpoints the endpoints required for the profile editor
     */
    async function init($endpoints: ProfileEditorEndpoints): Promise<void> {
        endpoints.value = $endpoints;
        await fetchProfileEditorData();
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

        const fieldSettingsKeys = fieldSettings.value.map((setting) => setting.key);
        const emptyFieldSettings = Object.fromEntries(fieldSettingsKeys.map((key) => [key, []]));

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
