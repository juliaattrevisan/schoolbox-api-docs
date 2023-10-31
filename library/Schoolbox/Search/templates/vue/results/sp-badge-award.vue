<script setup lang="ts">
import RelativeTime from "@schoolboxLearningMomentEvidence/relative-time.vue";
import SequentialRevealModal from "@schoolboxVue/components/sequential-reveal-modal.vue";
import BadgeAward from "@schoolboxBadge/Award/vue/badgeAward.vue";
import { useSPBadgeAwardStore } from "./store/spBadgeAwardStore";

const badgeAwardStore = useSPBadgeAwardStore();
</script>

<template>
    <sequential-reveal-modal
        size="medium"
        :is-open="badgeAwardStore.selectedBadge !== null"
        @closed="badgeAwardStore.unsetSelectedBadge()"
    >
        <template #sequence-1>
            <template v-if="badgeAwardStore.selectedBadge !== null">
                <badge-award
                    v-if="badgeAwardStore.selectedBadge.type === 'achievement'"
                    :key="`awardAchievement-${badgeAwardStore.selectedBadge.id}`"
                    type="achievement"
                    :badge="badgeAwardStore.selectedBadge"
                    :expiry="badgeAwardStore.selectedBadge.expiry"
                    :endpoint="`/learning/badge/award/${badgeAwardStore.selectedBadge.id}/achievement`"
                    :is-modal-open="true"
                    placeholder="Search for users to award achievement to"
                    toast-activate-message="Awarding achievement..."
                    toast-success-message="Awarded achievement"
                    toast-failure-message="Awarding achievement failed"
                    @cancel="badgeAwardStore.unsetSelectedBadge()"
                    @done="badgeAwardStore.unsetSelectedBadge()"
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
                    v-else-if="badgeAwardStore.selectedBadge.type === 'goal'"
                    :key="`setGoal-${badgeAwardStore.selectedBadge.id}`"
                    type="goal"
                    :badge="badgeAwardStore.selectedBadge"
                    :expiry="badgeAwardStore.selectedBadge.expiry"
                    :endpoint="`/learning/badge/award/${badgeAwardStore.selectedBadge.id}/goal`"
                    :is-modal-open="true"
                    placeholder="Search for users to award achievement to"
                    toast-activate-message="Setting goal..."
                    toast-success-message="Goal set"
                    toast-failure-message="Setting goal failed"
                    @cancel="badgeAwardStore.unsetSelectedBadge()"
                    @done="badgeAwardStore.unsetSelectedBadge()"
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
