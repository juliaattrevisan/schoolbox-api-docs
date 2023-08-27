<script setup lang="ts">
/**
 * The creator filter input, to show results linked to a specific user
 * currently uses the exisitng jquery selectize plugin
 */

import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import { useFilterCreatorStore } from "./store/spFilterCreatorStore";

// const options = window.Vue.ref([]);s
// const selected = window.Vue.ref(null);

const filterCreatorStore = useFilterCreatorStore();
</script>

<template>
    <div class="l-flex-col">
        <label for="creator">Creator</label>
        <search-select endpoint="/ajax/user.php?groupID=0&query=" :selected="[]" />
    </div>
</template>

<style scoped></style>
