<script setup lang="ts">
/**
 * to view a nodes full breadcrumb trail, its description,
 * and all the places the node is used
 */

import { computed } from "vue";
import { slideDownOnEnter, slideUpOnLeave } from "@schoolboxVue/transitions/transitions";
import { useStore } from "../store/manageCurriculumStore";
import { useActiveNodeStore } from "./store/mcActiveNodeStore";
import { useNodeDetailsStore } from "./store/mcNodeDetailsStore";
import LoadingTable from "@schoolboxVue/components/loading/loading-table.vue";
import HorizontalScoller from "@schoolboxVue/components/scroll/horizontal-scoller";
import McNodeDetailsUsage from "./mc-node-details-usage.vue";

interface Props {
    depth: number; // to gradually add more marginLeft to indicate node level depth
}

const props = defineProps<Props>();
const store = useStore();
const activeNodeStore = useActiveNodeStore();
const nodeDetailsStore = useNodeDetailsStore();

/**
 * to gradually and more marginLeft to indicate node level depth
 *
 * @returns { number }
 */
const marginLeft = computed(() => `calc(${props.depth * 2}rem)`);

/**
 * Display the list of breadcrumbs to provide context on where the selected node comes from
 *
 * @returns { string }
 */
const breadcrumbs = computed(() => {
    if (activeNodeStore.node.breadcrumbs && activeNodeStore.node.breadcrumbs.length > 0) {
        const justNames = activeNodeStore.node.breadcrumbs.map((breadcrumb) => breadcrumb.name);
        return justNames.join(" > ");
    }
    return "";
});

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
                <Transition mode="out-in" @enter="slideDownOnEnter" @leave="slideUpOnLeave">
                    <div>
                        <p v-if="breadcrumbs.length > 0" class="meta">{{ breadcrumbs }}</p>
                        <div v-html="activeNodeStore.node.description"></div>
                        <template v-if="hasUsage">
                            <h3>Connected Material</h3>
                            <horizontal-scoller :has-buttons="true" :height="80" :has-content-change="false">
                                <ul class="usage-list">
                                    <mc-node-details-usage
                                        v-for="(usage, index) in activeNodeStore.node.usage"
                                        :key="index"
                                        :usage="usage"
                                    />
                                </ul>
                            </horizontal-scoller>
                        </template>
                    </div>
                </Transition>
            </fieldset>
        </section>
    </div>
</template>

<style lang="scss" scoped>
@import "./style/mcActiveNode.scss";

.usage-list {
    margin: 0;
}
</style>
