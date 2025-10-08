import { IMAGE_LIGHTBOX_CREATE, type ImageLightboxCreateEvent } from "@js/image-lightbox/imageLightboxEvent";
import type { StorageFile } from "@js/resource/resourceUtils";
import { apiRequest } from "@js/util/sbx/apiUtils";

enum PreviewType {
    CUSTOM = "custom",
    REDIRECT = "redirect",
    IMAGE = "image",
    IFRAME = "iframe",
    PDF = "pdf",

    INLINE = "inline",
}

type PreviewApiResponse = {
    type: PreviewType | null;
    fragment: string | null;
};

/**
 * Open a preview for a file, or redirect to download if not previewable
 * @param fileUrl the URL of the file to preview
 * @param baseUrl the base URL to use for the preview, defaults to /preview
 */
export async function openFilePreview(
    fileUrl: NonNullable<StorageFile["_links"]["fetch"]>,
    baseUrl: string = "/preview",
): Promise<void> {
    document.dispatchEvent(new Event("OVERLAY_LOADER_SHOW"));

    const previewRoute = `${baseUrl}/${encodeURIComponent(fileUrl)}`;
    const apiResponse = await apiRequest<PreviewApiResponse>({ method: "GET", url: previewRoute });

    document.dispatchEvent(new Event("OVERLAY_LOADER_HIDE"));

    // Did something break?
    // Just pretend this preview never happened.
    if (!apiResponse.success) {
        window.location.href = fileUrl;
        return;
    }

    const response =
        apiResponse.data.fragment === null || apiResponse.data.type === null
            ? {
                  type: PreviewType.CUSTOM,
                  fragment: "There was a problem loading the preview, this file may have been deleted.",
              }
            : {
                  type: apiResponse.data.type,
                  fragment: apiResponse.data.fragment,
              };

    if (response.type === PreviewType.REDIRECT) {
        window.location.href = response.fragment;
        return;
    }

    let lightboxDetail: ImageLightboxCreateEvent["detail"];
    if (response.type === PreviewType.IMAGE) {
        lightboxDetail = {
            targetKey: "preview-image",
            options: {
                dynamic: true,
                dynamicEl: [{ src: response.fragment }],
                mobileSettings: {
                    showCloseIcon: true,
                },
            },
            onCreated: (gallery): void => {
                gallery.openGallery();
            },
        };
    } else if (response.type === PreviewType.IFRAME) {
        lightboxDetail = {
            targetKey: "preview-iframe",
            options: {
                dynamic: true,
                dynamicEl: [
                    {
                        src: response.fragment,
                        iframe: true,
                    },
                ],
                iframeWidth: "80%",
                iframeHeight: "80%",
                mobileSettings: {
                    showCloseIcon: true,
                },
            },
            onCreated: (gallery): void => {
                gallery.openGallery();
            },
        };
    } else if (response.type === PreviewType.PDF) {
        // Workaround CORS issues by forcing the PDF.js viewer to be loaded from Schoolbox's
        // host, rather than from the CDN.
        const viewerUrl = new URL("/preview/pdfjs/web/viewer.html", window.location.origin);
        const pdfUrl = new URL(response.fragment, window.location.origin);

        viewerUrl.searchParams.set("file", pdfUrl.toString());

        lightboxDetail = {
            targetKey: "preview-iframe",
            options: {
                autoplay: false,
                counter: false, // hide as could be confusing with the pages of the pdf conflicting with the gallery counter
                download: false, // download would be of the viewer, not the actual pdf
                dynamic: true,
                dynamicEl: [
                    {
                        src: viewerUrl.toString(),
                        iframe: true,
                    },
                ],
                enableDrag: false, // ensure doesn't cause any conflict with dragging in the pdf viewer
                enableSwipe: false, // ensure doesn't cause any conflict with swiping in the pdf viewer
                iframeWidth: "100%",
                iframeHeight: "100%",
                mobileSettings: {
                    showCloseIcon: true,
                },
            },
            onCreated: (gallery): void => {
                gallery.el.addEventListener("lgAfterOpen", () => {
                    // @ts-expect-error -- This is a slight workaround to display the content properly, as LightGallery
                    // has no way of adding custom HTML to the lightbox, this replaces the preloader
                    // image with the content we want to load.
                    const lightGallerySelector = gallery.$inner.selector as HTMLElement;
                    const iframe = lightGallerySelector.querySelector("iframe");

                    if (iframe) {
                        // Hide the editor mode buttons in the PDF.js viewer
                        iframe.onload = (): void => {
                            const editorButtons = iframe.contentWindow?.document.getElementById("editorModeButtons");
                            if (editorButtons) {
                                editorButtons.style.display = "none";
                            }
                        };
                    }
                });
                gallery.openGallery();
            },
        };
    } else {
        lightboxDetail = {
            targetKey: "preview-custom",
            options: {
                dynamic: true,
                dynamicEl: [
                    {
                        src: `${window.cdnPrefix}/images/foundation/preloader.gif`,
                    },
                ],
                mobileSettings: {
                    showCloseIcon: true,
                },
            },
            onCreated: (gallery): void => {
                // The fragment sent back can contain scripts, so it is important that we
                // append it to the DOM in a way that will execute the scripts as well.
                const fragmentDiv = document.createRange().createContextualFragment(
                    `<div class="lg-video-cont" style="width:80%;max-width:100%;max-height:100%;">
                                                    <div class="preview fragment">${response.fragment}</div>
                                                </div>`,
                );

                // This is a slight workaround to display the content properly, as LightGallery
                // has no way of adding custom HTML to the lightbox, this replaces the preloader
                // image with the content we want to load.
                gallery.el.addEventListener("lgAfterOpen", () => {
                    // @ts-expect-error -- This is a slight workaround to display the content properly, as LightGallery
                    // has no way of adding custom HTML to the lightbox, this replaces the preloader
                    // image with the content we want to load.
                    const lightGallerySelector = gallery.$inner.selector as HTMLElement;

                    if (!lightGallerySelector.querySelector(".lg-current")) {
                        return;
                    }

                    const lgCurrent = lightGallerySelector.querySelector(".lg-current");
                    if (lgCurrent) {
                        lgCurrent.innerHTML = "";
                        lgCurrent.appendChild(fragmentDiv);
                    }

                    // As we have manipulated the contents of the lightbox ourselves, LightGallery
                    // will have no idea to change the download link to the correct URL, so we need
                    // to do it ourselves. Uses optional chaining to ensure the download link exists.
                    lightGallerySelector.querySelector("[download]")?.setAttribute("href", fileUrl);
                });

                gallery.openGallery();
            },
        };
    }

    document.dispatchEvent(
        new CustomEvent<ImageLightboxCreateEvent["detail"]>(IMAGE_LIGHTBOX_CREATE, { detail: lightboxDetail }),
    );
}
