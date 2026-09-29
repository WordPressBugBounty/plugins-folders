<?php
namespace Folders\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin deactivation handler.
 *
 * Shows the deactivation feedback modal on the Plugins screen and removes
 * plugin data on deactivation when the user has opted in.
 */
class Deactivate {

    /**
     * Register hooks for the deactivation feedback modal and its assets.
     */
    public function __construct() {
        add_action('admin_footer', array($this, 'deactivate_feedback_form'));
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Output the deactivation feedback modal on the Plugins screen.
     *
     * Only rendered for users who can manage options. Hooked to `admin_footer`.
     *
     * @return void
     */
    public function deactivate_feedback_form()
    {
        global $pagenow;
        if($pagenow === 'plugins.php' && current_user_can('manage_options')) {
            include_once FOLDERS_TEMPLATE_DIR . 'folders/modals/deactivate-feedback-modal.php';
            include_once FOLDERS_TEMPLATE_DIR . 'footer/footer.php';
        }
    }

    /**
     * Enqueue styles and scripts for the deactivation feedback modal.
     *
     * Only loaded on the Plugins screen for users who can manage options.
     *
     * @return void
     */
    public function enqueue_scripts() {
        global $pagenow;
        if($pagenow === 'plugins.php' && current_user_can('manage_options')) {
            wp_enqueue_style('folders-deactivate-feedback', FOLDERS_PLUGIN_URL . 'dist/css/folders-feedback.css', array(), FOLDERS_VERSION);
            wp_enqueue_style('folders-settings', FOLDERS_PLUGIN_URL . 'dist/css/settings.css', array(), FOLDERS_VERSION);
            wp_enqueue_script('folders-deactivate-feedback', FOLDERS_PLUGIN_URL . 'dist/js/folders-feedback.js', array('jquery'), FOLDERS_VERSION, true);
            wp_localize_script(
                'folders-deactivate-feedback',
                'folders_settings',
                [
                    'ajax_url'      => admin_url( 'admin-ajax.php' ),
                    'rest_url'      => get_rest_url( null, 'folders-settings/v1/' ),
                    'rest_nonce'    => wp_create_nonce( 'wp_rest' ),
                    'lang'       => \Folders\Admin\Assets::get_settings_lang(),
                ]
            );
        }
    }

    /**
     * Run deactivation tasks.
     *
     * Deletes all folder data when the "remove folders when removed" advanced
     * setting is enabled. Requires the `activate_plugins` capability.
     *
     * @return void
     */
    public function deactivate() {
        if(!current_user_can('activate_plugins')) {
            return;
        }
        $status = \Folders\Admin\Settings::get_field_settings( 'advanced_settings', 'remove_folders_when_removed' );
        if($status || $status == 'on') {
            $params = [
                'nonce' => wp_create_nonce('delete-folders-plugin-data-manually'),
            ];
            \Folders\Folders\Actions\FoldersCRUD::delete_all_folder($params);
        }
    }
}
