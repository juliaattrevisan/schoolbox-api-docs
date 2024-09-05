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

const emit = defineEmits(["select-all"]);

const props = defineProps({
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
});

/**
 * To determine the check display of the checkbox
 *
 * @returns { Boolean }
 */
const isChecked = window.Vue.computed(() => {
    return props.isAllResultsSelected ? true : false;
});

/**
 * dispatch to parent so can move to relevant settings page
 */
function onSelectAll() {
    emit("select-all");
}
</script>

<template>
    <button class="search-result" @click.prevent="onSelectAll">
        <div class="checklist">
            <input
                id="checkbox-select-all"
                type="checkbox"
                :checked="isChecked"
                :class="{ indeterminate: isSomeResultsSelected }"
            />
            <label for="checkbox-select-all"></label>
        </div>
        <strong>Select All</strong>
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
.search-result-active {
    background: var(--content-ui-hover);
}
input[type="checkbox"]:not(.plain) + label {
    margin: 0;
}
</style>
