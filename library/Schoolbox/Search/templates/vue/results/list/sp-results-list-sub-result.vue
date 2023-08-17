<script setup lang="ts">
/**
 * To display a single linked sub result within a result
 */

import { addMarks } from "@js/util/domUtils";
import { Result } from "../../../js/types/Result";
import { useQueryStore } from "../../store/spQueryStore";

interface Props {
    subResult: Result; // the result to display
    isLast: boolean; // whether this is the last result
}
const props = defineProps<Props>();

const queryStore = useQueryStore();
</script>

<template>
    <div :class="`c-card c-card--child ${!props.isLast ? `c-card--border-end` : ''}`">
        <div class="l-flex-row l-gap-m">
            <div class="c-img c-img--xs">
                <div class="c-img__wrapper">
                    <div :class="`icon icon-document`"></div>
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
            </div>
        </div>
    </div>
</template>

<style scoped></style>
