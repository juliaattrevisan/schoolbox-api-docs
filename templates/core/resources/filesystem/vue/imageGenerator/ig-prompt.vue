<script lang="ts" setup>
// TODO: Pull in these sizes/styles from somewhere configurable?
const AVAILABLE_SIZES = [
    { value: "square", label: "Square (1x1)" },
    { value: "landscape", label: "Landscape (3x2)" },
    { value: "portrait", label: "Portrait (2x3)" },
] as const;

const AVAILABLE_STYLES = [
    { value: "background", label: "Background" },
    { value: "cartoon", label: "Cartoon" },
    { value: "oil_painting", label: "Oil Painting" },
    { value: "photo_realistic", label: "Photorealistic" },
    { value: "pop_art", label: "Pop Art" },
    { value: "sketch", label: "Sketch" },
    { value: "surrealism", label: "Surrealism" },
    { value: "wide_angle", label: "Wide Angle" },
    { value: "vector_art", label: "Vector Art" },
] as const;

const prompt = window.Vue.ref<string>("test");
const imageSize = window.Vue.ref<(typeof AVAILABLE_SIZES)[number]["value"] | null>(null);
const imageStyle = window.Vue.ref<(typeof AVAILABLE_STYLES)[number]["value"] | null>(null);
</script>

<template>
    <label>
        <textarea
            v-model="prompt"
            name="prompt"
            rows="3"
            placeholder="Describe or refine the image you want to generate"
            style="resize: vertical; margin-bottom: 0"
        ></textarea>
    </label>
    <div class="ig-prompt-actions actions-small-1">
        <select v-model="imageSize" name="image-size">
            <option :value="null" style="font-style: italic">Size</option>
            <option v-for="size in AVAILABLE_SIZES" :key="size.value" :value="size.value">
                {{ size.label }}
            </option>
        </select>
        <select v-model="imageStyle" name="image-style">
            <option :value="null" style="font-style: italic">Style</option>
            <option v-for="style in AVAILABLE_STYLES" :key="style.value" :value="style.value">
                {{ style.label }}
            </option>
        </select>
        <nav>
            <a class="icon-send no-text" href="#" title="Modify"></a>
        </nav>
    </div>
</template>

<style scoped lang="scss">
.ig-prompt-actions {
    /* mimic the style if this were an input */
    border-style: solid;
    border-width: 0.0625rem;
    border-color: var(--form-border-color);
    border-top: 0;
    margin-bottom: 1rem;

    /* unique small selects in this area */
    select {
        margin: 0.5rem;
        min-height: 2rem;
        width: 25%;
        height: 2rem;
    }

    nav {
        margin: 0.25rem;

        /* a custom icon that isn't from the sbx font for now hence these are some rather specific margins
         * to align the icon, we should probably just add it to our actual icons... */
        .icon-send {
            height: 2.5rem;

            &::before {
                content: "➤";
                font-size: 1.5rem;
                line-height: 2.25rem;
            }
        }
    }
}
</style>
