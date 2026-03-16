<script setup>
/* eslint-disable @eslint-community/eslint-comments/disable-enable-pair -- see below */
/* eslint-disable @eslint-community/eslint-comments/no-unlimited-disable -- see below */
/* eslint-disable -- Ignore this file for ESLint checks for now (SA-18376) */
/* eslint-enable vue/no-v-html */
/**
 * To search for members to add
 */

import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import { useStore } from "../store/manageGroupStore";
import { useMembersStore } from "../store/mgMembersStore";
import { useAddMembersStore } from "../store/mgAddMembersStore";
import { useSearchStore } from "../store/mgSearchStore";
import FromTypes from "./mgAddMembersFromTypes";

const store = useStore();
const membersStore = useMembersStore();
const addMembersStore = useAddMembersStore();
const searchStore = useSearchStore();

/**
 * Update the input place holder text depending on the currentFromSelect
 *
 * @returns { String }
 */
const inputPlaceholder = window.Vue.computed(() => {
    const placeholderOptions = {
        [FromTypes.NAME]: "Search users...",
        [FromTypes.GROUP]: "Search groups...",
        [FromTypes.ROLE]: "Search roles...",
        [FromTypes.LIST]: "Add from a list...",
    };
    return placeholderOptions[addMembersStore.fromType];
});

/**
 * Get the search endpoint without the query parameter
 * search-select will append the query parameter automatically
 *
 * @returns { String }
 */
const searchEndpoint = window.Vue.computed(() => {
    const endpoints = {
        [FromTypes.NAME]: "/ajax/user.php?groupID=0&",
        [FromTypes.GROUP]: "/group/searchGroups",
        [FromTypes.ROLE]: "/group/searchRoles",
        [FromTypes.LIST]: "/group/searchUsers",
    };
    return endpoints[addMembersStore.fromType];
});

/**
 * Handle the search response from the API
 * Filter out members that are already in the group before displaying
 *
 * @param { unknown } json - The search response
 */
function onSearchResponse(json) {
    // Store raw results
    let rawResults = [];
    if (json instanceof Array) {
        rawResults = json;
    } else if (typeof json === "object" && json !== null && "data" in json && Array.isArray(json.data)) {
        rawResults = json.data;
    }

    // Apply filtering logic based on fromType
    let filteredResults = rawResults;
    if (addMembersStore.fromType === FromTypes.NAME || addMembersStore.fromType === FromTypes.LIST) {
        // Filter out users already in the group (use Set for O(1) lookups)
        const memberIdSet = new Set(membersStore.members.map((member) => member.id));
        filteredResults = rawResults.filter((result) => !memberIdSet.has(result.id));
    } else if (addMembersStore.fromType === FromTypes.GROUP) {
        // Filter out the current group itself
        filteredResults = rawResults.filter((result) => result.id !== store.id);
    }

    // Update the results in the search-select component
    searchStore.results = filteredResults;
}

/**
 * Watch for changes to selectedResults and ensure each item has the fromType property
 * This is needed because the store's addMembers action expects fromType on each selected result
 */
window.Vue.watch(
    () => searchStore.selectedResults,
    (newSelectedResults) => {
        // Check if any items are missing fromType and add it
        let needsUpdate = false;
        const updatedResults = newSelectedResults.map((result) => {
            if (!result.fromType) {
                needsUpdate = true;
                return {
                    ...result,
                    fromType: addMembersStore.fromType === "list" ? "name" : addMembersStore.fromType,
                };
            }
            return result;
        });

        // Only update if we actually added fromType to prevent infinite loops
        if (needsUpdate) {
            searchStore.selectedResults = updatedResults;
        }
    },
    { deep: true },
);
</script>

<template>
    <search-select
        v-model:selected="searchStore.selectedResults"
        v-model:results="searchStore.results"
        :endpoint="searchEndpoint"
        :placeholder="inputPlaceholder"
        :manage-search-response="true"
        label-id="manage-group-search"
        data-test="manage-group-search-text"
        @search-response="onSearchResponse"
    />
</template>

<style lang="scss" scoped>
// Styles handled by search-select component
</style>
