<script setup lang="ts">
import type { Ref } from "vue";
import { usePopOut } from "@schoolboxVue/composables/popOut/usePopOut";
import { useMarkbookStore } from "./store/markbookStore";
import type { MarkbookActivity } from "./types/payload";

interface Props {
    activity: MarkbookActivity;
}

const props = defineProps<Props>();
const mbStore = useMarkbookStore();

const activityHeaderSelection = window.Vue.inject<Ref<MarkbookActivity | null>>("activityHeaderSelection");
const activityHeaderDropdownEl = window.Vue.inject<Ref<HTMLElement | null>>("activityHeaderDropdownEl");
if (activityHeaderDropdownEl === undefined) {
    throw new Error("Unable to inject activityHeaderDropdownEl");
}

const referenceEl = window.Vue.useTemplateRef<HTMLElement | null>("referenceEl");
const { onButtonSelected } = usePopOut(referenceEl, activityHeaderDropdownEl, "Actions", "bottom-start");

/**
 * When the user clicks on the activity, open the pop-out menu if desired
 */
async function onTitleClick(): Promise<void> {
    if (activityHeaderSelection === undefined) {
        throw new Error("Unable to inject activityHeaderSelection");
    }

    if (!mbStore.data) {
        return; // exit early
    }

    activityHeaderSelection.value = props.activity;
    await onButtonSelected();
}

/**
 * A markbook column is faded if the selected feedback cell exists
 * and isn't for the current column (activity)
 */
const isFaded = window.Vue.computed(() => {
    if (mbStore.feedbackSelectedCell === null) {
        return false;
    }

    return mbStore.feedbackSelectedCell.activityId !== props.activity.id;
});
</script>

<template>
    <template v-if="props.activity.splitComments">
        <th :class="{ 'markbook-faded': isFaded }">
            <div><span>Mark</span></div>
        </th>
        <th :class="{ 'markbook-faded': isFaded }">
            <div><span>Comment</span></div>
        </th>
    </template>

    <th v-else :data-test="`markbook-assessment-${props.activity.id}`" :class="{ 'markbook-faded': isFaded }">
        <div ref="referenceEl">
            <!-- A teacher clicking an assessment from the markbook is most likely interested in seeing more granular results -->
            <span>
                <a
                    v-if="props.activity._links?.summary"
                    :href="props.activity._links?.summary"
                    :title="props.activity.title"
                    @click.prevent="onTitleClick"
                >
                    {{ props.activity.title }}
                </a>
                <span v-else :title="props.activity.title">{{ props.activity.title }}</span>
                <br />
                <span class="meta">{{ props.activity.typeName }}</span>
            </span>
        </div>
    </th>
</template>

<style scoped></style>
