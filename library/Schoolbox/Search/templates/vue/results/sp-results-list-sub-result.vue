<script setup lang="ts">
/**
 * To display a single linked sub result within a result
 */

import { addMarks } from "@js/util/domUtils";
import { Result } from "../../../js/types/Result";
import { ResultTypes } from "../../../js/types/ResultTypes";
import { useQueryStore } from "../../store/spQueryStore";

interface Props {
    subResult: Result; // the result to display
    isLast: boolean; // whether this is the last result
}
const props = defineProps<Props>();

const queryStore = useQueryStore();

/**
 * To display the date in format
 * @returns {string} the formatted date
 */
const dateFormatted = window.Vue.computed(() => {
    return window.dayjs(props.subResult.date).format("DD/MM/YYYY");
});
</script>

<template>
    <div :class="`c-card c-card--child ${!props.isLast ? `c-card--border-end` : ''}`">
        <div class="l-flex-row l-gap-s l-pad-s">
            <div class="c-img c-img--s">
                <div class="c-img__wrapper">
                    <img
                        v-if="props.subResult.image"
                        :src="(props.subResult.image as string)"
                        :alt="`Photo of ${props.subResult.title}`"
                    />
                    <div v-else-if="props.subResult.icon" :class="`icon icon-${props.subResult.icon}`"></div>
                </div>
            </div>
            <div class="l-flex-col l-flex--justify-center f-no-margins">
                <h6 class="">
                    <!-- eslint-disable-next-line vue/no-v-html -->
                    <a
                        :href="props.subResult.url"
                        v-html="addMarks(props.subResult.title, queryStore.queryWordList)"
                    ></a>
                </h6>
                <span v-if="props.subResult.type.includes(ResultTypes.FILE)" class="meta"
                    >Created by
                    <a :href="props.subResult.creator?._links?.profile">{{ props.subResult.creator?.fullName }}</a> on
                    {{ dateFormatted }}</span
                >
            </div>
        </div>
    </div>
</template>

<style scoped></style>
