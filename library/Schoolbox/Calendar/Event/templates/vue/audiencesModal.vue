<template>
    <div>
        <reveal-modal ref="modal">
            <template v-if="isGroupTemplate">
                <div class="small-12 medium-6 columns no-pad" v-if="groups.length">
                    <p><strong>Groups</strong></p>
                    <ul>
                        <li v-for="(group, index) in groups" v-if="index > 0">
                            <a :href="/homepage/ + group.id">{{ group.name }}</a>
                        </li>
                    </ul>
                </div>
            </template>
            <template v-else>
                <div class="small-12 medium-6 columns no-pad" v-if="campuses.length">
                    <p><strong>{{ campusesLabel }}</strong></p>
                    <ul>
                        <li v-for="campus in campuses">
                            {{ campus.name }}
                        </li>
                    </ul>
                </div>
                <div class="small-12 medium-6 columns no-pad" v-if="roles.length">
                    <p><strong>{{ rolesLabel }}</strong></p>
                    <ul>
                        <li v-for="role in roles">
                            {{ role.name }}
                        </li>
                    </ul>
                </div>
            </template>
        </reveal-modal>
    </div>
</template>

<script>
    import RevealModal from "@schoolbox/reveal-modal";

    export default {
        name: "audiences-modal",
        props: ['campus-audiences', 'role-audiences', 'group-audiences', 'is-group'],
        components: {RevealModal},
        data() {
            return {
                campuses: this.campusAudiences,
                roles: this.roleAudiences,
                groups: this.groupAudiences,
                isGroupTemplate: this.isGroup,
            }
        },
        methods: {
            open() {
                this.$refs.modal.open();
            },
        },

        computed: {
            campusesLabel: function() {
                return (this.campuses.length > 1) ? 'Campuses' : 'Campus';
            },

            rolesLabel: function() {
                return (this.roles.length > 1) ? 'Roles' : 'Role';
            }
        }
    }
</script>

<style scoped>

</style>
