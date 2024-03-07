<script setup lang="ts">
import { TextboxContent } from "./unitPlannerTypes";

interface Props {
    textbox: TextboxContent;
}

const props = defineProps<Props>();
</script>

<template>
    <!-- eslint-disable-next-line vue/no-v-html -->
    <div v-html="props.textbox.body"></div>
</template>

<style scoped></style>
