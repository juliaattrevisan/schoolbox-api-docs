<script>
import RevealModal from "@schoolboxVue/components/reveal-modal.vue";

export default {
    components: {RevealModal},
    props: {
        getListEndpoint: {
            // The endpoint for fetching the list of items.
            type: String,
            required: true,
        },
        postItemEndpoint: {
            // The endpoint for posting a new item to.
            type: String,
            required: true,
        },
        putItemEndpointBase: {
            // The base endpoint for updating an item:
            // assume each item's put endpoint is this with the ID appended.
            type: String,
            required: true,
        },
        deleteItemEndpointBase: {
            // The endpoint for deleting an item:
            // assume each item's delete endpoint is this with the ID appended.
            type: String,
            required: true,
        },
    },

    mounted() {
        this.getData();
    },

    data() {
        return {
            isDataLoaded: false,
            hasDataLoadError: false,
            loadedData: [],
            newItem: {},
        };
    },

    methods: {
        async getData() {
            try {
                const response  = await fetch(this.getListEndpoint);
                this.loadedData = await response.json();
            } catch (error) {
                console.warn(error);
                this.hasDataLoadError = true; //show load error
            } finally {
                this.isDataLoaded = true; //update the display to show the list
            }
        },
        async postItem() {
            try {
                console.log(this.newItem);
                const response = await fetch(
                    this.postItemEndpoint,
                    {
                        method: 'POST',
                        body: JSON.stringify(this.newItem),
                        headers: {
                            "Content-Type": "application/json",
                        },
                    }
                );
                this.newItem = await response.json();
                this.$refs.addItem.close();
                this.$refs.itemAdded.open();
                this.loadedData.push(this.newItem)

                // Show toast
            } catch (error) {
                console.warn(error);
            } finally {
                this.isDataLoaded = true; //update the display to show the list
            }
        },
    }
}
</script>

<template>
    <div>
        <div v-if="isDataLoaded">
            <div class="small-12 island">
                <template v-if="loadedData.length === 0">
                    <div id="listEmptyState" class="empty-state">
                        <i class="icon-list"></i>
                        <p>
                            No oAuth clients have been configured.
                            <a href="#" @click.prevent="$refs.addItem.open">Add an oAuth client</a>.
                        </p>
                    </div>
                </template>
                <template v-else>
                    <table>
                        <tr>
                            <th>Name</th>
                            <th>Client ID</th>
                            <th>Client Secret</th>
                            <th>&nbsp;</th>
                        </tr>
                        <tr v-for="row in loadedData">
                            <td>{{ row.name }}</td>
                            <td>{{ row.clientId }}</td>
                            <td>
                                <template v-if="row.clientSecret">{{ row.clientSecret }}</template>
                                <template v-else>&mdash;</template>
                            </td>
                            <td><a href="#" class="icon-delete" title="Delete this client"></a></td>
                        </tr>
                    </table>
                    <div class="component-action">
                        <section>
                            <a class="button" href="#" @click.prevent="$refs.addItem.open">Add oAuth Client</a>
                        </section>
                    </div>
                </template>
            </div>
        </div>

        <div v-else-if="hasDataLoadError">
            <div class="small-12 island">
                <section class="content">
                    <div data-alert class="alert-box alert">
                        Sorry, we are currently unable to load this List data. Please contact Schoolbox Support it the
                        problem persists.
                    </div>
                </section>
            </div>
        </div>

        <div v-else>
            <table class="skeleton">
                <thead>
                <tr>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td></td>
                </tr>
                <tr>
                    <td></td>
                </tr>
                </tbody>
            </table>
        </div>

        <reveal-modal ref="addItem">
            <h2>Add oAuth Client</h2>
            <label for="addItem-name">Name</label>
            <input id="addItem-name" required v-model="newItem.name" />
            <div class="component-action">
                <section>
                    <a class="button" @click.prevent="postItem" href="#">Add</a>
                    <a class="button" @click.prevent="$refs.addItem.close" href="#">Cancel</a>
                </section>
            </div>
        </reveal-modal>

        <reveal-modal ref="itemAdded">
            <h2>oAuth Client Added</h2>
            <label for="itemAdded-name">Name</label>
            <input id="itemAdded-name" readonly v-model="newItem.name" />

            <label for="itemAdded-name">Client ID</label>
            <input id="itemAdded-name" readonly v-model="newItem.clientId" />

            <label for="itemAdded-name">Client Secret</label>
            <input id="itemAdded-name" readonly v-model="newItem.clientSecret" />

            <div data-alert="" class="alert-box warning">
                You must copy and store the client secret:
                it will no longer be retrievable after reloading this page.
            </div>

            <div class="component-action">
                <section>
                    <a class="button" @click.prevent="$refs.itemAdded.close" href="#">Close</a>
                </section>
            </div>
        </reveal-modal>
    </div>
</template>

<style scoped lang="scss">

</style>
