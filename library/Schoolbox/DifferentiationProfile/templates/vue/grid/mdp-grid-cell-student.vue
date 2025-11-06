<script setup lang="ts">
/**
 * Display a student and access to select linked records and generate with AI
 */

import type { UserCC as User } from "@schoolboxLibrary/types/User";
import UserPhoto from "@schoolboxVue/components/userPhoto/user-photo.vue";
import type { Profile } from "../../js/types/manageDifferentiationProfilesTypes";

const props = defineProps<{
    user: User;
    profile: Profile;
}>();

const emit = defineEmits<{
    linkedRecordsSelect: [profile: Profile];
}>();

/**
 * When the linked records button is clicked
 */
function onLinkedRecordsSelected(): void {
    emit("linkedRecordsSelect", props.profile);
}
</script>

<template>
    <div class="l-flex-col l-pad-s l-gap-m">
        <div class="l-flex-row l-flex--no-wrap l-gap-m">
            <user-photo :user="props.user" render="small" />
            <div class="l-flex-col l-flex--justify-center f-no-margins">
                <!-- <a v-if="props.user._links?.profile" :href="props.user._links!.profile" title="View Profile">
                {{ props.user.lastName }},
                {{ props.user.preferredName || props.user.firstName }}
            </a>
            <template v-else>
                {{ props.user.lastName }},
                {{ props.user.preferredName || props.user.firstName }}
            </template> -->
                {{ props.user.fullName }}
            </div>
        </div>
        <button v-if="props.profile._routes?.linkedRecords" class="button" @click="onLinkedRecordsSelected">
            View Linked Records
        </button>
    </div>
</template>

<style scoped></style>
