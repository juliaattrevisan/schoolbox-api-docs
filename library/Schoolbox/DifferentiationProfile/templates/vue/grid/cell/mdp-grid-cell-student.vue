<script setup lang="ts">
/**
 * Display a student and access to select linked records and extract with AI
 */

import { sbProps } from "@js/util/sbx/pageUtils";
import locale from "@js/terminology/rosettaStone";
import type { UserCC as User } from "@schoolboxLibrary/types/User";
import UserPhoto from "@schoolboxVue/components/user/user-photo.vue";
import { ProfileStatus, type Profile } from "../../../js/types/manageDifferentiationProfilesTypes";
import { useHeaderStore } from "../../store/mdpHeaderStore";
import { useLinkedRecordsStore } from "../../store/mdpLinkedRecordsStore";
import { useGenAiStore } from "../../store/mdpGenAiStore";

const props = defineProps<{
    user: User;
    profile: Profile;
}>();

const headerStore = useHeaderStore();
const linkedRecordsStore = useLinkedRecordsStore();
const genAiStore = useGenAiStore();

/**
 * When the linked records button is clicked
 */
function onLinkedRecordsSelected(): void {
    linkedRecordsStore.select(props.profile);
}

/**
 * When the extract with AI button is clicked
 */
function onGenerateSelected(): void {
    void genAiStore.prompt(props.profile);
}
</script>

<template>
    <div class="l-flex-col l-pad-s l-gap-m">
        <div class="l-flex-row l-flex--no-wrap l-flex--align-start l-gap-m">
            <user-photo :user="props.user" render="small" />
            <div class="l-flex-col l-flex--justify-center l-gap-s f-no-margins">
                <a v-if="props.user._links?.profile" :href="props.user._links!.profile" title="View Profile">
                    {{ props.user.lastName }},
                    {{ props.user.givenName }}
                </a>
                <template v-else>
                    {{ props.user.lastName }},
                    {{ props.user.givenName }}
                </template>
                <p v-if="props.user.yearLevel || (props.user.campus && props.user.campus.length)" class="meta">
                    <template v-if="props.user.yearLevel">
                        {{ locale.year.translate(false, true) }} {{ props.user.yearLevel.name }}</template
                    ><template v-if="props.user.campus && props.user.campus.length"
                        ><template v-if="props.user.yearLevel">, </template
                        ><span v-for="(campus, index) in props.user.campus" :key="campus.id">
                            {{ campus.name }} Student<span v-if="index < props.user.campus.length - 1">, </span>
                        </span>
                    </template>
                </p>
            </div>
        </div>
        <!-- if the profile is expired we don't want to provide access to linked records or AI extraction -->
        <template v-if="!props.profile.status?.includes(ProfileStatus.EXPIRED)">
            <button
                v-if="props.profile._routes?.linkedRecords"
                class="button f-no-margins"
                @click="onLinkedRecordsSelected"
            >
                View Linked Records
            </button>
            <a
                v-if="props.profile._routes?.customisations"
                :href="props.profile._routes.customisations"
                class="button icon-group f-no-margins"
            >
                View Customisations
            </a>
            <button
                v-if="sbProps.aiFeatures.differentiation?.enabled && headerStore.hasEnabledTextFields"
                class="button icon-ai f-no-margins"
                @click="onGenerateSelected"
            >
                Extract with AI
            </button>
        </template>
    </div>
</template>

<style scoped></style>
