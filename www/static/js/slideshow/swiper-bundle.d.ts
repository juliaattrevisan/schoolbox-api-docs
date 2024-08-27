/**
 * The swiper/bundle module does not have a usable typescript definition file
 * for importing "swiper/bundle" instead of just "swiper", this file provides
 * a basic type definition for the module, but it may not be complete or
 * entirely correct. In our uses, we don't need more specifics than this yet.
 */

declare module "swiper/bundle" {
    import Swiper from "swiper";

    export default Swiper;
}
