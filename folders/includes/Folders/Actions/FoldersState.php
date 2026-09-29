<?php
namespace Folders\Folders\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Saves the expanded/collapsed state of individual folders.
 */
class FoldersState {

    /**
     * Save whether a folder is expanded (open) in the folder tree.
     *
     * Stored as `is_active` in the folder's `folder_info` term meta.
     *
     * @param array $params {
     *     Request parameters.
     *
     *     @type string     $nonce     Nonce for the `folder_nonce_{folder_id}` action.
     *     @type int|string $folder_id Folder term ID.
     *     @type int|string $status    Truthy when the folder is expanded.
     * }
     * @return array|\WP_Error Success array, or WP_Error on invalid request.
     */
    public static function save_folder_state($params)
    {
        $nonce = isset( $params['nonce'] ) ? sanitize_text_field( $params['nonce'] ) : '';
        $folder_id = isset( $params['folder_id'] ) ? sanitize_text_field( $params['folder_id'] ) : '';
        $status = isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : '';

        if(empty($folder_id) || empty($nonce) || !wp_verify_nonce($nonce, 'folder_nonce_' . $folder_id)) {
            return new \WP_Error( 'error', esc_html__('Invalid request', 'folders'), array( 'status' => 403 ) );
        }

        $folder_info = get_term_meta($folder_id, "folder_info", true);
        $status = $status ? 1 : 0;

        if ($folder_info) {
            $folder_info['is_active'] = $status;
            update_term_meta($folder_id, "folder_info", $folder_info);
        } else {
            $folder_info = [];
            $folder_info['is_active'] = $status;
            add_term_meta($folder_id, "folder_info", $folder_info);
        }

        return array(
            'success'       => true,
            'message'       => esc_html__('Folders updated successfully', 'folders'),
            'folder_id'     => $folder_id
        );
    }
}
