<script setup lang="ts">
/**
 * The header for the curriculum coverage report
 */

/**
 * When the print button is clicked, print the page
 */
function onPrintClicked(): void {
    window.print();
}
</script>

<template>
    <div class="l-flex-row f-no-margins">
        <h1 class="l-flex__item--1">Curriculum Coverage Report</h1>
        <button class="l-flex l-flex--center f-no-bkg" aria-label="Print" title="Print" @click="onPrintClicked">
            <sbx-icon name="print" />
        </button>
    </div>
</template>

<style scoped></style>
