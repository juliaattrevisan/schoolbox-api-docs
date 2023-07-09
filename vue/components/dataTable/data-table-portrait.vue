<script setup lang="ts">
/**
 *
 */

interface Props {
    rowData: object; // the row of data
    componentData: object; // the component data
}
const props = defineProps<Props>();
</script>

<template>
    <template v-if="props.rowData[componentData.src] && props.rowData[componentData.href]">
        <a :href="props.rowData[componentData.src]" title="View profile">
            <img :src="props.rowData[componentData.src]" alt="Portrait photo of user" />
        </a>
    </template>
    <template v-else-if="props.rowData[componentData.src]">
        <img :src="props.rowData[componentData.src]" alt="Portrait photo of user" />
    </template>
</template>
