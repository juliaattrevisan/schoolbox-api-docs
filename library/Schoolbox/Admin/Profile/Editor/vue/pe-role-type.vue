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
}
</script>

<template>
    <dl class="tabs">
        <dd
            v-for="role in peStore.roleTypes"
            :key="role.roleType"
            :class="{ active: role === peStore.selectedRoleType }"
        >
            <a
                :aria-selected="role === peStore.selectedRoleType"
                :tabindex="role === peStore.selectedRoleType ? 0 : -1"
                @click="setSelectedRoleType(role)"
            >
                {{ capitalizeFirstLetter(role.roleType) }}
            </a>
        </dd>
    </dl>
</template>

<style scoped></style>
