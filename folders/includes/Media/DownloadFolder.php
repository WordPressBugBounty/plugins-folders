<?php

namespace Folders\Media;

use Folders\Folders\Actions\FoldersCRUD;
use ZipArchive;

/**
 * Downloads the media in a folder as a ZIP archive.
 *
 * The archive is built in batches of 10 files per request so large folders
 * do not time out; the client calls the endpoint again with the next page
 * until all files have been added.
 */
class DownloadFolder
{
    /**
     * Add the next batch of a folder's attachments to the folder's ZIP archive.
     *
     * The archive is stored in `uploads/folders-files/{folder-slug}.zip` and is
     * recreated when page 1 is requested. Only attachments directly in the
     * folder (not in subfolders) are included. Requires the `upload_files`
     * capability.
     *
     * @param array $params {
     *     Request parameters.
     *
     *     @type string     $nonce       Nonce for the `folder_nonce_{post_type}` action.
     *     @type string     $post_type   Post type of the folder.
     *     @type int|string $folder_id   Folder term ID.
     *     @type int|string $total_files Total number of files in the folder, echoed back for progress.
     *     @type int|string $page        Batch number, starting at 1.
     * }
     * @return array|\WP_Error Result array with the ZIP `file_url`, `file_name` and progress
     *                         (`page`, `files`, `scanned`) in `data`, or WP_Error on failure.
     */
    public static function download_folder($params) {
        $nonce       = isset( $params['nonce'] ) ? sanitize_text_field( $params['nonce'] ) : '';
        $post_type   = isset( $params['post_type'] ) ? sanitize_text_field( $params['post_type'] ) : '';
        $folder_id   = isset( $params['folder_id'] ) ? sanitize_text_field( $params['folder_id'] ) : '';
        $total_files = isset( $params['total_files'] ) ? sanitize_text_field( $params['total_files'] ) : 0;
        $page        = isset( $params['page'] ) ? intval(sanitize_text_field( $params['page'] )) : 1;

        if (empty($nonce) || empty($post_type) || empty($folder_id) || empty($total_files)) {
            return new \WP_Error( 'error', esc_html__('Invalid request aq', 'folders'), array( 'status' => 403 ) );
        }

        if ( ! wp_verify_nonce( $nonce, 'folder_nonce_'.$post_type ) ) {
            return new \WP_Error( 'error', esc_html__('Invalid request', 'folders'), array( 'status' => 403 ) );
        }

        if (!current_user_can("upload_files")) {
            return new \WP_Error( 'error', esc_html__('You have not permission to download files', 'folders'), array( 'status' => 403 ) );
        }

        $folder_type = \Folders\Folders\Settings::get_folder_post_type($post_type);

        $term = get_term_by('term_id', $folder_id, $folder_type);

        if (empty($term)) {
            return new \WP_Error( 'error', esc_html__('Error during creating zip file', 'folders'), array( 'status' => 403 ) );
        }

        $per_page = 10;

        if($page <= 0) {
            $page = 1;
        }

        $scannedFiles = $page * $per_page;

        $posts = get_posts(
            [
                'post_type' => 'attachment',
                'numberposts' => $per_page,
                'tax_query' => [
                    [
                        'taxonomy' => $folder_type,
                        'field' => 'term_id',
                        'terms' => $folder_id,
                        'include_children' => false,
                    ],
                ],
                'paged' => $page
            ]
        );


        if (empty($posts)) {
            return new \WP_Error( 'error', esc_html__('Error during creating zip file', 'folders'), array( 'status' => 403 ) );
        }

        $file_array = [];
        foreach ($posts as $post) {
            $file_array[] = get_attached_file($post->ID);
        }

        $zip_file = FoldersCRUD::create_slug_from_string($term->name) . ".zip";

        $folder_path = wp_upload_dir();
        $folderPath = $folder_path['basedir'] . DIRECTORY_SEPARATOR . "folders-files";
        if (!is_dir($folderPath)) {
            mkdir($folderPath);
        }

        $zip_path = $folderPath . DIRECTORY_SEPARATOR . $zip_file;

        ini_set('max_execution_time', 0);

        if ($page == 1 && file_exists($zip_path)) {
            @unlink($zip_path);
        }

        $fileURL = $folder_path['baseurl'] . "/folders-files/" . $zip_file;

        $zip = new ZipArchive();

        if ($zip->open($zip_path, ZipArchive::CREATE) !== true) {
            return new \WP_Error( 'error', esc_html__('Error during creating zip file', 'folders'), array( 'status' => 403 ) );
        }

        foreach ($file_array as $file) {
            if (file_exists($file)) {
                $zip->addFromString(basename($file), file_get_contents($file));
            }
        }

        $zip->close();

        $response = [];
        $response['file_url'] = $fileURL;
        $response['file_name'] = $zip_file;
        $response['page'] = $page;
        $response['files'] = $total_files;
        $response['scanned'] = $scannedFiles;
        $response['folder_id'] = $folder_id;
        $response['nonce'] = $nonce;

        return array(
            'success' => true,
            'data'    => $response,
        );
    }
}