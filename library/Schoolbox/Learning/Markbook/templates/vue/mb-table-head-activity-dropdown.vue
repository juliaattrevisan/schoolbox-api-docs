<script setup lang="ts">
import type { MarkbookActivity } from "./types/payload";

interface Props {
    activity: MarkbookActivity;
}

const props = defineProps<Props>();
</script>

<template>
    <li class="separator">Activity</li>
    <li>
        <a v-if="props.activity._links?.summary" :href="props.activity._links.summary" class="icon-view"> View </a>
    </li>
    <li>
        <a v-if="props.activity._links?.modify" :href="props.activity._links.modify" class="icon-modify"> Modify </a>
    </li>
    <li>
        <a v-if="props.activity._links?.status" :href="props.activity._links.status" class="icon-progress">
            View Status
        </a>
    </li>
    <li>
        <a href="" class="icon-upload"> Import Feedback </a>
    </li>
    <li>
        <a href="" class="icon-approve"> Publish Grades & Feedback </a>
    </li>
</template>

<style scoped></style>
