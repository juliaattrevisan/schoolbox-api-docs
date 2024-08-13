<script setup lang="ts">
/**
 * To display a single activity linked to a node
 */

import type {
    Usage,
    CourseUsageObject,
    UnitUsageObject,
    LearningActivityUsageObject,
    BadgeUsageObject,
} from "@schoolboxCurriculum/types/Node";
import { ActivityTypes } from "@schoolboxCurriculum/types/Node";
import { getUsageHref, getUsageIcon } from "@schoolboxCurriculum/js/nodeUtils";
import BadgeImage from "@schoolboxBadge/vue/badge-image.vue";

interface Props {
    usage: Usage; // the usage to display
}

const props = defineProps<Props>();
const emit = defineEmits(["select"]);

/**
 * To get the icon for the usage
 */
const usageIcon = window.Vue.computed(() => {
    if (props.usage.discriminator === ActivityTypes.LEARNING_ACTIVITY) {
        // there's various types of learning activity icons
        return getUsageIcon(props.usage.object.type);
    }
    return getUsageIcon(props.usage.discriminator);
});

/**
 * dispatch to parent so can either navigate to another page
 * or open the badge modal
 */
function onSelect() {
    if (props.usage.discriminator === ActivityTypes.BADGE) {
        emit("select", props.usage);
    }
}
</script>

<template>
    <li class="usage">
        <a
            :href="props.usage.discriminator === ActivityTypes.BADGE ? undefined : getUsageHref(props.usage)"
            :title="
                props.usage.discriminator === ActivityTypes.BADGE ? props.usage.object.name : props.usage.object.title
            "
            @click="onSelect"
        >
            <div class="usage__container">
                <div v-if="props.usage.discriminator === ActivityTypes.BADGE" class="usage__icon-container">
                    <badge-image :badge="props.usage.object" css="badge"></badge-image>
                </div>
                <div v-else :class="`usage__icon-container ${usageIcon} size-24`"></div>
                <div class="usage__title-container">
                    <p v-if="props.usage.discriminator === ActivityTypes.BADGE">{{ props.usage.object.name }}</p>
                    <p v-else>{{ props.usage.object.title }}</p>
                    <p v-if="props.usage.discriminator === ActivityTypes.COURSE" class="meta">
                        {{ (props.usage.object as CourseUsageObject).folder?.parent?.title }}
                    </p>
                    <p v-else-if="props.usage.discriminator === ActivityTypes.UNIT" class="meta">
                        {{ (props.usage.object as UnitUsageObject).folder?.parent?.title }}
                    </p>
                    <p v-else-if="props.usage.discriminator === ActivityTypes.LEARNING_ACTIVITY" class="meta">
                        {{ (props.usage.object as LearningActivityUsageObject).unit?.title }}
                    </p>
                    <p v-else-if="props.usage.discriminator === ActivityTypes.BADGE" class="meta">
                        {{ (props.usage.object as BadgeUsageObject).category.name }}
                    </p>
                </div>
            </div>
        </a>
    </li>
</template>

<style lang="scss" scoped>
@import "@css/core/base/icon-colours";
.usage {
    border: 1px solid #ededed;
    padding: 0;
    width: 220px;
}

.usage__container {
    display: flex;
    height: 100%;
}

.usage__icon-container {
    background-color: #f6f6f6;
    flex: 0 0 60px;
    padding: 0.75rem;
    position: relative;
    // move the icon more into the centre
    &:before {
        line-height: 1.25;
        padding: 0 0 0 0.5rem;
    }
}

.usage__title-container {
    flex: 1 1 auto;
    padding: 0.25rem 0.5rem;
    p {
        display: -webkit-box;
        font-size: 0.75rem;
        line-height: 1rem;
        margin-bottom: 0.25rem;
        overflow: hidden;
        white-space: normal;
        -webkit-line-clamp: 3; // clamp the lines to a max of three, cutting off the rest: https://css-tricks.com/almanac/properties/l/line-clamp/
        -webkit-box-orient: vertical;
    }
}
</style>
