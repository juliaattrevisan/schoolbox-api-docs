<script setup lang="ts">
/**
 * A list of activities or lesson plans that are part of a particular node
 */

import type { Item } from "../js/types/curriculumCoverageTypes";

interface Props {
    items: Item[];
}
const props = defineProps<Props>();
</script>

<template>
    <template v-for="item in props.items" :key="item._links.view">
        <h6 class="f-no-margins">
            <a :href="item._links.view" :title="`View ${item.name}`" class="l-flex l-gap-s">
                <sbx-icon :name="item.icon" />
                {{ item.name }}
            </a>
        </h6>
    </template>
</template>

<style scoped></style>
