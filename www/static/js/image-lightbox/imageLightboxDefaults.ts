import lgAutoplay from "lightgallery/plugins/autoplay";
import lgFullScreen from "lightgallery/plugins/fullscreen";
import lgVideo from "lightgallery/plugins/video";

// Define the default settings for lightGallery
interface LightGalleryDefaults {
    plugins: any[];
    licenseKey: string;
    speed: number;
}

const imageLightboxDefaults: LightGalleryDefaults = {
    plugins: [lgAutoplay, lgFullScreen, lgVideo],
    licenseKey: window.sbProps.imageLightboxLicenseKey,
    speed: 0,
};

export default imageLightboxDefaults;
