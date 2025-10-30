<script setup lang="ts">
/**
 * Display a student and access to select linked records and generate with AI
 */

import type { UserCC as User } from "@schoolboxLibrary/types/User";
import UserPhoto from "@schoolboxVue/components/userPhoto/user-photo.vue";

const props = defineProps<{
    user: User;
}>();
</script>

<template>
    <div class="l-flex-row l-flex--no-wrap l-pad-s l-gap-m">
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
</template>

<style scoped></style>
