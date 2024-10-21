<script setup lang="ts">
/**
 * The creator filter input, to show results linked to a specific user
 */

import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import type { Creator } from "./store/spFilterCreatorsStore";
import { useFilterCreatorsStore } from "./store/spFilterCreatorsStore";

const filterCreatorsStore = useFilterCreatorsStore();

const currentUser: Creator = {
    id: window.schoolboxUser.id,
    name: window.schoolboxUser.name,
};
</script>
<template>
    <div class="l-flex-col">
        <search-select
            v-model:selected-items="filterCreatorsStore.creator"
            label="Creator"
            label-id="creator"
            endpoint="/ajax/user.php?groupID=0&query="
            :is-single-select="true"
            :default-results="[currentUser]"
            placeholder="Search for users..."
        />
    </div>
</template>

<style scoped></style>
