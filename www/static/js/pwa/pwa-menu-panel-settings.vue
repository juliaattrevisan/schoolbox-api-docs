<script setup lang="ts">
interface Props {
    // whether the panel has any visible content to show in it
    hasVisibleContent: boolean;
    // site title, to show "[title] web app" in the message
    siteTitle: string;
}

const props = defineProps<Props>();

/**
 * Show the panel if there is any content to show in it
 */
const showPanel = window.Vue.computed(() => {
    return props.hasVisibleContent;
});
</script>

<template>
    <div v-show="showPanel" class="small-12 columns">
        <div class="row actions-small-1">
            <div class="list-item">
                <div class="small-12 columns">
                    <h2 class="subheader">Setup {{ props.siteTitle }} web app</h2>
                </div>
            </div>
        </div>
    </div>
    <div v-show="showPanel" class="small-12 island">
        <section class="content">
            <slot></slot>
        </section>
    </div>
</template>

<style scoped></style>
