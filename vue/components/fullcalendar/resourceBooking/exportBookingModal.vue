<template>
    <reveal-modal ref="modal">
        <h2 id="calendar-export-dialog-text">Live Feed URL</h2>
        <p class="meta">Use the following address to subscribe your favourite calendar application to a live feed.</p>
        <div id="calendar-export-dialog-url" class="alert-box">{{ exportUrl }}</div>
    </reveal-modal>
</template>

<script>
    import RevealModal from "@schoolbox/reveal-modal";
    import qs          from 'qs';

    export default {
        name: "export-booking-modal",
        props: [
            'token',
            'bookingExportUrl',
            'resourceId',
            'bookingType',
        ],
        components: {RevealModal},
        methods: {
            /**
             * Open the modal.
             */
            open() {
                this.$refs.modal.open();
            }
        },
        computed: {
            window: () => window,

            exportUrl() {
                let values = Object.assign({}, this.form);

                // Add token to object
                values.token = this.token;

                if (this.bookingType === 'category') {
                    values.resource_category_id = this.resourceId;
                } else {
                    values.resource_id = this.resourceId;
                }

                return this.window.location.protocol
                    + '//'
                    + this.window.location.host
                    + this.bookingExportUrl
                    + '?'
                    + qs.stringify(values);
            }
        }
    }
</script>
