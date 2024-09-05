<script setup>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * An individual class search result that matches the search query with search result
 * highlighted within. Selecting will add the class to the list
 * Also included is a 'Select All' feature which toggles the selection of all
 * results currently shown
 */

import ShowMatches from "@schoolboxVue/components/search/show-matches.vue";

const emit = defineEmits(["select"]);

const props = defineProps({
    result: {
        type: Object,
        required: true,
        default: () => {
            return {
                id: 0,
                name: "name",
                isSelected: false,
            };
        },
    },
    isAllResultsSelected: {
        //to determine the behaviour of the 'Select all classes' button
        type: Boolean,
        required: true,
        default: false,
    },
    isSomeResultsSelected: {
        //to add the 'indeterminate' class to the select all checkbox
        type: Boolean,
        required: true,
        default: false,
    },
    query: {
        // current search query
        type: String,
        required: true,
        default: "",
    },
});

/**
 * To determine the check display of the checkbox
 *
 * @returns { Boolean }
 */
const isChecked = window.Vue.computed(() => {
    return props.result.isSelected;
});

/**
 * dispatch to parent so can move to relevant settings page
 */
function onSelect() {
    emit("select", props.result);
}
</script>

<template>
    <button class="search-result" @click.prevent="onSelect">
        <div class="checklist">
            <input :id="`checkbox-${result.id}`" type="checkbox" :checked="isChecked" />
            <label :for="`checkbox-${result.id}`"></label>
        </div>
        <show-matches tag="span" :text="props.result.name" :query="props.query" />
    </button>
</template>

<style lang="scss" scoped>
.search-result {
    background: white;
    display: flex;
    font-size: 0.825rem;
    margin: 0;
    padding: 0.5rem 0.75rem;
    text-align: left;
    transition: background 0.2s;
    width: 100%;
    &:hover {
        background: var(--content-ui-hover);
    }
}

input[type="checkbox"]:not(.plain) + label {
    margin: 0;
}
</style>
