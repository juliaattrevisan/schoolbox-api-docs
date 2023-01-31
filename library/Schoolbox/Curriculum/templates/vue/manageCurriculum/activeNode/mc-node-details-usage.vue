<script setup lang="ts">
/**
 * To display a single activity linked to a node
 */

import type { Usage } from "@schoolboxCurriculum/types/Node";
import { getUsageHref, getUsageIcon } from "@schoolboxCurriculum/js/nodeUtils";

interface Props {
    usage: Usage; // the usage to display
}

const props = defineProps<Props>();
</script>

<template>
    <li class="usage">
        <a :href="`${getUsageHref(props.usage)}`" :title="props.usage.object.title">
            <div class="usage__container">
                <div :class="`usage__icon-container ${getUsageIcon(props.usage)} size-24`"></div>
                <div class="usage__title-container">
                    <p>{{ props.usage.object.title }}</p>
                </div>
            </div>
        </a>
    </li>
</template>

<style lang="scss" scoped>
.usage {
    border: 1px solid #ededed;
    display: inline-block;
    height: 64px;
    margin: 0px 0.25rem;
    padding: 0;
    width: 180px;
}

.usage__container {
    // flex needs to occur in a container within .usage as the transition elements require display: inline-block
    display: flex;
    height: 100%;
}

.usage__icon-container {
    // v-deep is used so can target the img succesfully
    background-color: #f6f6f6;
    flex: 0 0 50px;
    padding: 1rem;
    position: relative;
    &:before {
        padding: 0;
    }
}

.usage__title-container {
    flex: 1 1 auto;
    padding: 0.25rem 0.5rem;
    p {
        display: -webkit-box;
        font-size: 0.75rem;
        line-height: 1rem;
        overflow: hidden;
        white-space: normal;
        -webkit-line-clamp: 3; // clamp the lines to a max of three, cutting off the rest: https://css-tricks.com/almanac/properties/l/line-clamp/
        -webkit-box-orient: vertical;
    }
}
</style>
