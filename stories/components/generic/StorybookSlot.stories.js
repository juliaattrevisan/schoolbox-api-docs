import StorybookSlot from "../../../vue/components/storybook-slot";

export default {
    title: "Components/Generic/StorybookSlot",
    component: StorybookSlot,
};

const DefaultTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="l-pad-m">
            <h3>Storybook Slot</h3>
            <p>
                Hi. This is a generic container class for use with non-Vue components inside Storybook.
                Instead of having to declare a new Vue component every time to act as a placeholder, just use this one instead.
            </p>
            <p>For an example of how to use this, look at this exact story file you're reading now - stories/components/generic/StorybookSlot.stories.js.</p>
            <p>Just put your HTML inside the 'template' property of a Storybook template importing StorybookSlot and you're gold.</p>
        </div>
    `,
});

export const Default = DefaultTemplate.bind({});
Default.parameters = {
    controls: { hideNoControlsWarning: true },
};
