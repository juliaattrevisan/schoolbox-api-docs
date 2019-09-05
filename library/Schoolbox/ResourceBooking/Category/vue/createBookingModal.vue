<template>
    <reveal-modal ref="modal">
        <form id="createEvent" :action="bookingPath" v-on:submit.prevent>
            <h3>{{ this.resource.name }}</h3>
            <p>Create booking for: <span>{{ displayDate }}</span></p>
            <input type="submit" class='expand' value="Create Booking" v-on:click="submitForm" />
        </form>
    </reveal-modal>
</template>

<script>
    import RevealModal from "@schoolbox/reveal-modal";

    export default {
        name: "create-booking-modal",
        props: [
            'resource',
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
                    'startDate=' + this.toMoment(this.resource.date).format('YYYY-MM-DD')
                ];

                if (!this.resource.allDay) {
                    createBookingParamsRoute.push('startTime=' + this.toMoment(this.resource.date).format('HH:mm'));
                }

                window.location = this.bookingPath + this.resource.id + '?' + createBookingParamsRoute.join('&');
            },

            getStartDate() {
                return this.resource.date ? this.toMoment(this.resource.date).format('YYYY-MM-DD') : '';
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
                return this.resource.date
                    ? this.toMoment(this.resource.date).format(
                        this.resource.allDay
                            ? 'LL'
                            : 'LLLL'
                    )
                    : '';
            }
        }
    }
</script>
