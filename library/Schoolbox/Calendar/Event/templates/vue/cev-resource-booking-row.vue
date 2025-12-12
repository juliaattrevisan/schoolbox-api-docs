<script setup lang="ts">
import type { Booking } from "./resourceBookingTypes";

interface Props {
    booking: Booking;
    canModify: boolean;
}
const props = defineProps<Props>();
</script>

<template>
    <div class="l-grid l-gap-l l-grid--2-col l-mar-m--bottom">
        <div>
            <!-- Intentionally display the resource name here, not the booking name -->
            {{ props.booking.resource.name }}
        </div>
        <div class="text-right">
            <span
                class="label"
                :class="{
                    pending: props.booking.status.isPending,
                    approved: props.booking.status.isApproved,
                    rejected: props.booking.status.isRejected,
                }"
                >{{ booking.status.name }}</span
            >
            <a v-if="canModify" href="#" class="icon-modify" title="Modify Booking" aria-label="Modify Booking"></a>
        </div>
    </div>
</template>

<style scoped></style>
