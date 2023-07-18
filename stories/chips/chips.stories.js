import StorybookSlot from "../../vue/components/storybook-slot.vue";

export default {
    title: "Chips/Chips",
    component: StorybookSlot,
};

const DefaultTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <div class="u-padding-m">
            <h4 class="">What are they?</h4>
            <p>They are a type of selector that lends itself to sets of options with a common theme. Good examples of where these could be used are...</p>
            <ul>
                <li>
                    A list of assessment types within the settings of a component in Schoolbox
                </li>
                <li>
                    Selectors for the number of bedrooms, bathrooms or parking spots in a hotel / homeshare / real estate website or app
                </li>
            </ul>
            <p>
                They are <b>not</b> a complete replacement for checkboxes, nor do they function as a radio selectors in our current implementation
                (though the Material Design docs do say this functionality is within spec so we may add radio selector chips later)
            </p>
            <h4 class="">When should I consider using chips?</h4>
            <p>If you meet any or all of the below</p>
            <ul>
                <li>
                    A good use case is when selectable items are related by context (for example, filter options
                    that are visually grouped together in our UI and contextually relevant to each other)
                </li>
                <li>
                    When you want to preserve vertical space and items aren't expected to sit in an explicit grid
                </li>
                <li>
                    When you're okay with or specifically want the behaviour for items to automatically wrap to lower
                    rows when horizontal screen size is reduced
                </li>
                <li>
                    When you have more than a single thing that needs to be selected or added to a list. Chips should <b>always</b> appear in a
                    set and <b>never</b> appear in a location that allows only a single chip. These are not a direct replacement for checkboxes, nor are they buttons
                </li>
                <li>
                    When you have a set of options, you don't want them to wrap, and you want a component that allows users to scroll horizontally if there's overflow
                </li>
            </ul>
        </div>
        <h4 class="u-padding-m--horiz">Flavours</h4>
        <div class="sb-flex-col u-gap--xs u-padding-m">
            <ul class="sb-chips u-gap--xs">
                <li><input type="checkbox" id="cb-1-1" /><label for="cb-1-1">Apple<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" id="cb-1-2" /><label for="cb-1-2">Banana<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" id="cb-1-3" /><label for="cb-1-3">Carrot<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" id="cb-1-4" /><label for="cb-1-4">Durian<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-1-5" /><label for="cb-1-5">Endive<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-1-6" /><label for="cb-1-6">Fig<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-1-7" /><label for="cb-1-7">Grape<span class="sb-chip__icon icon-tick"></span></label></li>
            </ul>
            <ul class="sb-chips sb-chips--rounded u-gap--xs">
                <li><input type="checkbox" id="cb-2-1" /><label for="cb-2-1">Apple<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" id="cb-2-2" /><label for="cb-2-2">Banana<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" id="cb-2-3" /><label for="cb-2-3">Carrot<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" id="cb-2-4" /><label for="cb-2-4">Durian<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-2-5" /><label for="cb-2-5">Endive<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-2-6" /><label for="cb-2-6">Fig<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-2-7" /><label for="cb-2-7">Grape<span class="sb-chip__icon icon-tick"></span></label></li>
            </ul>
            <ul class="sb-chips u-gap--xs sb-chips--rounded sb-chips--no-border">
                <li><input type="checkbox" id="cb-3-1" /><label for="cb-3-1">Apple<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" id="cb-3-2" /><label for="cb-3-2">Banana<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" id="cb-3-3" /><label for="cb-3-3">Carrot<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" id="cb-3-4" /><label for="cb-3-4">Durian<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-3-5" /><label for="cb-3-5">Endive<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-3-6" /><label for="cb-3-6">Fig<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-3-7" /><label for="cb-3-7">Grape<span class="sb-chip__icon icon-tick"></span></label></li>
            </ul>
            <ul class="sb-chips u-gap--xs sb-chips--rounded sb-chips--no-background">
                <li><input type="checkbox" id="cb-4-1" /><label for="cb-4-1">Apple<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" id="cb-4-2" /><label for="cb-4-2">Banana<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" id="cb-4-3" /><label for="cb-4-3">Carrot<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" id="cb-4-4" /><label for="cb-4-4">Durian<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-4-5" /><label for="cb-4-5">Endive<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-4-6" /><label for="cb-4-6">Fig<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-4-7" /><label for="cb-4-7">Grape<span class="sb-chip__icon icon-tick"></span></label></li>
            </ul>
            <p>Chips are under no obligation to use the tick as their selected icon - all Schoolbox icons are supported</p>
            <ul class="sb-chips u-gap--xs sb-chips--rounded">
                <li><input type="checkbox" id="cb-12-1" /><label for="cb-12-1">Sunny / Clear<span class="sb-chip__icon icon-sunny"></span></label></span></li>
                <li><input type="checkbox" id="cb-12-2" /><label for="cb-12-2">Mostly Cloudy<span class="sb-chip__icon icon-mostly-cloudy"></span></label></span></li>
                <li><input type="checkbox" id="cb-12-3" /><label for="cb-12-3">Cloudy<span class="sb-chip__icon icon-cloudy"></span></label></span></li>
                <li><input type="checkbox" id="cb-12-4" /><label for="cb-12-4">Rain / Chance of Rain<span class="sb-chip__icon icon-rain"></span></label></li>
                <li><input type="checkbox" id="cb-12-5" /><label for="cb-12-5">Storm / Stormy Change<span class="sb-chip__icon icon-storm"></span></label></li>
            </ul>
            <p>Chips also support RTL layouts and have horiztonal overflow scrolling supported optionally</p>
            <ul class="sb-chips u-gap--xs sb-chips--rounded" dir="rtl">
                <li><input type="checkbox" id="cb-5-1" /><label for="cb-5-1">Apple<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" id="cb-5-2" /><label for="cb-5-2">Banana<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" id="cb-5-3" /><label for="cb-5-3">Carrot<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" id="cb-5-4" /><label for="cb-5-4">Durian<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-5-5" /><label for="cb-5-5">Endive<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-5-6" /><label for="cb-5-6">Fig<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-5-7" /><label for="cb-5-7">Grape<span class="sb-chip__icon icon-tick"></span></label></li>
            </ul>
            <ul class="sb-chips sb-chips--rounded sb-chips--scroll u-gap--xs">
                <li><input type="checkbox" id="cb-6-1" /><label for="cb-6-1">Apple<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" id="cb-6-2" /><label for="cb-6-2">Banana<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" id="cb-6-3" /><label for="cb-6-3">Carrot<span class="sb-chip__icon icon-tick"></span></label></span></li>
                <li><input type="checkbox" id="cb-6-4" /><label for="cb-6-4">Durian<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-6-5" /><label for="cb-6-5">Endive<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-6-6" /><label for="cb-6-6">Fig<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-6-7" /><label for="cb-6-7">Grape<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-6-8" /><label for="cb-6-8">Hazelnut<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-6-9" /><label for="cb-6-9">Iiiiiiiiiceberg Lettuce<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-6-10" /><label for="cb-6-10">Jackfruit<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-6-11" /><label for="cb-6-11">Kiwi<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-6-12" /><label for="cb-6-12">Lemon<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-6-13" /><label for="cb-6-13">Mandarine<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-6-14" /><label for="cb-6-14">Nectarine<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-6-15" /><label for="cb-6-15">Orange<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-6-16" /><label for="cb-6-16">Peach<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-6-17" /><label for="cb-6-17">Quince<span class="sb-chip__icon icon-tick"></span></label></li>
                <li><input type="checkbox" id="cb-6-18" /><label for="cb-6-18">Rocket<span class="sb-chip__icon icon-tick"></span></label></li>
            </ul>
        </div>
    `,
});

const RoundedTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <ul class="sb-chips sb-chips--rounded u-gap--xs u-padding-m">
            <li><input type="checkbox" id="cb-7-1" /><label for="cb-7-1">Apple<span class="sb-chip__icon icon-tick"></span></label></span></li>
            <li><input type="checkbox" id="cb-7-2" /><label for="cb-7-2">Banana<span class="sb-chip__icon icon-tick"></span></label></span></li>
            <li><input type="checkbox" id="cb-7-3" /><label for="cb-7-3">Carrot<span class="sb-chip__icon icon-tick"></span></label></span></li>
            <li><input type="checkbox" id="cb-7-4" /><label for="cb-7-4">Durian<span class="sb-chip__icon icon-tick"></span></label></li>
            <li><input type="checkbox" id="cb-7-5" /><label for="cb-7-5">Endive<span class="sb-chip__icon icon-tick"></span></label></li>
            <li><input type="checkbox" id="cb-7-6" /><label for="cb-7-6">Fig<span class="sb-chip__icon icon-tick"></span></label></li>
            <li><input type="checkbox" id="cb-7-7" /><label for="cb-7-7">Grape<span class="sb-chip__icon icon-tick"></span></label></li>
        </ul>
    `,
});

const NoBackgroundTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <ul class="sb-chips sb-chips--rounded sb-chips--no-background u-gap--xs u-padding-m">
            <li><input type="checkbox" id="cb-8-1" /><label for="cb-8-1">Apple<span class="sb-chip__icon icon-tick"></span></label></span></li>
            <li><input type="checkbox" id="cb-8-2" /><label for="cb-8-2">Banana<span class="sb-chip__icon icon-tick"></span></label></span></li>
            <li><input type="checkbox" id="cb-8-3" /><label for="cb-8-3">Carrot<span class="sb-chip__icon icon-tick"></span></label></span></li>
            <li><input type="checkbox" id="cb-8-4" /><label for="cb-8-4">Durian<span class="sb-chip__icon icon-tick"></span></label></li>
            <li><input type="checkbox" id="cb-8-5" /><label for="cb-8-5">Endive<span class="sb-chip__icon icon-tick"></span></label></li>
            <li><input type="checkbox" id="cb-8-6" /><label for="cb-8-6">Fig<span class="sb-chip__icon icon-tick"></span></label></li>
            <li><input type="checkbox" id="cb-8-7" /><label for="cb-8-7">Grape<span class="sb-chip__icon icon-tick"></span></label></li>
        </ul>
    `,
});

const NoBorderTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <ul class="sb-chips sb-chips--rounded sb-chips--no-border u-gap--xs u-padding-m">
            <li><input type="checkbox" id="cb-9-1" /><label for="cb-9-1">Apple<span class="sb-chip__icon icon-tick"></span></label></span></li>
            <li><input type="checkbox" id="cb-9-2" /><label for="cb-9-2">Banana<span class="sb-chip__icon icon-tick"></span></label></span></li>
            <li><input type="checkbox" id="cb-9-3" /><label for="cb-9-3">Carrot<span class="sb-chip__icon icon-tick"></span></label></span></li>
            <li><input type="checkbox" id="cb-9-4" /><label for="cb-9-4">Durian<span class="sb-chip__icon icon-tick"></span></label></li>
            <li><input type="checkbox" id="cb-9-5" /><label for="cb-9-5">Endive<span class="sb-chip__icon icon-tick"></span></label></li>
            <li><input type="checkbox" id="cb-9-6" /><label for="cb-9-6">Fig<span class="sb-chip__icon icon-tick"></span></label></li>
            <li><input type="checkbox" id="cb-9-7" /><label for="cb-9-7">Grape<span class="sb-chip__icon icon-tick"></span></label></li>
        </ul>
    `,
});

const RTLTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <ul class="sb-chips u-gap--xs u-padding-m" dir="rtl">
            <li><input type="checkbox" id="cb-10-1" /><label for="cb-10-1">Apple<span class="sb-chip__icon icon-tick"></span></label></span></li>
            <li><input type="checkbox" id="cb-10-2" /><label for="cb-10-2">Banana<span class="sb-chip__icon icon-tick"></span></label></span></li>
            <li><input type="checkbox" id="cb-10-3" /><label for="cb-10-3">Carrot<span class="sb-chip__icon icon-tick"></span></label></span></li>
            <li><input type="checkbox" id="cb-10-4" /><label for="cb-10-4">Durian<span class="sb-chip__icon icon-tick"></span></label></li>
            <li><input type="checkbox" id="cb-10-5" /><label for="cb-10-5">Endive<span class="sb-chip__icon icon-tick"></span></label></li>
            <li><input type="checkbox" id="cb-10-6" /><label for="cb-10-6">Fig<span class="sb-chip__icon icon-tick"></span></label></li>
            <li><input type="checkbox" id="cb-10-7" /><label for="cb-10-7">Grape<span class="sb-chip__icon icon-tick"></span></label></li>
        </ul>
    `,
});

const ScrollableTemplate = (args, { argTypes }) => ({
    components: { StorybookSlot },
    template: `
        <ul class="sb-chips sb-chips--scroll u-gap--xs u-padding-m">
            <li><input type="checkbox" id="cb-11-1" /><label for="cb-11-1">Apple<span class="sb-chip__icon icon-tick"></span></label></span></li>
            <li><input type="checkbox" id="cb-11-2" /><label for="cb-11-2">Banana<span class="sb-chip__icon icon-tick"></span></label></span></li>
            <li><input type="checkbox" id="cb-11-3" /><label for="cb-11-3">Carrot<span class="sb-chip__icon icon-tick"></span></label></span></li>
            <li><input type="checkbox" id="cb-11-4" /><label for="cb-11-4">Durian<span class="sb-chip__icon icon-tick"></span></label></li>
            <li><input type="checkbox" id="cb-11-5" /><label for="cb-11-5">Endive<span class="sb-chip__icon icon-tick"></span></label></li>
            <li><input type="checkbox" id="cb-11-6" /><label for="cb-11-6">Fig<span class="sb-chip__icon icon-tick"></span></label></li>
            <li><input type="checkbox" id="cb-11-7" /><label for="cb-11-7">Grape<span class="sb-chip__icon icon-tick"></span></label></li>
            <li><input type="checkbox" id="cb-11-8" /><label for="cb-11-8">Hazelnut<span class="sb-chip__icon icon-tick"></span></label></li>
            <li><input type="checkbox" id="cb-11-9" /><label for="cb-11-9">Iceberg Lettuce<span class="sb-chip__icon icon-tick"></span></label></li>
            <li><input type="checkbox" id="cb-11-10" /><label for="cb-11-10">Jackfruit<span class="sb-chip__icon icon-tick"></span></label></li>
            <li><input type="checkbox" id="cb-11-11" /><label for="cb-11-11">Kiwi<span class="sb-chip__icon icon-tick"></span></label></li>
            <li><input type="checkbox" id="cb-11-12" /><label for="cb-11-12">Lemon<span class="sb-chip__icon icon-tick"></span></label></li>
            <li><input type="checkbox" id="cb-11-13" /><label for="cb-11-13">Mandarine<span class="sb-chip__icon icon-tick"></span></label></li>
            <li><input type="checkbox" id="cb-11-14" /><label for="cb-11-14">Nectarine<span class="sb-chip__icon icon-tick"></span></label></li>
            <li><input type="checkbox" id="cb-11-15" /><label for="cb-11-15">Orange<span class="sb-chip__icon icon-tick"></span></label></li>
            <li><input type="checkbox" id="cb-11-16" /><label for="cb-11-16">Peach<span class="sb-chip__icon icon-tick"></span></label></li>
            <li><input type="checkbox" id="cb-11-17" /><label for="cb-11-17">Quince<span class="sb-chip__icon icon-tick"></span></label></li>
            <li><input type="checkbox" id="cb-11-18" /><label for="cb-11-18">Rocket<span class="sb-chip__icon icon-tick"></span></label></li>
        </ul>
    `,
});

export const Default = DefaultTemplate.bind({});
Default.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const Rounded = RoundedTemplate.bind({});
Rounded.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const NoBackground = NoBackgroundTemplate.bind({});
NoBackground.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const NoBorder = NoBorderTemplate.bind({});
NoBorder.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const RTL = RTLTemplate.bind({});
RTL.parameters = {
    controls: { hideNoControlsWarning: true },
};

export const Scrollable = ScrollableTemplate.bind({});
Scrollable.parameters = {
    controls: { hideNoControlsWarning: true },
};
