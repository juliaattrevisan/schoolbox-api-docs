/**
 * TODO - Return and add menu items with icons.
 */
import MenuItem from "../../vue/components/menu/pop-out-menu-item.vue";

export default {
    title: "Menu/MenuItem",
    component: MenuItem,
};

const DefaultTemplate = (args, { argTypes }) => ({
    components: { MenuItem },
    template: `
        <MenuItem>I am a menu item</MenuItem>
        `,
});

const DisabledTemplate = (args, { argTypes }) => ({
    components: { MenuItem },
    template: `
        <MenuItem class="is-disabled">I am a disabled menu item</MenuItem>
        `,
});

const IconTemplate = (args, { argTypes }) => ({
    components: { MenuItem },
    setup() {
        return { args };
    },
    template: `
        <MenuItem v-bind="args">I am a menu item with an icon</MenuItem>
        `,
});

const MarkerTemplate = (args, { argTypes }) => ({
    components: { MenuItem },
    setup() {
        return { args };
    },
    template: `
        <MenuItem v-bind="args">I am a menu item with a marker</MenuItem>
        `,
});

export const Default = DefaultTemplate.bind({});
Default.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const Disabled = DisabledTemplate.bind({});
Disabled.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const Icon = IconTemplate.bind({});
Icon.parameters = {
    controls: { hideNoControlsWarning: true },
};
Icon.args = {
    iconText: "teacher",
};

export const Marker = MarkerTemplate.bind({});
Marker.parameters = {
    controls: { hideNoControlsWarning: true },
};
Marker.args = {
    iconText: "marker",
};
