<script setup lang="ts">
import RelativeTime from "@schoolboxLearningMomentEvidence/relative-time.vue";
import SequentialRevealModal from "@schoolboxVue/components/sequential-reveal-modal.vue";
import BadgeAward from "@schoolboxBadge/Award/vue/badgeAward.vue";
import { useBadgeStore } from "./store/spBadgeStore";

const badgeStore = useBadgeStore();
</script>

<template>
    <sequential-reveal-modal
        size="medium"
        :is-open="badgeStore.awardBadge !== null"
        @closed="badgeStore.unsetAwardBadge()"
    >
        <template #sequence-1>
            <template v-if="badgeStore.awardBadge !== null">
                <badge-award
                    v-if="badgeStore.awardBadge.type === 'achievement'"
                    :key="`awardAchievement-${badgeStore.awardBadge.id}`"
                    type="achievement"
                    :badge="badgeStore.awardBadge"
                    :expiry="badgeStore.awardBadge.expiry ?? undefined"
                    :endpoint="`/learning/badge/award/${badgeStore.awardBadge.id}/achievement`"
                    :is-modal-open="true"
                    placeholder="Search for users to award achievement to"
                    toast-activate-message="Awarding achievement..."
                    toast-success-message="Awarded achievement"
                    toast-failure-message="Awarding achievement failed"
                    @cancel="badgeStore.unsetAwardBadge()"
                    @done="badgeStore.unsetAwardBadge()"
                >
                    <template #header="slotProps">
                        <h2>
                            Award &lsquo;<b>{{ slotProps.badge.name }}</b
                            >&rsquo; as an Achievement
                        </h2>
                    </template>
                    <template #confirmButton> Award </template>
                    <template #expiryMessage="slotProps">
                        <p v-if="slotProps.expiryDate" class="meta">
                            This achievement will expire
                            <relative-time :datetime="slotProps.expiryDate" date-only></relative-time>.
                        </p>
                    </template>
                </badge-award>

                <badge-award
                    v-else-if="badgeStore.awardBadge.type === 'goal'"
                    :key="`setGoal-${badgeStore.awardBadge.id}`"
                    type="goal"
                    :badge="badgeStore.awardBadge"
                    :expiry="badgeStore.awardBadge.expiry ?? undefined"
                    :endpoint="`/learning/badge/award/${badgeStore.awardBadge.id}/goal`"
                    :is-modal-open="true"
                    placeholder="Search for users to award achievement to"
                    toast-activate-message="Setting goal..."
                    toast-success-message="Goal set"
                    toast-failure-message="Setting goal failed"
                    @cancel="badgeStore.unsetAwardBadge()"
                    @done="badgeStore.unsetAwardBadge()"
                >
                    <template #header="slotProps">
                        <h2>
                            Set &lsquo;<b>{{ slotProps.badge.name }}</b
                            >&rsquo; as a goal
                        </h2>
                    </template>
                    <template #confirmButton> Set goal </template>
                    <template #expiryMessage="slotProps">
                        <p v-if="slotProps.expiryDate" class="meta">
                            This goal will expire
                            <relative-time :datetime="slotProps.expiryDate" date-only></relative-time>.
                        </p>
                    </template>
                </badge-award>
            </template>
        </template>
    </sequential-reveal-modal>
</template>

<style scoped></style>
