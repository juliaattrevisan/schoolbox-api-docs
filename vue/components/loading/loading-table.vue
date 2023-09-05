<script setup lang="ts">
/**
 * Just a basic loading animation that is replaced when loading is done
 */

interface Props {
    rows?: number;
}

const props = withDefaults(defineProps<Props>(), {
    rows: 5,
});
</script>

<template>
    <table class="skeleton" data-test="loading-table">
        <tbody>
            <tr v-for="(row, index) in props.rows" :key="index">
                <td></td>
            </tr>
        </tbody>
    </table>
</template>

<style scoped>
.skeleton {
    margin-bottom: 0;
}
</style>
