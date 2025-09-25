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
</script>

<template>
    <th
        :data-test="`markbook-assessment-${props.activity.id}`"
        :class="{
            'markbook-faded':
                mbStore.feedbackSelectedCell && mbStore.feedbackSelectedCell.activityId !== props.activity.id,
        }"
    >
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
