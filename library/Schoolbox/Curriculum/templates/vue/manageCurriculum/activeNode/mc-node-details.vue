<script setup lang="ts">
/**
 * to view a nodes full breadcrumb trail, its description,
 * and all the places the node is used
 */

import { computed } from "vue";
import { useActiveNodeStore } from "./store/mcActiveNodeStore";
import { useNodeDetailsStore } from "./store/mcNodeDetailsStore";
import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import HorizontalScoller from "@schoolboxVue/components/scroll/horizontal-scoller";
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
</script>

<template>
    <div class="node-container" :style="{ marginLeft: marginLeft }">
        <loading-table v-if="nodeDetailsStore.isProcessing" :rows="2" />
        <section v-else>
            <fieldset class="content">
                <div v-html="activeNodeStore.node.description"></div>
                <div v-if="hasUsage" class="usage-list-container">
                    <h4>Connected Material ({{ activeNodeStore.node.usageCount }})</h4>
                    <horizontal-scoller :has-buttons="false" :height="80" :has-content-change="false">
                        <ul class="usage-list">
                            <mc-node-details-usage
                                v-for="(usage, index) in activeNodeStore.node.usage"
                                :key="index"
                                :usage="usage"
                            />
                        </ul>
                    </horizontal-scoller>
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
    padding-top: 1rem;
}
.usage-list {
    margin: 0;
}
</style>
