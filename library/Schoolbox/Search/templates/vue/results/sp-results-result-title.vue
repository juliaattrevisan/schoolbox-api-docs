<script setup lang="ts">
/**
 * To display the search results title
 */

import ShowMatches from "@schoolboxVue/components/search/show-matches.vue";
import type { Result } from "../../js/types/Result";
import { ResultTypes } from "../../js/types/ResultTypes";
import { useQueryStore } from "../store/spQueryStore";

interface Props {
    result: Result; // the result to display
    isSubResult?: boolean; // whether this is a sub result
}
const props = withDefaults(defineProps<Props>(), {
    isSubResult: false,
});

const emit = defineEmits<{
    badgeSelected: [value: Result];
}>();

const queryStore = useQueryStore();
</script>

<template>
    <template v-if="props.isSubResult">
        <h6 v-if="props.result.url">
            <show-matches tag="a" :href="props.result.url" :text="props.result.title" :query="queryStore.activeQuery" />
        </h6>
        <show-matches v-else tag="h6" :text="props.result.title" :query="queryStore.activeQuery" />
    </template>
    <template v-else>
        <h3 v-if="props.result.type === ResultTypes.BADGE">
            <show-matches
                tag="a"
                href="#open-badge-modal"
                :text="props.result.title"
                :query="queryStore.activeQuery"
                @click="emit('badgeSelected', props.result)"
            />
        </h3>
        <h3 v-else-if="props.result.url">
            <show-matches tag="a" :href="props.result.url" :text="props.result.title" :query="queryStore.activeQuery" />
        </h3>
        <show-matches v-else tag="h3" :text="props.result.title" :query="queryStore.activeQuery" />
    </template>
</template>

<style scoped></style>
