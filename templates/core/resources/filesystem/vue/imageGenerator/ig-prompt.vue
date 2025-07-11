<script lang="ts" setup>
import { generateImage } from "./imageGeneratorPayload";

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

const prompt = window.Vue.ref<string>("");
const imageSize = window.Vue.ref<(typeof AVAILABLE_SIZES)[number]["value"] | null>(null);
const imageStyle = window.Vue.ref<(typeof AVAILABLE_STYLES)[number]["value"] | null>(null);

// TODO: All this should be moved elsewhere, this is just a test for now
const isGenerating = window.Vue.ref<boolean>(false);
const generateError = window.Vue.ref<string | null>(null);

type ImageResult = {
    /* the openai response ID so we can reference it in the next chat */
    previousResponseId: string;
    /* the prompt used to generate it */
    prompt: string;
    /* the image URL */
    src: string;
    /* a message from the AI, if any */
    message: string;
};

const imageResult = window.Vue.ref<ImageResult | null>(null);

/**
 * Temp function to test the prompt
 */
async function sendRequest(): Promise<void> {
    if (isGenerating.value) {
        return;
    }

    isGenerating.value = true;
    generateError.value = null;
    const toast = window.sbProps.toastActivate("Generating image...");

    // TODO: Pass thru route
    const response = await generateImage("/openai/image", {
        userPrompt: prompt.value,
        imageSize: imageSize.value,
        imageStyle: imageStyle.value,
        previousResponseId: imageResult.value?.previousResponseId ?? null,
    });

    isGenerating.value = false;
    if (response.success === false) {
        toast.timeout({ text: "Image generation failed", isSuccess: false });
        generateError.value = response.message;
        return;
    }

    toast.timeout({ text: "Image generated successfully", isSuccess: true });
    console.warn("Response data:", response.data);

    const image = response.data.output.find((item) => item.type === "image_generation_call");
    const message = response.data.output.find((item) => item.type === "message");
    const messageContent = message ? message.content.find((c) => c.type === "output_text") : null;

    // TODO: Handle the response data properly in the backend instead of here
    imageResult.value = {
        previousResponseId: response.data.id,
        prompt: prompt.value,
        src: `data:image/png;base64,${image.result}`,
        message: messageContent ? messageContent.text.trim() : "",
    };

    /* clear the prompt but not the other settings */
    prompt.value = "";
}
</script>

<template>
    <!-- TODO: this shouldn't be in this file, but for quick testing its here atm... -->
    <div v-if="imageResult" class="l-mar-m--bottom l-pad-s ig-feed-entry">
        <img :src="imageResult.src" :alt="imageResult.prompt" style="max-width: 200px; max-height: 200px" />
        <p v-if="imageResult.message" class="l-mar--0-bottom l-mar-s--top">{{ imageResult.message }}</p>
    </div>

    <div v-if="isGenerating" class="loader"></div>
    <div v-if="generateError" class="alert-box alert">
        <b>There was a problem generating the image.</b>
        {{ generateError }}
    </div>

    <label>
        <textarea
            v-model="prompt"
            name="prompt"
            rows="3"
            placeholder="Describe or refine the image you want to generate"
            style="resize: vertical; margin-bottom: 0"
            :disabled="isGenerating || undefined"
        ></textarea>
    </label>
    <div class="ig-prompt-actions actions-small-1 l-mar-m--bottom">
        <div class="list-item">
            <select v-model="imageSize" name="image-size" :disabled="isGenerating || undefined">
                <option :value="null" style="font-style: italic">Size</option>
                <option v-for="size in AVAILABLE_SIZES" :key="size.value" :value="size.value">
                    {{ size.label }}
                </option>
            </select>
            <select v-model="imageStyle" name="image-style" :disabled="isGenerating || undefined">
                <option :value="null" style="font-style: italic">Style</option>
                <option v-for="style in AVAILABLE_STYLES" :key="style.value" :value="style.value">
                    {{ style.label }}
                </option>
            </select>
        </div>
        <nav>
            <a
                class="icon-send"
                href="#"
                title="Generate Image"
                :disabled="isGenerating || undefined"
                @click.prevent="sendRequest"
            ></a>
        </nav>
    </div>
</template>

<style scoped lang="scss">
.ig-feed-entry {
    border: 1px solid #b3b3b3;
}

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
