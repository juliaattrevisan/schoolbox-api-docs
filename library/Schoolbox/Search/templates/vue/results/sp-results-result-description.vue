<script setup lang="ts">
/**
 * To display the search results description
 */

import ShowMatches from "@schoolboxVue/components/search/show-matches.vue";
import type { Result } from "../../js/types/Result";
import { useQueryStore } from "../store/spQueryStore";

interface Props {
    result: Result; // the result to display
}
const props = defineProps<Props>();

const queryStore = useQueryStore();
</script>

<template>
    <article>
        <!-- though this is CKEditor content, only text is returned in this context, so <media-article> is not required  -->
        <show-matches tag="p" :text="props.result.description as string" :query="queryStore.activeQuery" />
    </article>
</template>

<style scoped></style>
