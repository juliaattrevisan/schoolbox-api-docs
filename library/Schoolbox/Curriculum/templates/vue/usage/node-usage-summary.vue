<script setup lang="ts">
/**
 * Shows the activity count types when available and can be selected
 * to reveal the node description and connected material
 */

import type { Node } from "@schoolboxCurriculum/types/Node";
import { ActivityTypes } from "@schoolboxCurriculum/types/Node";
import { getUsageIcon, getTotalUsage } from "@schoolboxCurriculum/js/nodeUtils";

interface Props {
    node: Node; // the assigned node to display
    title: string; // the already computed node title
    isViewing: boolean; // to disable certain events when viewing the node
    isDisabled: boolean; // to disable certain events when updating the node
}

const props = defineProps<Props>();
const emit = defineEmits(["selected"]);
</script>

<template>
    <button
        v-if="getTotalUsage(props.node) > 0"
        class="usage-counts"
        :title="`View ${props.title}`"
        :disabled="props.isDisabled"
        @click.prevent="emit('selected')"
    >
        <sbx-label
            v-if="props.node._usageCounts?.course > 0"
            :text="props.node._usageCounts.course.toString()"
            sentiment="neutral"
            :icon="getUsageIcon(ActivityTypes.COURSE)"
            title="Courses"
        ></sbx-label>
        <sbx-label
            v-if="props.node._usageCounts?.unit > 0"
            :text="props.node._usageCounts.unit.toString()"
            sentiment="neutral"
            :icon="getUsageIcon(ActivityTypes.UNIT)"
            title="Units"
        ></sbx-label>
        <sbx-label
            v-if="props.node._usageCounts?.assessment > 0"
            :text="props.node._usageCounts.assessment.toString()"
            sentiment="neutral"
            :icon="getUsageIcon(ActivityTypes.LEARNING_ACTIVITY)"
            title="Learning activity"
        ></sbx-label>
        <sbx-label
            v-if="props.node._usageCounts?.badge > 0"
            :text="props.node._usageCounts.badge.toString()"
            sentiment="neutral"
            :icon="getUsageIcon(ActivityTypes.BADGE)"
            title="Badges"
        ></sbx-label>
    </button>
    <button
        v-if="getTotalUsage(props.node) > 0 || props.node.description !== ''"
        :class="`button-action-option ${props.isViewing ? 'icon-close' : 'icon-curriculum-details'}`"
        :title="`View ${props.title}`"
        :disabled="props.isDisabled"
        data-test-view
        @click.prevent="emit('selected')"
    >
        View {{ props.title }}
    </button>
</template>

<style lang="scss" scoped>
@import "@schoolboxCurriculum/vue/style/viewNode";
.button-action-option {
    @extend .button;
    color: var(--sbx-config-color-accent, var(--content-ui-foreground));
    font-size: 0rem;
    opacity: 0.7;
    padding: 0px 2px 0px 12px;
    &:hover {
        opacity: 1;
    }
}

.usage-counts {
    @extend .button;
    display: flex;
    gap: 0.5rem;
    min-width: auto;
    overflow: visible;
}
</style>
