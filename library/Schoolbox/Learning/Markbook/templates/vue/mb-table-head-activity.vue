<script setup lang="ts">
import type { Ref } from "vue";
import { usePopOut } from "@schoolboxVue/composables/popOut/usePopOut";
import type { MarkbookActivity } from "./types/payload";

interface Props {
    activity: MarkbookActivity;
}

const props = defineProps<Props>();
</script>

<template>
    <th :data-test="`markbook-assessment-${props.activity.id}`">
        <div ref="referenceEl">
            <!-- A teacher clicking an assessment from the markbook is most likely interested in seeing more granular results -->
            <span>
                <a
                    v-if="props.activity._links?.summary !== null"
                    :href="props.activity._links?.summary"
                    :title="props.activity.title"
                >
                    {{ props.activity.title }}
                </a>
                <span v-else :title="props.activity.title">{{ props.activity.title }}</span>
                <br />
                <span class="meta">{{ props.activity.typeName }}</span>
            </span>
        </div>
    </th>
</template>

<style scoped></style>
