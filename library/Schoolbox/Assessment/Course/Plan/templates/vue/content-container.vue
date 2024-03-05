<script setup lang="ts">
import { useUnitPlannerStore } from "./store/unitPlannerStore";

interface Props {
    unitId: number;
}

const props = defineProps<Props>();
const store = useUnitPlannerStore();

const contentEl = window.Vue.ref<HTMLElement | null>(null);
const contentHeight = window.Vue.ref(0);

const showViewMore = window.Vue.computed(() => {
    if (contentHeight.value <= store.unexpandedUnitHeight) return true;
    if (store.expandedUnits.includes(props.unitId)) return true;
    return false;
});

window.Vue.onMounted(() => {
    contentHeight.value = contentEl.value?.offsetHeight || 0;
});
</script>

<template>
    <div>
        <div
            class="content-container"
            :style="{
                height: store.expandedUnits.includes(props.unitId) ? 'auto' : store.unexpandedUnitHeight + 'px',
            }"
        >
            <div ref="contentEl"><slot></slot></div>
        </div>

        <div v-if="!showViewMore" class="show-more-container">
            <button @click.prevent="store.toggleExpanded(unitId)">Show More</button>
        </div>
    </div>
</template>

<style scoped>
.content-container {
    overflow: hidden;
    transition: height 0.5s ease;
}
.show-more-container {
    bottom: 0;
    position: absolute;
    width: 100%;
    background: linear-gradient(0deg, #fff 0%, #fff0 100%);
    pointer-events: none;
}
.show-more-container button {
    margin: 5px;
    float: right;
    pointer-events: all;
}
</style>
