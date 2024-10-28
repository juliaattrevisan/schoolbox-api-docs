<script setup lang="ts">
/**
 * To search and select for curriculum in curriculum coverage report
 * note - This is a temporary solution until the curriculum-selector is updated
 * It's specifically for the curriculum coverage report for now as I want to limit
 * the scope of the changes
 */

import { unref } from "vue";
import { watchIgnorable } from "@vueuse/core";
import SearchSelect from "@schoolboxVue/components/searchSelect/search-select.vue";
import { useSelectedNodesStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/csSelectedNodesStore";
import { useNodeTitle } from "@schoolboxCurriculum/vue/composables/useNodeTitle";
import type { Node } from "@schoolboxCurriculum/types/Node";

interface Props {
    endpoint: string; // The curriculum api endpoint
    label?: string; // The label text
    labelId?: string; // The label id
    isRequired?: boolean; // Whether the input requires a value
    hasError?: boolean; // Whether the input has an error
    errorMessage?: string; // The error message to display
    isReadOnly?: boolean; // Whether the input is read only
}
const props = withDefaults(defineProps<Props>(), {
    label: "Select Curriculum",
    labelId: "curriculum",
    errorMessage: "Please select at least one curriculum outcome",
});

const store = useSelectedNodesStore();
const endpoint = `${props.endpoint}?enabled=1&limit=50&includeWithoutCode=1&query=`; // The endpoint to retrieve the curriculum data from
const results = window.Vue.ref<Node[]>([]);

/**
 * To inject the code / title of the node into the name field
 */
const { ignoreUpdates } = watchIgnorable(results, (newResults: Node[] | unknown[]) => {
    // ignore updates to avoid an infinite loop...
    ignoreUpdates(() => {
        if (newResults instanceof Array && newResults.length === 0) {
            return;
        }
        results.value = results.value.map((result) => {
            const { title } = useNodeTitle(result);
            return {
                ...result,
                id: result.id,
                name: title.value, // manipulate the name to display the code / title of the curriculum
            };
        });
    });
});
</script>

<template>
    <search-select
        v-model:selected="store.nodes"
        v-model:results="results"
        :label="props.label"
        :label-id="props.labelId"
        :is-required="props.isRequired"
        :endpoint="endpoint"
        :has-error="props.hasError"
        :error-message="props.errorMessage"
        :is-read-only="props.isReadOnly"
        :allow-partial-matches="true"
    />
</template>

<style scoped></style>
