<script setup lang="ts">
/**
 * Just a basic loading animation that is replaced when loading is done
 * This should replace all the instances of the skeleton loading state in other Vue files
 */

const props = withDefaults(
    defineProps<{
        rows?: number; // number of table rows to display
        onlyBody?: boolean; // whether to only display the table body, so the table header is maintained, for when sorting/paginating a table
        rowHeight?: string; // height of each row
    }>(),
    {
        rows: 5,
        onlyBody: false,
        rowHeight: "2rem",
    },
);
</script>

<template>
    <tbody v-if="props.onlyBody" class="skeleton" data-test="loading-table">
        <tr v-for="(row, index) in props.rows" :key="index" :style="{ height: props.rowHeight }">
            <td colspan="100%"></td>
        </tr>
    </tbody>
    <table v-else class="skeleton l-mar--0-bottom" data-test="loading-table">
        <tbody>
            <tr v-for="(row, index) in props.rows" :key="index" :style="{ height: props.rowHeight }">
                <td colspan="100%"></td>
            </tr>
        </tbody>
    </table>
</template>

<style></style>
