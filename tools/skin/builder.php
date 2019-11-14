#!/usr/bin/php
<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 */

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';


    /** @inheritDoc */
    public function getShortOpts(): array
    {
        return ['f'];
    }


    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return [
            'force',
            self::OPT_LONG_HELP
        ];
    }


    /** @inheritDoc */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Skin Builder compiles .scss files into .css files, including an instance's customisations.
Those customisations override the defaults and are periodically generated when required.
Customisations are chosen in Skin Config.

You can force regeneration of .css files using the --force parameter.

Usage: builder.php [--force]


HELP;
            exit;
        }

        $logger = $this->getLogger();

        define('SKIN_FILE', dirname(__FILE__) .'/../../www/css/foundation/utils/_skin-'.INSTANCE_ID.'.scss');
        define('CUSTOM_CSS_FILE', dirname(__FILE__) .'/../../www/css/foundation/utils/_custom-'.INSTANCE_ID.'.scss');

        $force = $this->getOpt('f') || $this->getOpt('force');

        // Skin builder is off? don't set any vars
        $logger->info('Skin builder is on - building using color settings from skin builder');

        $vars = [
            'navigation-foreground' => Config::get('skin_colour_navigation_foreground'),
            'navigation-background' => Config::get('skin_colour_navigation_background'),
            'navigation-hover' => Config::get('skin_colour_navigation_hover'),
            'off-canvas-foreground' => Config::get('skin_colour_offcanvas_foreground'),
            'off-canvas-background' => Config::get('skin_colour_offcanvas_background'),
            'off-canvas-hover' => Config::get('skin_colour_offcanvas_hover'),
            'accounts-menu-bg-colour' => Config::get('accounts_menu_bg_colour'),
            'accounts-menu-text-colour' => Config::get('accounts_menu_text_colour'),
            'accounts-menu-hover-colour' => Config::get('accounts_menu_hover_colour'),
            'content-ui-foreground' => Config::get('skin_colour_content_ui_foreground'),
            'content-ui-background' => Config::get('skin_colour_content_ui_background'),
            'content-ui-hover' => Config::get('skin_colour_content_ui_hover'),
            'content-ui-selected' => Config::get('skin_colour_content_ui_selected'),
            'content-ui-submit-foreground' => Config::get('skin_colour_content_ui_submit_foreground'),
            'content-ui-submit-background' => Config::get('skin_colour_content_ui_submit_background'),
            'accent-foreground' => Config::get('skin_colour_accent_foreground'),
            'accent-background' => Config::get('skin_colour_accent_background'),
            'accent-hover' => Config::get('skin_colour_accent_hover'),
            'logo-background' => Config::get('skin_colour_logo_background'),
            'body-bg' => Config::get('skin_colour_body_bg'),
            'button-input-scale' => Config::get('skin_button_input_scale'),
            'login-form-background-color' => Config::get('skin_colour_login_background'),
            'form-border-color' => Config::get('skin_colour_form_input_border_color'),
            'form-bg-hover-color' => Config::get('skin_colour_form_background_hover_color'),
        ];

        if (is_readable(SKIN_FILE) && is_readable(CUSTOM_CSS_FILE)) {
            $origSha = sha1_file(SKIN_FILE);
            $origCustomSha = sha1_file(CUSTOM_CSS_FILE);
            $logger->info("Skin has been built before (hash: $origSha)");
        } else {
            // File doesnt exist, force the build
            $logger->info('Skin has not been built before - forcing build');
            $force = true;
            $origSha = null;
        }

        $newSha  = null;
        $customSha = null;

        $fp = fopen(SKIN_FILE, 'w');

        if ($fp) {
            $logger->info('Creating skin file...');
            fputs($fp, "// Do not edit, automatically created by tools/skin/builder.php\n");

            foreach ($vars as $var => $val) {
                fputs($fp, '$'.$var.': '.$val.";\n");
            }

            fclose($fp);

            /* Output custom css */
            if ($cfp = fopen(CUSTOM_CSS_FILE, 'w')) {
                fputs($cfp, "// Do not edit, automatically created by tools/skin/builder.php\n");
                fputs($cfp, Config::get('skin_custom_css'));
                fclose($cfp);
            }

            $newSha = sha1_file(SKIN_FILE);
            $newCustomSha = sha1_file(CUSTOM_CSS_FILE);
            $logger->info("Skin file created (hash: $newSha)");
        }

        $siteLocation = Config::get('site_location');

        if ($origSha != $newSha || $origCustomSha != $newCustomSha || $force) {
            $logger->info('Skin has changed, or build was forced - rebuilding');
            // Compile the new sass file.
            $infile  = SITE_root . '/www/css/foundation/chrome-foundation.scss';
            $infileInstance  = SITE_root . '/www/css/foundation/chrome-foundation-'.INSTANCE_ID.'.scss';
            $outfile = SITE_root . '/www/css/'.$siteLocation.'/chrome-foundation.css';
            $outdir  = dirname($outfile);
            $tmpfile = sb_tempnam('css');

            $infileContent = file_get_contents($infile);
            $infileContent = str_replace('utils/skin', 'utils/skin-'.INSTANCE_ID, $infileContent);
            $infileContent = str_replace('utils/custom', 'utils/custom-'.INSTANCE_ID, $infileContent);
            file_put_contents($infileInstance, $infileContent);

            $cmd = sprintf('sass --style compressed %s %s', escapeshellarg($infileInstance), escapeshellarg($tmpfile));
            $logger->info("Building SASS...");
            exec($cmd, $output, $outputCode);

            if ($outputCode === 0) {
                $logger->info("SASS built - copying to CSS location");

                if (!is_dir($outdir)) {
                    mkdir($outdir);
                }

                copy($tmpfile, $outfile);
                // Copy the sourcemap
                if (is_readable($tmpfile.'.map')) {
                    $tmpMap = $tmpfile.'.map';
                    $outMap = $outfile.'.map';
                    copy($tmpMap, $outMap);
                    // Look for the sourcemap reference in $outfile and fix it manually
                    file_put_contents(
                        $outfile,
                        str_replace(
                            basename($tmpMap),
                            basename($outMap),
                            file_get_contents($outfile)
                        )
                    );
                }

                $logger->info("Building stripped down CSS for editor");
                $editorIn = SITE_root . '/www/css/foundation/editor.scss';
                $editorInInstance = SITE_root . '/www/css/foundation/editor-'.INSTANCE_ID.'.scss';
                $editorOut = SITE_root . '/www/css/'.$siteLocation.'/editor.css';

                $editorInContent = file_get_contents($editorIn);
                $editorInContent = str_replace('utils/skin', 'utils/skin-'.INSTANCE_ID, $editorInContent);
                $editorInContent = str_replace('utils/custom', 'utils/custom-'.INSTANCE_ID, $editorInContent);
                file_put_contents($editorInInstance, $editorInContent);

                $cmd     = sprintf('sass --style compressed --sourcemap=none %s %s', escapeshellarg($editorInInstance), escapeshellarg($editorOut));
                exec($cmd, $output, $outputCode);

                if ($outputCode !== 0) {
                    $logger->warning("Something went wrong building editor CSS (exit code: {$outputCode})");
                }
            } else {
                // something went wrong
                $logger->error("Something went wrong during SASS build process! (exit code: {$outputCode})");
                $logger->info("Command: {$cmd}");
                $logger->info('Output:');

                foreach ($output as $line) {
                    $logger->info($line);
                }
            }

            if (is_readable($tmpfile)) {
                unlink($tmpfile);
            }

            if (is_readable($tmpfile.'.map')) {
                unlink($tmpfile.'.map');
            }

            exit($outputCode);
        }

        $logger->info('Skin has not changed, and build was not forced');
        exit(0);
    }
};

$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]);
$kernel->runApp();
