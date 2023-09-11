<script setup lang="ts">
/**
 * To display the search results title
 */

import { addMarks } from "@js/util/domUtils";
import { Result } from "../../js/types/Result";
import { useQueryStore } from "../store/spQueryStore";

interface Props {
    result: Result; // the result to display
    isSubResult?: boolean; // whether this is a sub result
}
const props = withDefaults(defineProps<Props>(), {
    isSubResult: false,
});

const queryStore = useQueryStore();
</script>

<template>
    <template v-if="props.isSubResult">
        <h6 v-if="props.result.url">
            <!-- eslint-disable-next-line vue/no-v-html -->
            <a :href="props.result.url" v-html="addMarks(props.result.title, queryStore.queryWordList)"></a>
        </h6>
        <!-- eslint-disable-next-line vue/no-v-html -->
        <h6 v-else v-html="addMarks(props.result.title, queryStore.queryWordList)"></h6>
    </template>
    <template v-else>
        <h3 v-if="props.result.url">
            <!-- eslint-disable-next-line vue/no-v-html -->
            <a :href="props.result.url" v-html="addMarks(props.result.title, queryStore.queryWordList)"></a>
        </h3>
        <!-- eslint-disable-next-line vue/no-v-html -->
        <h3 v-else v-html="addMarks(props.result.title, queryStore.queryWordList)"></h3>
    </template>
</template>

<style scoped></style>
