<script setup lang="ts">
/**
 * to view a nodes description and all the places the node is used
 */

import { ref, computed } from "vue";
import type { Node, BadgeUsageObject, Usage } from "@schoolboxCurriculum/types/Node";
import { ActivityTypes } from "@schoolboxCurriculum/types/Node";
import { getSpecificUsages } from "@schoolboxCurriculum/js/nodeUtils";
import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import BadgeModal from "@schoolboxBadge/vue/badge-modal";
// import { useActiveNodeStore } from "./store/mcActiveNodeStore";
import { useNodeUsageStore } from "@schoolboxCurriculum/vue/manageCurriculum/activeNode/store/mcNodeUsageStore";
import NodeUsageDetailsItem from "./node-usage-details-item.vue";

interface Props {
    node: Node; // the assigned node to display
    depth: number; // to gradually add more marginLeft to indicate node level depth
}

const props = defineProps<Props>();
// const activeNodeStore = useActiveNodeStore();
const nodeUsageStore = useNodeUsageStore();
const isBadgeModalOpen = ref(false); // whether the modal to display the badge details is open
const currentBadge = ref<BadgeUsageObject | null>(null); // the badge to display in the modal when a badge usage is selected

/**
 * to gradually and more marginLeft to indicate node level depth
 *
 * @returns { number }
 */
const marginLeft = computed(() => `calc(${props.depth * 2}rem)`);

/**
 * Whether we should show the usage lists
 *
 * @returns { boolean }
 */
const hasUsage = computed(() => {
    return props.node.usage && props.node.usage.length > 0;
});

/**
 * An activity to display in the usage list
 */
type ActiveUsageType = {
    title: string;
    count: number;
    usages: Usage[];
};

/**
 * Return the list of active usages, grouped by type
 *
 * @returns { Array }
 */
const activeUsages = computed(() => {
    const usages: ActiveUsageType[] = [];
    if (props.node._usageCounts.course > 0) {
        usages.push({
            title: "Courses",
            count: props.node._usageCounts.course,
            usages: getSpecificUsages(props.node.usage as Usage[], ActivityTypes.COURSE),
        });
    }
    if (props.node._usageCounts.unit > 0) {
        usages.push({
            title: "Units",
            count: props.node._usageCounts.unit,
            usages: getSpecificUsages(props.node.usage as Usage[], ActivityTypes.UNIT),
        });
    }
    if (props.node._usageCounts.assessment > 0) {
        usages.push({
            title: "Learning Activities",
            count: props.node._usageCounts.assessment,
            usages: getSpecificUsages(props.node.usage as Usage[], ActivityTypes.LEARNING_ACTIVITY),
        });
    }
    if (props.node._usageCounts.badge > 0) {
        usages.push({
            title: "Badges",
            count: props.node._usageCounts.badge,
            usages: getSpecificUsages(props.node.usage as Usage[], ActivityTypes.BADGE),
        });
    }
    return usages;
});

/**
 * Reload the page to try loading again
 */
function onRefreshSelected() {
    window.location.reload();
}

/**
 * Opens a modal window for viewing this badge's full details.
 *
 * @param { BadgeUsageObject } badge - the badge to display
 */
function openDetailModal(badge: BadgeUsageObject) {
    currentBadge.value = badge;
    isBadgeModalOpen.value = true;
}

/**
 * Open a new page or open the badge modal
 *
 * @param { Usage } usage - the selected usage
 */
function onUsageSelected(usage: Usage) {
    openDetailModal(usage.object as BadgeUsageObject);
}

/**
 * the badge modal is closed, so we should set the boolean to
 * false so we can open it again
 */
function onDetailModalClosed() {
    isBadgeModalOpen.value = false;
}
</script>

<template>
    <div class="node-container" :style="{ marginLeft: marginLeft }">
        <loading-table v-if="nodeUsageStore.isProcessing" :rows="2" />
        <section v-else-if="nodeUsageStore.hasLoadError">
            <fieldset class="content">
                <div class="alert-box alert">
                    Sorry, we are currently unable to load the Curriculum usage data. Please
                    <a href="#refresh-page" title="refresh the page" @click.prevent="onRefreshSelected"
                        >refresh the page</a
                    >
                    to try again and contact your support if the problem persists.
                </div>
            </fieldset>
        </section>
        <section v-else>
            <fieldset class="content">
                <div v-html="props.node.description"></div>
                <div v-if="hasUsage" class="usage-list-container">
                    <div v-for="activeUsage in activeUsages">
                        <h4>{{ activeUsage.title }} ({{ activeUsage.count }})</h4>
                        <ul class="usage-list">
                            <node-usage-details-item
                                v-for="(usage, index) in activeUsage.usages"
                                :key="index"
                                :usage="usage"
                                @select="onUsageSelected"
                            />
                        </ul>
                    </div>
                </div>
            </fieldset>
        </section>
        <badge-modal :is-open="isBadgeModalOpen" :badge="currentBadge" @closed="onDetailModalClosed" />
    </div>
</template>

<style lang="scss" scoped>
@import "@schoolboxCurriculum/vue/manageCurriculum/activeNode/style/mcActiveNode.scss";

.content {
    margin: 0;
}
.usage-list-container {
    display: flex;
    flex-direction: column;
    gap: 1rem 0;
    padding-top: 1rem;
    h4 {
        margin-bottom: 0.5rem;
    }
}

.usage-list {
    display: flex; // so the items can be the same height when the text content within varies
    flex-wrap: wrap;
    gap: 0.5rem;
    margin: 0;
    li {
        display: inline-block;
    }
}
</style>
