<script setup lang="ts">
import { useUnitPlannerStore } from "./store/unitPlannerStore";

interface Props {
    unitId: number;
}

const props = defineProps<Props>();
const store = useUnitPlannerStore();

const maxContentHeight = 300;
const maxContentHeightStyle = window.Vue.computed(() => `${maxContentHeight}px`);

const contentEl = window.Vue.ref<HTMLElement | null>(null);
const isDefaultExpanded = window.Vue.ref(false);

const isExpanded = window.Vue.computed(() => {
    if (isDefaultExpanded.value) return true;
    if (store.expandedUnits.includes(props.unitId)) return true;
    return false;
});

window.Vue.onMounted(() => {
    if (contentEl.value) {
        const height = contentEl.value.offsetHeight;
        if (height > 300) {
            isDefaultExpanded.value = false;
        } else {
            isDefaultExpanded.value = true;
        }
    }
});
</script>

<template>
    <div class="content-container" :class="{ expanded: isExpanded }">
        <div ref="contentEl"><slot></slot></div>
    </div>

    <div v-if="!isExpanded" class="show-more-container">
        <div class="component-action">
            <section>
                <a href="#" class="button" @click.prevent="store.toggleExpanded(unitId)"> Show More </a>
            </section>
        </div>
    </div>
</template>

<style scoped>
.content-container:not(.expanded) {
    max-height: v-bind(maxContentHeightStyle);
    overflow: hidden;
}

.show-more-container {
    position: absolute;
    bottom: 0;
    width: 100%;
}
</style>
