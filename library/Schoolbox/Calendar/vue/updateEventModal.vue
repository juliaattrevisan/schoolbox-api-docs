<script>
    import RevealModal from '@schoolbox/reveal-modal'
    import axios       from 'axios'

    export default {
        name: "update-event-modal",
        props: [
            'event',
            // TODO why do I have to pass this method around?
            'toMoment',
        ],
        components: {
            RevealModal,
        },
        computed: {
            window: () => window,
            isNewEventSubmittable: function () {
                return this.event.title && this.event.title.length !== 0;
            },
            // formDate() {
            //     return this.event.date
            //         ? this.toMoment(this.event.date).format(this.event.allDay ? 'DD-MM-YYYY' : 'DD-MM-YYYY HH:mm')
            //         : '';
            // },
            displayDate() {
                return this.event.date
                    ? this.toMoment(this.event.date).format(this.event.allDay ? 'ddd Do MMM' : 'ddd Do MMM, h:mma')
                    : '';
            },
            eventExtendedProps: function () {
                // This is a subset of the view event's extended properties.
                // Can they be made to extend one another?
                let event      = this.event,
                    data       = event?.extendedProps?.data,
                    metadata   = data?.meta;
                return {
                    eventId:   metadata?.eventId,
                    completed: data?.meta?.completed,
                }
            }
        },
        methods: {
            open() {
                this.$refs.modal.open();
            },
        },
    }
</script>

<template>
    <reveal-modal ref="modal">
        <form ref="updateForm" method="post" action="/calendar/ajax/update">
            <input type="hidden" name="eventId" :value="eventExtendedProps.eventId"/>

            <!-- Currently only tasks have this form -->
            <h3>Task</h3>

            <label>
                Due {{ displayDate }}
            </label>

            <input id="modifyTaskBubbleCompleted" name="completed" type="checkbox" v-model="eventExtendedProps.completed" />
            <label for="modifyTaskBubbleCompleted" class="checkbox-label">
                Complete
            </label>

            <label>
                Task
                <input name="title" type="text" v-model="event.title" />
            </label>

            <label class="detail">
                Note
                <textarea name="detail" v-model="event.detail"></textarea>
            </label>

            <input type="submit" value="Update Task" />
            <span class="deleteLink" data-is-html="1"></span>
        </form>
    </reveal-modal>
</template>

<style scoped>

</style>
