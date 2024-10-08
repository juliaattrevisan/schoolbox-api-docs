<script setup lang="ts">
/**
 * To search for searchResults and select them
 */

import { onClickOutside } from "@vueuse/core";
import type { Result } from "./types/Result";
import SearchSelectSelected from "./search-select-selected.vue";
import SearchSelectInput from "./search-select-input.vue";
import SearchSelectResults from "./search-select-results.vue";
import { useSearchStore } from "./store/searchSelectStore";

interface Props {
    endpoint: string; // the search api endpoint
    selected: Result[]; // the selected searchResults
    defaultResults?: Result[]; // show selection options when the search input is empty
    isSingleSelect?: boolean; // whether only one result can be selected
    formValueName?: string; // the name of the form value
    placeholder?: string; // the placeholder text
}
const props = withDefaults(defineProps<Props>(), {
    defaultResults: undefined,
    isSingleSelect: false,
    formValueName: "",
    placeholder: "Search...",
});

const emit = defineEmits<{
    select: [result: Result];
    remove: [result: Result];
}>();

const store = useSearchStore();
const searchSelectEl = window.Vue.ref<HTMLDivElement | null>(null);

/**
 * Pass through the endpoint to the store
 */
window.Vue.onMounted(() => {
    store.init(
        props.endpoint,
        props.selected,
        props.defaultResults !== undefined ? props.defaultResults : [],
        props.isSingleSelect,
    );
});

/**
 * To toggle the selected state of the result
 * @param result - the result selected
 */
function onSelected(result: Result): void {
    emit("select", result);
}

/**
 * To remove a result
 * @param result - the result removed
 */
function onRemoved(result: Result): void {
    emit("remove", result);
}

/**
 * To close the search results when clicking outside
 */
onClickOutside(searchSelectEl, () => {
    store.clear();
});
</script>

<template>
    <div ref="searchSelectEl" class="c-search-select">
        <search-select-selected
            :selected="props.selected"
            :form-value-name="props.formValueName"
            :is-single-select="props.isSingleSelect"
            @remove="onRemoved"
        />
        <search-select-input :placeholder="props.placeholder" />
        <search-select-results :is-single-select="props.isSingleSelect" :selected="selected" @select="onSelected" />
    </div>
</template>

<style scoped></style>
