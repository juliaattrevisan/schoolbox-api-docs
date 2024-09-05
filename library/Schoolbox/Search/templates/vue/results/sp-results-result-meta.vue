<script setup lang="ts">
/**
 * To display the search results title
 */

import { Result } from "../../js/types/Result";
import { ResultTypes } from "../../js/types/ResultTypes";

interface Props {
    result: Result; // the result to display
    isSubResult?: boolean; // whether this is a sub result
}
const props = withDefaults(defineProps<Props>(), {
    isSubResult: false,
});

/**
 * To display the date in format
 * @returns the formatted date
 */
function getDateShort(): string {
    return window.dayjs(props.result.date).format("DD/MM/YYYY");
}

/**
 * To display the date in format
 * @returns the formatted date
 */
function getDateLong(): string {
    return window.dayjs(props.result.date).format("dddd, MMMM D, YYYY");
}
</script>

<template>
    <span v-if="props.result.type === ResultTypes.NEWS" class="meta"
        >{{ props.result.typeTitle }} | Posted by
        <a :href="props.result.creator?._links?.profile">{{ props.result.creator?.fullName }}</a> on
        {{ getDateShort() }}</span
    >
    <span v-else-if="props.result.type.includes(ResultTypes.FILE)" class="meta"
        >{{ props.result.typeTitle }} | Created by
        <a :href="props.result.creator?._links?.profile">{{ props.result.creator?.fullName }}</a> on
        {{ getDateShort() }}</span
    >
    <span v-else-if="props.result.type === ResultTypes.EVENT" class="meta"
        >{{ props.result.typeTitle }} | {{ getDateLong() }}</span
    >
    <span v-else-if="props.result.context?.length !== 0 && !props.isSubResult" class="meta"
        >{{ props.result.typeTitle }} |
        <template v-for="(context, index) in props.result.context" :key="context.id"
            ><a :href="context._links.view">{{ context.title }}</a
            ><template v-if="index < props.result.context.length - 1"> > </template>
        </template></span
    >
    <span v-else class="meta"
        >{{ props.result.typeTitle }}<template v-if="props.result.meta"> | {{ props.result.meta }}</template></span
    >
</template>

<style scoped></style>
