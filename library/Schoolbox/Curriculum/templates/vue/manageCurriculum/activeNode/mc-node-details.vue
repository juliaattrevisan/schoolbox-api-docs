<script setup lang="ts">
/**
 * to view a nodes description and all the places the node is used
 */

import { computed } from "vue";
import type { Usage } from "@schoolboxCurriculum/types/Node";
import { ActivityTypes } from "@schoolboxCurriculum/types/Node";
import { getSpecificUsages } from "@schoolboxCurriculum/js/nodeUtils";
import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import { useActiveNodeStore } from "./store/mcActiveNodeStore";
import { useNodeDetailsStore } from "./store/mcNodeDetailsStore";
import McNodeDetailsUsage from "./mc-node-details-usage.vue";

interface Props {
    depth: number; // to gradually add more marginLeft to indicate node level depth
}

const props = defineProps<Props>();
const activeNodeStore = useActiveNodeStore();
const nodeDetailsStore = useNodeDetailsStore();

/**
 * to gradually and more marginLeft to indicate node level depth
 *
 * @returns { number }
 */
const marginLeft = computed(() => `calc(${props.depth * 2}rem)`);

/**
 * Whether we should show the usage list
 *
 * @returns { boolean }
 */
const hasUsage = computed(() => {
    return activeNodeStore.node.usage && activeNodeStore.node.usage.length > 0;
});

/**
 * Reload the page to try loading again
 */
function onRefreshSelected() {
    location.reload();
}
</script>

<template>
    <div class="node-container" :style="{ marginLeft: marginLeft }">
        <loading-table v-if="nodeDetailsStore.isProcessing" :rows="2" />
        <section v-else-if="nodeDetailsStore.hasLoadError">
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
                <div v-html="activeNodeStore.node.description"></div>
                <div v-if="hasUsage" class="usage-list-container">
                    <div v-if="activeNodeStore.node._usageCounts.course > 0">
                        <h4>Courses ({{ activeNodeStore.node._usageCounts.course }})</h4>
                        <ul class="usage-list">
                            <mc-node-details-usage
                                v-for="(usage, index) in getSpecificUsages(activeNodeStore.node.usage as Usage[], ActivityTypes.COURSE)"
                                :key="index"
                                :usage="usage"
                            />
                        </ul>
                    </div>
                    <div v-if="activeNodeStore.node._usageCounts.unit > 0">
                        <h4>Units ({{ activeNodeStore.node._usageCounts.unit }})</h4>
                        <ul class="usage-list">
                            <mc-node-details-usage
                                v-for="(usage, index) in getSpecificUsages(activeNodeStore.node.usage as Usage[], ActivityTypes.UNIT)"
                                :key="index"
                                :usage="usage"
                            />
                        </ul>
                    </div>
                    <div v-if="activeNodeStore.node._usageCounts.assessment > 0">
                        <h4>Learning Activities ({{ activeNodeStore.node._usageCounts.assessment }})</h4>
                        <ul class="usage-list">
                            <mc-node-details-usage
                                v-for="(usage, index) in getSpecificUsages(activeNodeStore.node.usage as Usage[], ActivityTypes.LEARNING_ACTIVITY)"
                                :key="index"
                                :usage="usage"
                            />
                        </ul>
                    </div>
                    <div v-if="activeNodeStore.node._usageCounts.badge > 0">
                        <h4>Badges ({{ activeNodeStore.node._usageCounts.badge }})</h4>
                        <ul class="usage-list">
                            <mc-node-details-usage
                                v-for="(usage, index) in getSpecificUsages(activeNodeStore.node.usage as Usage[], ActivityTypes.BADGE)"
                                :key="index"
                                :usage="usage"
                            />
                        </ul>
                    </div>
                </div>
            </fieldset>
        </section>
    </div>
</template>

<style lang="scss" scoped>
@import "./style/mcActiveNode.scss";

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
    gap: 0.5rem;
    margin: 0;
    li {
        display: inline-block;
    }
}
</style>
