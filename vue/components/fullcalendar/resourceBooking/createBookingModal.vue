<template>
    <reveal-modal ref="modal">
        <form id="createEvent" :action="bookingPath" v-on:submit.prevent>
            <template v-if="this.event.name">
                <h3>{{ this.event.name }}</h3>
            </template>
            <template v-else>
                <!--
                    TODO: To remove this when we update jQuery in the future
                    Temporary solution to prevent a mysterious error:
                    schoolbox.min.js?v=19.1.alpha1:3710 Uncaught TypeError: elem.getAttribute is not a function
                    when there's a related booking. According to my research, it's not a sizzle error and  we'll
                    need to update jQuery to 3.x. However, doing so may potentially open un new issues.
                 -->
            </template>
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
            'toMoment',
            'bookingPath',
            'dateFormats',
            'bookingType',
        ],
        data() {
            return {
                startDate: this.getStartDate(),
                startTime: '',
                event: {},
            }
        },
        components: {RevealModal},
        methods: {
            /**
             * Open the modal.
             */
            open(event) {
                this.event = event;
                this.$refs.modal.open();
            },

            submitForm() {
                var createBookingParamsRoute = [
                    'startDate=' + this.toMoment(this.event.date).format('YYYY-MM-DD')
                ];

                if (!this.event.allDay) {
                    createBookingParamsRoute.push('startTime=' + this.toMoment(this.event.date).format('HH:mm'));
                }

                var createBookingPath = this.bookingPath + '?' + createBookingParamsRoute.join('&');

                if (this.bookingType === 'category') {
                    var currentLocation = window.location;
                    var callBackUrl = currentLocation.pathname + currentLocation.search;
                    createBookingParamsRoute.push('callbackUrl=' + encodeURIComponent(callBackUrl));

                    createBookingPath = this.bookingPath + this.event.id + '?' + createBookingParamsRoute.join('&') + '#resourceBookingByCategory';
                }

                window.location = createBookingPath;
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
                            ? this.dateFormats.displayDate
                            : this.dateFormats.displayDatetime
                    )
                    : '';
            }
        }
    }
</script>
