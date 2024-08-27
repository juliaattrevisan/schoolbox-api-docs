/**
 * Returns the setting for the mediaEmbed plugin, which rely primarily
 * on our use of iframely to embed media.
 */
import type { MediaEmbedConfig } from "@ckeditor/ckeditor5-media-embed";

const IFRAME_SRC = "//cdn.iframe.ly/api/iframe";

/**
 * Returns the settings for the mediaEmbed plugin.
 * @returns {{ mediaEmbed: MediaEmbedConfig }} - the media embed settings
 */
export function getMediaEmbedSettings(): { mediaEmbed: MediaEmbedConfig } {
    return {
        // Configure 'mediaEmbed' with Iframely previews.
        mediaEmbed: {
            // Previews are always enabled if there’s a provider for a URL (below regex catches all URLs)
            // By default `previewsInData` are disabled, but let’s set it to `false` explicitely to be sure
            previewsInData: false,
            providers: [
                {
                    name: "iframely previews", // hint: this is just for previews. Get actual HTML codes by making API calls from your CMS
                    url: /.+/, // Match all URLs or just the ones you need
                    html: (match) => {
                        const url = match[0];
                        const iframeUrl = `${IFRAME_SRC}?app=1&key=${window.sbProps.iframelyLicenseKey}&url=${encodeURIComponent(url)}`;
                        // alternatively, use &key= instead of &api_key with the MD5 hash of your api_key
                        // more about it: https://iframely.com/docs/allow-origins
                        return (
                            // If you need, set maxwidth and other styles for 'iframely-embed' class - it's yours to customize
                            `<div class="iframely-embed">
                                <div class="iframely-responsive">
                                    <iframe src="${iframeUrl}" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen>
                                    </iframe>
                                </div>
                            </div>`
                        );
                    },
                },
            ],
        },
    };
}
