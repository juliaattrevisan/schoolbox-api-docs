<script setup lang="ts">
import type { DateRFC3339 } from "@js/util/dateUtils";
import type { UserCC } from "@schoolboxLibrary/types/User";
import UserPhoto from "@schoolboxVue/components/userPhoto/user-photo.vue";
import { STATUS_TICKS } from "@schoolboxAssessment/Types/dialogAgent";
import type { DialogAgentResponseStatus } from "@schoolboxAssessment/Types/dialogAgent";
import DasFilters from "./das-filters.vue";

interface Props {
    criteria: {
        id: string;
        title: string;
    }[];
    participants: {
        id: number;
        user: UserCC;
        status: DialogAgentResponseStatus;
        assistanceRequestedAt: DateRFC3339 | null;
        submittedAt: DateRFC3339 | null;
        _routes: {
            summary?: string | null;
            view?: string | null;
            sobo?: string | null;
            reset?: string | null;
            reopen?: string | null;
            resolved?: string | null;
        };
    }[];
}

const props = defineProps<Props>();
</script>

<template>
    <div class="row">
        <!-- Metadata about the activity -->
        <div class="small-12 large-4 columns">
            <slot name="meta"></slot>
        </div>

        <!-- Status filters -->
        <div class="small-12 large-8 columns text-right">
            <das-filters />
        </div>
    </div>

    <div class="row">
        <div class="small-12 island">
            <div class="dialog-agent-grid scrollable">
                <table>
                    <thead>
                        <tr>
                            <th class="u-sticky-left u-sticky-left--first"></th>
                            <th class="u-sticky-left u-sticky-left--offset-1">Status</th>
                            <th v-for="field in props.criteria" :key="field.id" class="field-col">{{ field.title }}</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="participant in props.participants" :key="participant.id">
                            <td class="profile-cell u-sticky-left u-sticky-left--first">
                                <div class="small-12 card compact l-mar-s no-pad">
                                    <a :href="`/search/user/${participant.id}`" title="View Profile">
                                        <user-photo :user="participant.user" render="small" />
                                        <p>{{ participant.user.fullName }}</p>
                                    </a>
                                </div>
                            </td>
                            <td class="u-sticky-left u-sticky-left--offset-1">
                                <component
                                    :is="participant._routes.view ? 'a' : 'span'"
                                    :href="participant._routes.view || undefined"
                                    class="progress-tick readonly"
                                    :class="STATUS_TICKS[participant.status]"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<style scoped lang="scss">
.dialog-agent-grid {
    --sbx-table-u-sticky-width-first: 12.5rem;
    max-height: 90vh;

    .field-col {
        min-width: 18rem;
    }
}
</style>
