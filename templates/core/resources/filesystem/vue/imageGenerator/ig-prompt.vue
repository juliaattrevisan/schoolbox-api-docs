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

const prompt = window.Vue.ref<string>("Sketch an image of a snake with a bowtie and top hat");
const imageSize = window.Vue.ref<(typeof AVAILABLE_SIZES)[number]["value"] | null>(null);
const imageStyle = window.Vue.ref<(typeof AVAILABLE_STYLES)[number]["value"] | null>(null);

/**
 * Temp function to test the prompt
 */
async function sendRequest(): Promise<void> {
    const payload = {
        prompt: prompt.value,
        imageSize: imageSize.value,
        imageStyle: imageStyle.value,
    };

    const toast = window.sbProps.toastActivate("Generating image...");

    const resp = await fetch("/openai/image", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
        },
        body: JSON.stringify(payload),
    });

    if (!resp.ok) {
        toast.timeout({ text: "Failed to generate image", isSuccess: false });
        console.error("Failed to send request", resp);
        return;
    }

    const data = await resp.json();
    toast.timeout({ text: "Image generated successfully", isSuccess: true });
    console.log("Response data:", data);
}
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
    <div class="ig-prompt-actions actions-small-1 l-mar-m--bottom">
        <div class="list-item">
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
        </div>
        <nav>
            <a class="icon-send no-text" href="#" title="Generate Image" @click.prevent="sendRequest"></a>
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

    .list-item {
        display: flex;
        flex-wrap: wrap;

        /* unique small selects in this area */
        select {
            height: 1.5rem;
            min-height: 1.5rem;
            margin: 0.5rem;
            max-width: 160px;
        }
    }

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
</style>
