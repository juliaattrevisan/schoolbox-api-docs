<script setup lang="ts">
/**
 * Tabs to select the current role type to edit the fields for
 */
import { capitalizeFirstLetter } from "@js/util/stringUtils";
import { useProfileEditorStore } from "./store/profileEditorStore";
import type { ProfileEditorRoleType } from "./types/profileEditorTypes";

const peStore = useProfileEditorStore();

window.Vue.onMounted(() => {
    /* When users manually change the hash in the url rather than using the ui
     */
    window.addEventListener("hashchange", () => {
        const roleType = window.location.hash.slice(1);
        const role = peStore.roleTypes.find((r) => r.roleType === roleType);

        if (role === undefined) {
            return; // It was changed to an invalid role type, so do nothing
        }

        peStore.setSelectedRoleType(role);
    });
});

/**
 * When the role type changes, change the url hash
 * @param role The role type to set
 */
function setSelectedRoleType(role: ProfileEditorRoleType): void {
    window.location.hash = role.roleType;
    peStore.setSelectedRoleType(role);

    // @ts-expect-error -- When we switch tabs, we need to tell foundation to fix the tooltips
    // eslint-disable-next-line @typescript-eslint/no-unsafe-call -- foundation isn't typed, see above
    $(document).foundation("tooltip", "reflow");
}
</script>

<template>
    <ul class="sub-nav">
        <li
            v-for="role in peStore.roleTypes"
            :key="role.roleType"
            :class="{ active: role === peStore.selectedRoleType }"
        >
            <a
                :aria-selected="role === peStore.selectedRoleType"
                :tabindex="role === peStore.selectedRoleType ? 0 : -1"
                :data-test="`pe-role-type-${role.roleType}`"
                @click="setSelectedRoleType(role)"
            >
                {{ capitalizeFirstLetter(role.roleType) }}
            </a>
        </li>
    </ul>
</template>

<style scoped></style>
