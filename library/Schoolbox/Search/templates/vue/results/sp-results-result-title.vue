<script setup lang="ts">
/**
 * To display the search results title
 */

import { addMarks } from "@js/util/domUtils";
import type { Result } from "../../js/types/Result";
import { ResultTypes } from "../../js/types/ResultTypes";
import { useQueryStore } from "../store/spQueryStore";
import { useBadgeStore } from "./store/spBadgeStore";

interface Props {
    result: Result; // the result to display
    isSubResult?: boolean; // whether this is a sub result
}
const props = withDefaults(defineProps<Props>(), {
    isSubResult: false,
});

const queryStore = useQueryStore();
const badgeStore = useBadgeStore();

/**
 * When a badge is selected, set it in the store
 */
function onBadgeSelected() {
    badgeStore.setSelectedBadge(props.result);
}
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
        <h3 v-if="props.result.type === ResultTypes.BADGE">
            <!-- eslint-disable-next-line vue/no-v-html -->
            <a
                href="#open-badge-modal"
                @click.prevent="onBadgeSelected"
                v-html="addMarks(props.result.title, queryStore.queryWordList)"
            ></a>
        </h3>
        <h3 v-else-if="props.result.url">
            <!-- eslint-disable-next-line vue/no-v-html -->
            <a :href="props.result.url" v-html="addMarks(props.result.title, queryStore.queryWordList)"></a>
        </h3>
        <!-- eslint-disable-next-line vue/no-v-html -->
        <h3 v-else v-html="addMarks(props.result.title, queryStore.queryWordList)"></h3>
    </template>
</template>

<style scoped></style>
