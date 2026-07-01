<script setup lang="ts">
/**
 * Just a basic loading animation that is replaced when loading is done
 */

interface Props {
    rows?: number;
    showImage?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    rows: 5,
    showImage: true,
});
</script>

<template>
    <div class="u-flex-col u-gap-m" data-test="loading-table">
        <div v-for="(row, index) in props.rows" :key="index" class="sbx-card sbx-card--loading">
            <div class="u-flex-row u-gap-m u-pad-m">
                <div v-if="props.showImage" class="sbx-img sbx-img--m sbx-img--round">
                    <div class="sbx-img__wrapper">
                        <div class="img-placeholder"></div>
                    </div>
                </div>
                <div class="u-flex-col u-flex--justify-center u-flex__item--1 u-gap-m">
                    <slot>
                        <h3></h3>
                        <span></span>
                    </slot>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped></style>
