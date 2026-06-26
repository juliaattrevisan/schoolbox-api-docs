<script setup lang="ts">
import { getDayjsDatetimeLong } from "@js/util/dateUtils";
import { AiModerationStatus, STATUS_LABELS } from "./moderationEvent";
import type { AiModerationEvent } from "./moderationEvent";
import { RISK_LEVEL_LABELS, CATEGORY_LABELS, RiskLevel } from "./moderationResult";
import AulSessionInfoModerationEventActions from "./aul-session-info-moderation-event-actions.vue";

interface Props {
    event: AiModerationEvent;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: "updateEvent", event: AiModerationEvent): void;
}>();

const componentId = window.Vue.useId();

const isExpanded = window.Vue.ref<boolean>(false);
const canExpand = window.Vue.computed(() => props.event.result.categories.length > 0);

/**
 * Don't show the "No Behaviour Flagged" label if the above risk level will already say that.
 * (you can't just check for NONE risk level, since it's possible to change the status of non-flagged events)
 */
const hideStatusLabel = window.Vue.computed(() => {
    return (
        props.event.status === AiModerationStatus.NO_BEHAVIOUR_FLAGGED &&
        props.event.result.overall_risk_level !== RiskLevel.NONE
    );
});

const timestamp = window.Vue.computed(() => {
    const date = window.dayjs(props.event.createdAt);
    const time = date.format(window.dateDisplayFormats.dayjsTime);
    return `${date.format(window.dateDisplayFormats.dayjsDateShort)} ${time}`;
});

const STATUS_LABEL_CLASSES: Record<AiModerationStatus, string> = {
    [AiModerationStatus.NO_BEHAVIOUR_FLAGGED]: "neutral",
    [AiModerationStatus.REQUIRES_ATTENTION]: "warning",
    [AiModerationStatus.NO_ACTION_REQUIRED]: "neutral",
    [AiModerationStatus.FURTHER_ACTION_REQUIRED]: "warning",
    [AiModerationStatus.FURTHER_ACTION_TAKEN]: "highlight",
};
</script>

<template>
    <li :class="`actions-small-${canExpand ? 2 : 1}`">
        <div class="list-item">
            <div class="card small-12">
                <h3>
                    {{ RISK_LEVEL_LABELS[props.event.result.overall_risk_level] }}
                    <span
                        v-if="!hideStatusLabel"
                        class="label right l-mar--0"
                        :class="STATUS_LABEL_CLASSES[props.event.status]"
                    >
                        {{ STATUS_LABELS[props.event.status] }}
                    </span>
                </h3>
                <p class="meta">
                    <time :datetime="props.event.createdAt" :title="getDayjsDatetimeLong(props.event.createdAt)">
                        {{ timestamp }}
                    </time>
                </p>
            </div>
        </div>

        <nav>
            <button
                v-if="canExpand"
                type="button"
                class="sbx-icon-only-button"
                :aria-expanded="isExpanded"
                :aria-label="`${isExpanded ? 'Collapse' : 'Expand'} event details`"
                :title="`${isExpanded ? 'Collapse' : 'Expand'} event details`"
                @click="isExpanded = !isExpanded"
            >
                <sbx-icon :name="isExpanded ? 'up-arrow' : 'down-arrow'"></sbx-icon>
            </button>
            <sbx-pop-out
                :content-id="`aul-session-info-moderation-event-${componentId}`"
                is-close-on-content-click
            ></sbx-pop-out>
        </nav>
        <ul :id="`aul-session-info-moderation-event-${componentId}`" class="f-dropdown" data-dropdown-content>
            <aul-session-info-moderation-event-actions
                :event="props.event"
                @update-event="(event) => emit('updateEvent', event)"
            />
        </ul>

        <article>{{ props.event.result.summary }}</article>
    </li>

    <li v-for="category in props.event.result.categories" v-show="isExpanded" :key="category.category" class="child">
        <div class="content">
            <h3>{{ CATEGORY_LABELS[category.category] }}</h3>
            <p v-for="(evidence, evidenceIdx) in category.evidence" :key="evidenceIdx" class="meta">
                "{{ evidence.quote }}"
            </p>
        </div>
    </li>
</template>
