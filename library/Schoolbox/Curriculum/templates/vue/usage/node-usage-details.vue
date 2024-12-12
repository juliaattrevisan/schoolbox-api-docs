<script setup lang="ts">
/**
 * to view a nodes description and all the activities the node is linked to in the curriculum
 */

import locale from "@js/terminology/rosettaStone";
import type { Node, Usage } from "@schoolboxCurriculum/types/Node";
import { ActivityTypes } from "@schoolboxCurriculum/types/Node";
import { getSpecificUsages } from "@schoolboxCurriculum/js/nodeUtils";
import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import NodeUsageDetailsDescription from "./node-usage-details-description.vue";
import NodeUsageDetailsItem from "./node-usage-details-item.vue";

interface Props {
    node: Node; // the assigned node to display
    depth: number; // to gradually add more marginLeft to indicate node level depth
    isProcessing: boolean; // whether we are currently loading the node usage
    hasLoadError: boolean; // whether there was an error loading the node usage
}

const props = defineProps<Props>();
const emit = defineEmits(["select"]);

/**
 * to gradually and more marginLeft to indicate node level depth
 * @returns { number }
 */
const marginLeft = window.Vue.computed(() => `calc(${props.depth * 2}rem)`);

/**
 * Reload the page to try loading again
 */
function onRefreshSelected(): void {
    window.location.reload();
}

/**
 * Whether we should show the usage lists
 * @returns { boolean }
 */
const hasUsage = window.Vue.computed(() => {
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
 * @returns { Array }
 */
const activeUsages = window.Vue.computed(() => {
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
            title: locale.unit.properPlural(),
            count: props.node._usageCounts.unit,
            usages: getSpecificUsages(props.node.usage as Usage[], ActivityTypes.UNIT),
        });
    }
    if (props.node._usageCounts.assessment > 0) {
        usages.push({
            title: `Learning ${locale.activity.properPlural()}`,
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
 * Whether there is useful usage content to display
 */
const hasContent = window.Vue.computed(() => hasUsage.value || props.node.description);
</script>

<template>
    <div :class="{ 'node-container': hasContent }" :style="{ marginLeft: marginLeft }">
        <loading-table v-if="props.isProcessing" :rows="2" />
        <section v-else-if="props.hasLoadError">
            <fieldset class="content">
                <div class="alert-box alert">
                    Sorry, we are currently unable to load the Curriculum usage data. Please
                    <a href="#refresh-page" title="refresh the page" @click.prevent="onRefreshSelected">
                        refresh the page
                    </a>
                    to try again and contact your support if the problem persists.
                </div>
            </fieldset>
        </section>
        <section v-else-if="hasContent">
            <fieldset class="content">
                <node-usage-details-description :node="props.node" />
                <div v-if="hasUsage" class="usage-list-container">
                    <h3>Total Usage</h3>
                    <div v-for="activeUsage in activeUsages" :key="activeUsage.title">
                        <h4>{{ activeUsage.title }} ({{ activeUsage.count }})</h4>
                        <ul class="usage-list">
                            <node-usage-details-item
                                v-for="(usage, index) in activeUsage.usages"
                                :key="index"
                                :usage="usage"
                                @select="emit('select', usage)"
                            />
                        </ul>
                    </div>
                </div>
            </fieldset>
        </section>
    </div>
</template>

<style lang="scss" scoped>
@import "@schoolboxCurriculum/vue/manageCurriculum/activeNode/style/mcActiveNode.scss";

.content {
    margin: 0;
    padding: 1rem 1rem 1rem 0rem;
}
.usage-list-container {
    display: flex;
    flex-direction: column;
    gap: 1rem 0;
    h3 {
        margin-bottom: 0;
    }
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
