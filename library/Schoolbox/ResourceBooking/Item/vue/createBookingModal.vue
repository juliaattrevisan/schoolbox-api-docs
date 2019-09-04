<template>
    <reveal-modal ref="modal">
        <form id="createEvent" :action="bookingPath" v-on:submit.prevent>
            <p>Create booking for: <span>{{ this.displayDate }}</span></p>
            <input type="submit" class='expand' value="Create Booking" v-on:click="submitForm" />
        </form>
    </reveal-modal>
</template>

<script>
    import RevealModal from "@schoolbox/reveal-modal";

    export default {
        name: "create-booking-modal",
        props: [
            'event',
            'toMoment',
            'bookingPath',
        ],
        data() {
            return {
                startDate: this.getStartDate(),
                startTime: '',
            }
        },
        components: {RevealModal},
        methods: {
            /**
             * Open the modal.
             */
            open() {
                this.$refs.modal.open();
            },

            submitForm() {

                var createBookingParamsRoute = [
                    'startDate=' + this.toMoment(this.event.date).format('YYYY-MM-DD')
                ];

                if (!this.event.allDay) {
                    createBookingParamsRoute.push('startTime=' + this.toMoment(this.event.date).format('HH:mm'));
                }

                window.location = this.bookingPath + '?' + createBookingParamsRoute.join('&');
            },

            getStartDate() {
                return this.event.date ? this.toMoment(this.event.date).format('YYYY-MM-DD') : '';
            }
        },
        computed: {
            window: () => window,

            /**
             * The booking's date, as displayed on the modal.
             *
             * @return {string}
             */
            displayDate() {
                return this.event.date
                    ? this.toMoment(this.event.date).format(
                        this.event.allDay
                            ? 'LL'
                            : 'LLLL'
                    )
                    : '';
            }
        }
    }
</script>
