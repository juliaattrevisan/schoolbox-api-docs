<script setup lang="ts">
/**
 * To display the search results title
 */

// import { addMarks } from "@js/util/domUtils";
import { Result } from "../../js/types/Result";
import { ResultTypes } from "../../js/types/ResultTypes";
// import { useQueryStore } from "../store/spQueryStore";

interface Props {
    result: Result; // the result to display
}
const props = defineProps<Props>();

// const queryStore = useQueryStore();

/**
 * To display the date in format
 * @returns {string} the formatted date
 */
const dateFormatted = window.Vue.computed(() => {
    return window.dayjs(props.result.date).format("DD/MM/YYYY");
});
</script>

<template>
    <span v-if="props.result.type === ResultTypes.NEWS" class="meta"
        >Posted by <a :href="props.result.creator?._links?.profile">{{ props.result.creator?.fullName }}</a> on
        {{ dateFormatted }}</span
    >
    <span v-else-if="props.result.meta" class="meta">{{ props.result.meta }}</span>
    <span v-else-if="props.result.context" class="meta"
        ><template v-for="(context, index) in props.result.context" :key="context.id"
            ><a :href="context._links.view">{{ context.title }}</a
            ><template v-if="index < props.result.context.length - 1"> > </template>
        </template></span
    >
</template>

<style scoped></style>
