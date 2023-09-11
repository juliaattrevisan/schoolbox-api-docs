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
    isSubResult?: boolean; // whether this is a sub result
}
const props = withDefaults(defineProps<Props>(), {
    isSubResult: false,
});

// const queryStore = useQueryStore();

/**
 * To display the date in format
 * @returns {string} the formatted date
 */
function getDateShort() {
    return window.dayjs(props.result.date).format("DD/MM/YYYY");
}

/**
 * To display the date in format
 * @returns {string} the formatted date
 */
function getDateLong() {
    return window.dayjs(props.result.date).format("dddd, MMMM D, YYYY");
}
</script>

<template>
    <span v-if="props.result.type === ResultTypes.NEWS" class="meta"
        >Posted by <a :href="props.result.creator?._links?.profile">{{ props.result.creator?.fullName }}</a> on
        {{ getDateShort() }}</span
    >
    <span v-else-if="props.result.type.includes(ResultTypes.FILE)" class="meta"
        >Created by <a :href="props.result.creator?._links?.profile">{{ props.result.creator?.fullName }}</a> on
        {{ getDateShort() }}</span
    >
    <span v-else-if="props.result.type === ResultTypes.EVENT" class="meta"> {{ getDateLong() }}</span>
    <span v-else-if="props.result.context.length !== 0 && !props.isSubResult" class="meta"
        ><template v-for="(context, index) in props.result.context" :key="context.id"
            ><a :href="context._links.view">{{ context.title }}</a
            ><template v-if="index < props.result.context.length - 1"> > </template>
        </template></span
    >
    <span v-else class="meta">{{ props.result.meta }}</span>
</template>

<style scoped></style>
