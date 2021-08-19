-- Increasing size to be able to store something like:
-- https://lh3.googleusercontent.com/proxy/8-T6Yk4eKi2BSPfx3zPH7M0M_HMtKfcdFxPmaRp4XoLJLxakifUgZjaEVs50nCbeLEcT1ZclcFK0siphrWZOMb8jAnHUgJo6R9Itfg1e1dqCMe8wEp2zmZKEZoo997rIXT85-CqTC4_HUgeSmtHRRvmR1Ax3IAo=s32
ALTER TABLE google_file MODIFY icon VARCHAR(255);
