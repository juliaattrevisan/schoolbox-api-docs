import { LightGallerySettings } from "lightgallery/lg-settings";
import lgAutoplay from "lightgallery/plugins/autoplay";
import lgFullScreen from "lightgallery/plugins/fullscreen";
import lgVideo from "lightgallery/plugins/video";

// Define the default settings for lightGallery
const imageLightboxDefaults: LightGallerySettings = {
    plugins: [lgAutoplay, lgFullScreen, lgVideo],
    licenseKey: window.sbProps.imageLightboxLicenseKey,
    speed: 0,
};

export default imageLightboxDefaults;
