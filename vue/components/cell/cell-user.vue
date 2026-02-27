<script setup lang="ts">
/**
 * Display a student
 */

import locale from "@js/terminology/rosettaStone";
import type { UserCC as User } from "@schoolboxLibrary/types/User";
import UserPhoto from "@schoolboxVue/components/user/user-photo.vue";

const props = defineProps<{
    user: User;
}>();
</script>

<template>
    <div class="l-flex-col l-pad-s l-gap-m">
        <div class="l-flex-row l-flex--no-wrap l-gap-m">
            <user-photo :user="props.user" render="small" />
            <div class="l-flex-col l-flex--justify-center l-gap-sf-no-margins">
                <a v-if="props.user._links?.profile" :href="props.user._links!.profile" title="View Profile">
                    {{ props.user.lastName }},
                    {{ props.user.preferredName !== "" ? props.user.preferredName : props.user.firstName }}
                </a>
                <template v-else>
                    {{ props.user.lastName }},
                    {{ props.user.preferredName !== "" ? props.user.preferredName : props.user.firstName }}
                </template>
                <p v-if="props.user.yearLevel || (props.user.campus && props.user.campus.length)" class="meta">
                    <template v-if="props.user.yearLevel">
                        {{ locale.year.translate(false, true) }} {{ props.user.yearLevel.name }}</template
                    >
                    <template v-if="props.user.campus && props.user.campus.length">
                        <span v-for="(campus, index) in props.user.campus" :key="campus.id">
                            {{ campus.name }} Student<span v-if="index < props.user.campus.length - 1">, </span>
                        </span>
                    </template>
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped></style>
