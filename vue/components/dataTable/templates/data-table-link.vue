<script setup lang="ts">
/**
 * To wrap the text within a link
 */

interface Props {
    rowData: object; // the row of data
    componentData: object; // the component data
}
const props = defineProps<Props>();
</script>

<template>
    <a :href="props.rowData[componentData.href]" :title="componentData.title">
        {{ props.rowData[componentData.text] }}
    </a>
</template>
