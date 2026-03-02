<script setup lang="ts">
/**
 * Tabs to select the current role type to edit the fields for
 */
import { capitalizeFirstLetter } from "@js/util/stringUtils";
import { useProfileEditorStore } from "./store/profileEditorStore";
import type { ProfileEditorRoleType } from "./types/profileEditorTypes";

const peStore = useProfileEditorStore();

/**
 * When the role type changes, change the url hash
 * @param role The role type to set
 */
function setSelectedRoleType(role: ProfileEditorRoleType): void {
    window.location.hash = role.roleType;
    peStore.setSelectedRoleType(role);

    if (document.readyState === "complete") {
        // When a setting is added after the page is loaded, we need to tell foundation to fix the tooltips
        window.jQuery(document).foundation("tooltip", "reflow");
    }
}

/**
 * Set the selected role type based on the current URL hash
 * Will fall back to the first role type if the hash is invalid
 */
function applyRoleTypeHash(): void {
    const hashValue = window.location.hash.slice(1);
    const roleType = peStore.roleTypes.find((r) => r.roleType === hashValue);

    if (roleType === undefined) {
        setSelectedRoleType(peStore.roleTypes[0]);
        return;
    }

    setSelectedRoleType(roleType);
}

window.Vue.onMounted(async () => {
    await window.Vue.nextTick(); // To wait for the store to have data

    /* Apply the initial role type if the hash has one */
    applyRoleTypeHash();

    /* When users manually change the hash in the url rather than using the ui */
    window.addEventListener("hashchange", applyRoleTypeHash);
});
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
