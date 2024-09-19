<script setup lang="ts">
/**
 * Tabs to select the current role type to edit the fields for
 */
import { capitalizeFirstLetter } from "@js/util/stringUtils";
import { useProfileEditorStore } from "./store/profileEditorStore";

const peStore = useProfileEditorStore();
</script>

<template>
    <dl v-if="!peStore.isLoading" class="tabs">
        <dd v-for="role in peStore.roleTypes" :key="role.name" :class="{ active: role === peStore.selectedRoleType }">
            <a
                :aria-selected="role === peStore.selectedRoleType"
                :tabindex="role === peStore.selectedRoleType ? 0 : -1"
                @click="peStore.setSelectedRoleType(role)"
            >
                {{ capitalizeFirstLetter(role.name) }}
            </a>
        </dd>
    </dl>
</template>

<style scoped></style>
