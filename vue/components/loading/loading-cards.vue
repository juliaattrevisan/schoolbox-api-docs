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
    <div class="l-flex-col l-gap-m" data-test="loading-table">
        <div v-for="(row, index) in props.rows" :key="index" class="c-card c-card--loading">
            <div class="l-flex-row l-gap-m l-pad-m">
                <div class="c-img c-img--m c-img--round">
                    <div class="c-img__wrapper">
                        <div class="img-placeholder"></div>
                    </div>
                </div>
                <div class="l-flex-col l-flex--justify-center l-flex__item--1 l-gap-m">
                    <h3></h3>
                    <span></span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
</style>
