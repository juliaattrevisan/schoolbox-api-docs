<script setup lang="ts">
/**
 * To display the search results description
 */

import { addMarks } from "@js/util/domUtils";
import { Result } from "../../js/types/Result";
import { useQueryStore } from "../store/spQueryStore";

interface Props {
    result: Result; // the result to display
}
const props = defineProps<Props>();

const queryStore = useQueryStore();
</script>

<template>
    <article>
        <!-- eslint-disable-next-line vue/no-v-html -->
        <p v-html="addMarks(props.result.description as string, queryStore.queryWordList)"></p>
    </article>
</template>

<style scoped></style>
