<script>
    import RevealModal from '@schoolbox/reveal-modal'
    import axios       from 'axios'

    const EVENT_CREATED = 'eventCreated';

    export default {
        name: "create-event-modal",
        props: [
            'userId',
            'eventTypes',
            'taskTypeId',
            // TODO why do I have to pass this method around?
            'toMoment',
            'eventCreateUrl',
            'eventModifyFormUrl',
            'formatFormDate',
            'formatFormDatetime',
            'formatDisplayDate',
            'formatDisplayDatetime',
        ],
        components: {
            RevealModal,
        },
        data() {
            return {
                event: {},
            }
        },
        computed: {
            window: () => window,

            /**
             * Can the event form be submitted?
             *
             * @return {boolean}
             */
            isFormSubmittable() {
                return this.event.title && this.event.title.length !== 0;
            },

            /**
             * The event's date, as submitted via the form.
             *
             * @return {string}
             */
            formDate() {
                return this.event.date
                    ? this.toMoment(this.event.date).format(
                        this.event.allDay
                            ? this.formatFormDate
                            : this.formatFormDatetime
                    )
                    : '';
            },

            /**
             * The event's date, as displayed on the modal.
             *
             * @return {string}
             */
            displayDate() {
                return this.event.date
                    ? this.toMoment(this.event.date).format(
                        this.event.allDay
                            ? this.formatDisplayDate
                            : this.formatDisplayDatetime
                    )
                    : '';
            },

            /**
             * All event types that are not tasks.
             *
             * @return {Array}
             */
            nonTaskEventTypes() {
                return this.eventTypes.filter(v => !v.isTaskType);
            },
        },
        methods: {
            /**
             * Open this modal.
             */
            open(event) {
                this.event = event;

                this.$refs.modal.open();
            },

            /**
             * Post the event creation form.
             *
             * TODO consider whether this should be in the base FullCalendar component
             *
             * @param form The form that's being submitted
             */
            createNewEvent(form) {
                let self = this;
                self.$refs.modal.close();
                axios.request({
                    url:          form.action,
                    data:         this.window.jQuery(form).serialize(),
                    method:       form.method,
                    responseType: 'json',
                }).then(function (response) {
                    // Add this event to the calendar
                    self.$emit(EVENT_CREATED, response.data);
                });
            },

            /**
             * Redirect the browser to the full event modify form.
             */
            redirectToEventForm() {
                // TODO don't use jQuery form serialize
                this.window.location.href =
                    this.eventModifyFormUrl
                    + '?from=inline&returnTo=calendar&'
                    + this.window.jQuery(this.$refs.createEventForm).serialize();
            },
        },
    }
</script>

<template>
    <reveal-modal ref="modal">
        <!-- create a tab Vue component? Currently they just work magically -->
        <dl class="tabs" data-tab>
            <dd class="active"><a href="#createEventPanel">Create Event</a></dd>
            <dd><a href="#createTaskPanel">Create Task</a></dd>
        </dl>
        <div class="tabs-content">
            <div class="content active" id="createEventPanel" aria-hidden="false">
                <form ref="createEventForm"
                      method="post"
                      :action="eventCreateUrl"
                      @submit.prevent="createNewEvent($refs.createEventForm)"
                      class="top"
                >
                    <input name="userId" type="hidden" :value="userId" />
                    <input name="allDay" type="hidden" :value="Number(event.allDay)" />
                    <input name="date"   type="hidden" :value="formDate" />

                    <section>
                        <h3>On <span>{{ displayDate }}</span></h3>

                        <label class="required" for="createEventBubbleTitle">
                            Event Title
                        </label>
                        <input id="createEventBubbleTitle" name="title" type="text" v-model.trim="event.title" />

                        <label>
                            Event Type
                            <select name="type" v-model="event.type">
                                <option v-for="eventType in nonTaskEventTypes" :value="eventType.id">
                                    {{ eventType.name }}
                                </option>
                            </select>
                        </label>
                    </section>
                    <ul class="flex-list buttons">
                        <li><button @click.prevent="redirectToEventForm($event)">Edit Event</button></li>
                        <li><input type="submit"
                                   :disabled="!isFormSubmittable"
                                   value="Create Event" /></li>
                    </ul>
                </form>
            </div>
            <div class="content" id="createTaskPanel" aria-hidden="true" tabindex="-1">
                <form ref="createTaskForm"
                      method="post"
                      :action="eventCreateUrl"
                      @submit.prevent="createNewEvent($refs.createTaskForm)"
                >
                    <input name="userId" type="hidden" :value="userId" />
                    <input name="allDay" type="hidden" :value="Number(event.allDay)" />
                    <input name="date"   type="hidden" :value="formDate" />
                    <input name="type"   type="hidden" :value="taskTypeId" />

                    <section>
                        <h3>Due <span>{{ displayDate }}</span></h3>

                        <label class="required" for="createTaskBubbleTitle">
                            Task
                        </label>
                        <input id="createTaskBubbleTitle" name="title" type="text" v-model.trim="event.title" />

                        <label class="detail">
                            Note
                            <textarea name="detail" v-model="event.detail"></textarea>
                        </label>
                    </section>
                    <div class="row">
                        <div class="small-12 columns no-pad">
                            <input type="submit"
                                   :disabled="!isFormSubmittable"
                                   value="Create Task" />
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </reveal-modal>
</template>
