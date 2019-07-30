<script>
    import RevealModal from '@schoolbox/reveal-modal'
    import axios       from 'axios'

    export default {
        name: "create-event-modal",
        props: [
            'event',
            'userId',
            'eventTypes',
            'taskTypeId',
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
            formDate() {
                return this.event.date
                    ? this.toMoment(this.event.date).format(this.event.allDay ? 'DD-MM-YYYY' : 'DD-MM-YYYY HH:mm')
                    : '';
            },
            displayDate() {
                return this.event.date
                    ? this.toMoment(this.event.date).format(this.event.allDay ? 'ddd Do MMM' : 'ddd Do MMM, h:mma')
                    : '';
            },
        },
        methods: {
            open() {
                this.$refs.modal.open();
            },
            createNewEvent: function (form) {
                let self = this;
                axios.request({
                    url:          form.action,
                    data:         window.$(form).serialize(),
                    method:       form.method,
                    responseType: 'json',
                }).then(function (response) {
                    // Add this event to the calendar
                    self.$emit('eventCreated', response.data);
                }).finally(function () {
                    self.$refs.modal.close();
                });
            },
            redirectToEventForm: function () {
                // Use jQuery's form serialize for now: replace later
                window.location.href = '/calendar/modify.php?from=inline&returnTo=calendar&'
                    + window.$(this.$refs.createEventForm).serialize();
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
                      action="/calendar/ajax/create"
                      @submit.prevent="createNewEvent($refs.createEventForm)"
                      class="top"
                >
                    <input name="userId" type="hidden" :value="userId" />
                    <input name="allDay" type="hidden" :value="event.allDay ? 1 : 0" />
                    <input name="date"   type="hidden" :value="formDate" />

                    <section>
                        <h3>On <span>{{ displayDate }}</span></h3>

                        <label>
                            Event Title
                            <input name="title" type="text" v-model="event.title" />
                        </label>

                        <label>
                            Event Type
                            <select name="type" v-model="event.type">
                                <option v-for="eventType in eventTypes" :value="eventType.id">
                                    {{ eventType.name }}
                                </option>
                            </select>
                        </label>
                    </section>
                    <ul class="flex-list buttons">
                        <li><button @click.prevent="redirectToEventForm($event)">Edit Event</button></li>
                        <li><input type="submit"
                                   :disabled="!isNewEventSubmittable"
                                   value="Create Event" /></li>
                    </ul>
                </form>
            </div>
            <div class="content" id="createTaskPanel" aria-hidden="true" tabindex="-1">
                <form ref="createTaskForm"
                      method="post"
                      action="/calendar/ajax/create"
                      @submit.prevent="createNewEvent($refs.createTaskForm)"
                >
                    <input name="userId" type="hidden" :value="userId" />
                    <input name="allDay" type="hidden" :value="event.allDay ? 1 : 0" />
                    <input name="date"   type="hidden" :value="formDate" />
                    <input name="type"   type="hidden" :value="taskTypeId" />

                    <section>
                        <h3>Due <span>{{ displayDate }}</span></h3>

                        <label>
                            Task
                            <input name="title" type="text" v-model="event.title" />
                        </label>

                        <label class="detail">
                            Note
                            <textarea name="detail" v-model="event.detail"></textarea>
                        </label>
                    </section>
                    <div class="row">
                        <div class="small-12 columns no-pad">
                            <input type="submit"
                                   :disabled="!isNewEventSubmittable"
                                   value="Create Task" />
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </reveal-modal>
</template>

<style scoped>

</style>
