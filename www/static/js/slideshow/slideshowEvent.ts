import type { SwiperOptions } from "node_modules/swiper/types/swiper-options";

export const SWIPER_INIT = "swiperInitialize";

export interface SwiperInitializeEvent extends CustomEvent {
    detail: {
        target: HTMLElement;
        options: SwiperOptions;
    };
}

export interface SwiperDocumentEventHandlerMap {
    [SWIPER_INIT]: SwiperInitializeEvent;
}
