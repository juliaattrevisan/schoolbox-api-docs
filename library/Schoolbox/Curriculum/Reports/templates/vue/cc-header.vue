<script setup lang="ts">
/**
 * The header for the curriculum coverage report
 */

/**
 * When the export button is clicked, export the report to CSV
 */
function onExportClicked(): void {
    // on export clicked
}
</script>

<template>
    <div class="l-flex-row f-no-margins">
        <h1 class="l-flex__item--1">Curriculum Coverage Report</h1>
        <button
            class="l-flex l-flex--center f-no-bkg"
            aria-label="Export to CSV"
            title="Export to CSV"
            @click="onExportClicked"
        >
            <sbx-icon name="export" />
        </button>
    </div>
</template>

<style scoped></style>
