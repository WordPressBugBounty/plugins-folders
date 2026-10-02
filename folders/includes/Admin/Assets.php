<?php
namespace Folders\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Settings page asset loader.
 *
 * Enqueues the scripts, styles, localized strings and customization preview
 * CSS variables used on the Folders settings, upgrade and recommended
 * plugins admin pages.
 */
class Assets {

    /**
     * Register the `admin_enqueue_scripts` hook.
     */
    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Enqueue settings assets when the current admin page is a Folders settings page.
     *
     * @param string $page Current admin page hook suffix.
     * @return void
     */
    public function enqueue_scripts( $page ) {
        if ( in_array( $page, [ 'settings_page_premio-folders-settings', 'toplevel_page_wcp_folders_settings', 'folders-settings_page_folders-upgrade-to-pro', 'folders-settings_page_recommended-folder-plugins' ], true ) ) {
            self::enqueue_settings_assets();
        }
    }

    /**
     * Enqueue the scripts and styles for the Folders settings pages.
     *
     * Loads Select2, Spectrum, the settings script and styles, the mailcheck
     * script when the email sign-up modal is shown, localizes `folders_settings`
     * for JavaScript, and adds inline CSS variables for the customization preview.
     *
     * @return void
     */
    public static function enqueue_settings_assets() {
        $isShown = \Folders\Folders\FoldersSignup::check_modal_status();
        if ($isShown) {
            wp_enqueue_script( 'folders-mailcheck', FOLDERS_PLUGIN_URL . 'dist/js/mailcheck.js', array( 'jquery' ), FOLDERS_VERSION, true );
        }
        wp_enqueue_script( 'folders-select2', FOLDERS_PLUGIN_URL . 'dist/js/select2.js', array( 'jquery' ), FOLDERS_VERSION, true );
        wp_enqueue_script( 'folders-spectrum', FOLDERS_PLUGIN_URL . 'dist/js/spectrum.js', array( 'jquery' ), FOLDERS_VERSION, true );
        wp_enqueue_script( 'folders-settings', FOLDERS_PLUGIN_URL . 'dist/js/settings.js', array( 'jquery', 'jquery-ui-slider' ), FOLDERS_VERSION, true );
        wp_enqueue_style( 'folders-google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap', array(), FOLDERS_VERSION );
        wp_enqueue_style( 'folders-select2', FOLDERS_PLUGIN_URL . 'dist/css/select2.css', array(), FOLDERS_VERSION );
        wp_enqueue_style( 'folders-spectrum', FOLDERS_PLUGIN_URL . 'dist/css/spectrum.css', array(), FOLDERS_VERSION );
        wp_enqueue_style( 'folders-fonts', FOLDERS_PLUGIN_URL . 'dist/css/folders-font.css', array(), FOLDERS_VERSION );
        wp_enqueue_style('folders-deactivate-feedback', FOLDERS_PLUGIN_URL . 'dist/css/folders-feedback.css', array(), FOLDERS_VERSION);
        wp_enqueue_style( 'folders-settings', FOLDERS_PLUGIN_URL . 'dist/css/settings.css', array(), FOLDERS_VERSION );
        $hasValidKey = \Folders\Admin\License::is_license_active();
        $upgradeURL = \Folders\Admin\License::get_pro_url();
        wp_localize_script(
            'folders-settings',
            'folders_settings',
            [
                'lang'       => self::get_settings_lang(),
                'ajax_url'   => admin_url( 'admin-ajax.php' ),
                'rest_url'   => get_rest_url( null, 'folders-settings/v1/' ),
                'user_rest_url'   => get_rest_url( null, 'folders-users/v1/' ),
                'rest_nonce' => wp_create_nonce( 'wp_rest' ),
                'has_valid_key' => $hasValidKey,
                'upgrade_url' => $upgradeURL,
            ]
        );

        $settings = \Folders\Admin\Settings::get_field_settings('customization_settings');
        $folder_size = $settings['folder_size'] == 'custom' ? $settings['custom_font_size'] : $settings['folder_size'];

        $custom_css = ':root {';
        $custom_css .= '--preview-new-folder-bg-color: ' . esc_attr($settings['new_folder_color'] ? $settings['new_folder_color'] : '#FA166B') . ';';
        $custom_css .= '--preview-folder-bg-color: ' . esc_attr($settings['folder_bg_color'] ? $settings['folder_bg_color'] : '#FA166B') . ';';
        $custom_css .= '--preview-folder-icon-color: ' . esc_attr($settings['default_icon_color'] ? $settings['default_icon_color'] : '#334155') . ';';
        $custom_css .= '--preview-dropdown-border-color: ' . esc_attr($settings['dropdown_color'] ? $settings['dropdown_color'] : '#484848') . ';';
        $custom_css .= '--preview-bulk-organize-bg-color: ' . esc_attr($settings['bulk_organize_button_color'] ? $settings['bulk_organize_button_color'] : '#FA166B') . ';';
        $custom_css .= '--preview-font-size: ' . esc_attr($folder_size ? $folder_size : '16') . 'px;';
        $custom_css .= '--preview-font-family: "' . esc_attr($settings['folder_font']) . '", sans-serif;';
        $custom_css .= '}';

        wp_add_inline_style( 'folders-settings', $custom_css );
    }

    /**
     * Get localized strings for settings JS.
     *
     * @return array Array of localized strings.
     */
    public static function get_settings_lang() {
        return array(
            'save_settings'       => esc_html__( 'Settings saved successfully.', 'folders' ),
            'reset_settings'      => esc_html__( 'Settings reset successfully.', 'folders' ),
            'empty_license_key'   => esc_html__( 'License key should not be empty', 'folders' ),
            'invalid_license_key' => esc_html__( 'Invalid license key', 'folders' ),
            'error'               => esc_html__( 'Error during handling request', 'folders' ),
            'invalid_json_file'   => esc_html__( 'Invalid file type, please upload only json file', 'folders' ),
            'no_data_found'       => esc_html__( 'No data found', 'folders' ),
            'file_upload_error'   => esc_html__( 'Error during uploading file.', 'folders' ),
            'invalid_email'       => esc_html__( 'Email address is not valid', 'folders' ),
            'required_field_error'  => esc_html__( 'Please fill all required and invalid fields', 'folders' ),
            'required_message'    => esc_html__( 'This field is required', 'folders' ),
            'email_required'      => esc_html__( 'Email is required', 'folders' ),
            'name_required'       => esc_html__( 'Name is required', 'folders' ),
            'message_required'    => esc_html__( 'Please enter your message', 'folders' ),
            'subject_required'    => esc_html__( 'Subject is required', 'folders' ),
            'upgrade_to_pro'      => esc_html__( 'Upgrade to Pro', 'folders' ),
            'remove_other_folders'  => sprintf(esc_html__( "You're about to delete %ss folders. Are you sure you'd like to proceed?", 'folders' ), '%plugin%'),

        );
    }
}
