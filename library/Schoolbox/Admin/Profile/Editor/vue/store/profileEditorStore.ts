import { defineStore } from "pinia";
import { cloneDeep } from "lodash";
import type { ProfileEditorRoleType, ProfileEditorEndpoints, ProfileEditorPayload } from "../types/profileEditorTypes";

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

    const endpoints = window.Vue.ref<ProfileEditorEndpoints>({
        adminProfileEditorPostFieldAdd: "",
        adminProfileEditorPostFieldRemove: "",
        adminProfileEditorPostFieldSettings: "",
        adminProfileEditorPostFieldSequence: "", // TODO: replace and handle null for all these
    });

    /**
     * Initialise the store with the relevant endpoints and data
     * @param $endpoints - The endpoints required for the profile editor
     * @param $roleTypes - The current role types
     * @param $fieldSettings - The current field settings
     */
    function init(
        $endpoints: ProfileEditorEndpoints,
        $roleTypes: ProfileEditorPayload["data"]["roleTypes"],
        $fieldSettings: ProfileEditorPayload["data"]["fieldSettings"],
    ): void {
        endpoints.value = $endpoints;
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

    return {
        init,
        endpoints,
        isLoading,
        roleTypes,
        fieldSettings,
        savedRoleTypes,
        selectedRoleType,
        savedSelectedRoleType,
        setSelectedRoleType,
    };
});
