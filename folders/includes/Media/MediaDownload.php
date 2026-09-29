<?php
namespace Folders\Media;

use ZipArchive;

defined( 'ABSPATH' ) || exit;

/**
 * Downloads selected media files as a ZIP archive.
 *
 * Used by the "Download" bulk action in the Media Library. Archives are
 * written to `uploads/folders-temp/` and old archives are cleaned up
 * before each new one is created.
 */
class MediaDownload {

    private static $temp_folders = 'folders-temp';


    /**
     * Create a ZIP archive of the selected attachments.
     *
     * Requires the `upload_files` capability.
     *
     * @param array $params {
     *     Request parameters.
     *
     *     @type string $nonce          Nonce for the `folder_nonce_{post_type}` action.
     *     @type string $post_type      Must be "attachment".
     *     @type array  $attachment_ids IDs of the attachments to include.
     * }
     * @return array Result array with `success`, and `download_url` and `file_name` in `data`
     *               on success or a `message` on failure.
     */
    public static function download_media_items($params) {

        $nonce       = isset( $params['nonce'] ) ? sanitize_text_field( $params['nonce'] ) : '';
        $type        = isset( $params['post_type'] ) ? sanitize_text_field( $params['post_type'] ) : '';
        $media_ids   = isset( $params['attachment_ids'] ) ? array_map('sanitize_text_field', (array)$params['attachment_ids']) : [];

        if (empty($nonce) || empty($type) || empty($media_ids)) {
            return array('success' => false, 'message' => esc_html__('Invalid request', 'folders'));
        }

        if ( ! wp_verify_nonce( $nonce, 'folder_nonce_'.$type ) ) {
            return array('success' => false, 'message' => esc_html__('Invalid request', 'folders'));
        }

        if (!current_user_can("upload_files")) {
            return array('success' => false, 'message' => esc_html__('You have not permission to download files', 'folders'));
        }

        if ($type !== 'attachment') {
            return array('success' => false, 'message' => esc_html__('Invalid request', 'folders'));
        }

        $zip = new ZipArchive();
        $upload_dir = wp_upload_dir();
        $temp_dir = trailingslashit($upload_dir['basedir']) . self::$temp_folders;
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        // Clean up old zip files before creating new one
        self::cleanup_old_files($temp_dir);

        // Generate unique filename
        $zip_name = self::generate_unique_filename();
        $zip_path = $temp_dir . '/' . $zip_name;

        if ($zip->open($zip_path, ZipArchive::CREATE) !== true) {
            return array('success' => false, 'message' => esc_html__('Error during downloading file', 'folders'));
        }

        foreach ($media_ids as $id) {
            // ID is already sanitized as integer on line 132
            $file = get_attached_file($id);
            if (file_exists($file)) {
                $zip->addFile($file, basename($file));
            }
        }

        $zip->close();

        $download_url = trailingslashit($upload_dir['baseurl']) . self::$temp_folders . '/' . $zip_name;
        return array('success' => true, 'data' => array('download_url' => $download_url, 'file_name' => $zip_name));
    }

    /**
     * Generate a unique file name for a download archive.
     *
     * @return string File name like `download_{timestamp}_{random}.zip`.
     */
    private static function generate_unique_filename() {
        return 'download_' . time() . '_' . wp_generate_password(8, false) . '.zip';
    }

    /**
     * Delete old ZIP archives from the temporary download folder.
     *
     * Without `$exclude_file`, all archives are deleted. With it, that file is
     * kept and only archives older than one hour are deleted.
     *
     * @param string $temp_dir     Absolute path of the temporary folder.
     * @param string $exclude_file Optional. File name to keep.
     * @return void
     */
    private static function cleanup_old_files($temp_dir, $exclude_file = '') {
        if (!file_exists($temp_dir)) {
            return;
        }

        $files = glob($temp_dir . '/*.zip');
        foreach ($files as $file) {
            if (is_file($file)) {
                // Skip the file we want to exclude (if any)
                if ($exclude_file && basename($file) === $exclude_file) {
                    continue;
                }
                // Delete files older than 1 hour or all files if no exclusion
                if (!$exclude_file || (time() - filemtime($file)) > 3600) {
                    @unlink($file);
                }
            }
        }
    }

}
