<script setup lang="ts">
interface Props {
    // site title, to show "[title] web app" in the message
    siteTitle: string;
}

const props = defineProps<Props>();
</script>

<template>
    <div class="small-12 columns">
        <div class="row actions-small-1">
            <div class="list-item">
                <div class="small-12 columns">
                    <h2 class="subheader">Setup {{ props.siteTitle }} web app</h2>
                </div>
            </div>
        </div>
    </div>
    <div class="small-12 island">
        <section class="content">
            <slot></slot>
        </section>
    </div>
</template>

<style scoped></style>
