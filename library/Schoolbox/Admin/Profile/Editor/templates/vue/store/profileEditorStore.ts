import { defineStore } from "pinia";
import { cloneDeep } from "lodash";
import type { ProfileEditorRoleType, ProfileEditorPayload } from "../types/profileEditorTypes";
import { fetchProfileEditorEndpoint } from "./profileEditorUtils";

export const useProfileEditorStore = defineStore("profileEditorStore", () => {
    const isLoading = window.Vue.ref<boolean>(true);
    const roleTypes = window.Vue.ref<ProfileEditorPayload["data"]["roleTypes"]>([]);
    const savedRoleTypes = window.Vue.ref<typeof roleTypes.value>([]);
    const fieldSettings = window.Vue.ref<ProfileEditorPayload["data"]["fieldSettings"]>({});
    const selectedRoleType = window.Vue.ref<ProfileEditorRoleType | null>(null);

    const savedSelectedRoleType = window.Vue.computed<typeof selectedRoleType.value>(() => {
        const currentRoleType = selectedRoleType.value;
        if (currentRoleType === null) return null;

        return savedRoleTypes.value.find((roleType) => roleType.roleType === currentRoleType.roleType) ?? null;
    });

    /**
     * Initialise the store with the relevant data
     * @param $roleTypes - The current role types
     * @param $fieldSettings - The current field settings
     */
    function init(
        $roleTypes: ProfileEditorPayload["data"]["roleTypes"],
        $fieldSettings: ProfileEditorPayload["data"]["fieldSettings"],
    ): void {
        roleTypes.value = $roleTypes;
        savedRoleTypes.value = cloneDeep($roleTypes);
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
     * Save changes to the current role type's name format
     * @returns true if the save was successful, otherwise an error message
     */
    async function saveNameFormat(): Promise<true | string> {
        if (selectedRoleType.value === null) throw new Error("No role type selected");
        if (savedSelectedRoleType.value === null) throw new Error("No saved role type selected");

        if (selectedRoleType.value.nameFormat === savedSelectedRoleType.value.nameFormat) {
            return true;
        }

        const response = await fetchProfileEditorEndpoint(selectedRoleType.value._routes.postNameFormat, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({ nameFormat: selectedRoleType.value.nameFormat }),
        });

        if (!response.success) {
            return response.message ?? "An unknown error occurred";
        }

        savedSelectedRoleType.value.nameFormat = selectedRoleType.value.nameFormat;
        return true;
    }

    return {
        init,
        isLoading,
        roleTypes,
        fieldSettings,
        savedRoleTypes,
        selectedRoleType,
        savedSelectedRoleType,
        setSelectedRoleType,
        saveNameFormat,
    };
});
