<script setup lang="ts">
/**
 * The creator filter input, to show results linked to a specific user
 * currently uses the exisitng jquery selectize plugin
 */

import { useFilterCreatorStore } from "./store/spFilterCreatorStore";
import vSelect from "vue-select";
import debounce from "lodash/debounce";
import "vue-select/dist/vue-select.css";

const options = window.Vue.ref([]);
// const selected = window.Vue.ref(null);

const filterCreatorStore = useFilterCreatorStore();

/**
 * Submit the current query, using _debounce to accommodate for key presses
 * so not sending on every key press
 */
const onSearch = debounce((query: string) => {
    if (query.length < 3) {
        return;
    }
    fetch(`/ajax/user.php?groupID=0&query=${query}`)
        .then((response) => response.json())
        .then((data) => {
            // totalCount.value = data.totalCount;
            options.value = data;
        })
        .finally(() => {
            // isLoading.value = false;
        })
        .catch(() => {
            // hasLoadError.value = true;
        });
}, 500);
</script>

<template>
    <div class="l-flex-col">
        <label for="creator">Creator</label>
        <!-- <input id="creator" type="text" /> -->
        <v-select
            v-model="filterCreatorStore.creator"
            class="schoolbox-vue-selectize"
            label="name"
            placeholder="Search for a user to filter by"
            :options="options"
            :clear-search-on-blur="
                function () {
                    return true;
                }
            "
            :clear-search-on-select="true"
            :close-on-select="false"
            @search="onSearch"
        >
            <template #option="option">
                <span class="d-center">
                    {{ option.name }}
                </span>
            </template>
            <template #selected-option="option">
                <span class="selected d-center">
                    {{ option.name }}
                </span>
            </template>
        </v-select>
    </div>
</template>

<style scoped></style>
