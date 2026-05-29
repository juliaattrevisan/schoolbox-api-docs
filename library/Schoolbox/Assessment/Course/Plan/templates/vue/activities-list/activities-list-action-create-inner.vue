<script setup lang="ts">
/**
 * The inner contents for a create activity dropdown
 * Should be wrapped in a ul
 * sbx-pop-out is not created here, as it may need to be customised by the parent component
 */
import { sbProps } from "@js/util/sbx/pageUtils";
import { getReturnUrl } from "@js/util/sbx/urlUtils";

interface Props {
    contentId: string;
    createRoute: string;
}

const props = defineProps<Props>();
</script>

<template>
    <ul :id="props.contentId" class="f-dropdown" data-dropdown-content>
        <li v-for="(activityType, key) in sbProps.assessmentTypes" :key="key">
            <a
                class="sbx-button--has-icon"
                :href="getReturnUrl(`${props.createRoute}?type=${key}`)"
                :data-test="`unit-planner-create-${key}-${$props.contentId}`"
                ><sbx-icon :name="activityType.icon" color="currentColor" size="m"></sbx-icon>{{ activityType.name }}
            </a>
        </li>
    </ul>
</template>

<style scoped></style>
