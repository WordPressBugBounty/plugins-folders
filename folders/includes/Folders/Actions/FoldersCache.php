<?php
namespace Folders\Folders\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Invalidates the cached folder counts when content changes.
 *
 * Deletes the `premio_folders_without_trash` transient whenever a post or
 * attachment is saved, trashed or deleted.
 */
class FoldersCache {

    /**
     * Register post and attachment change hooks that clear the folder cache.
     */
    public function __construct() {
        add_action('wp_trash_post', [$this, "delete_post"]);
        add_action('before_delete_post', [$this, "delete_post"]);
        add_action('save_post', [$this, "save_post"], 10, 3);
        add_action('add_attachment', [$this, "delete_post"]);
        add_action('edit_attachment', [$this, "delete_post"]);
        add_action('delete_attachment', [$this, "delete_post"]);
    }

    /**
     * Clear the folder count cache when a post is saved.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     * @param bool     $update  Whether this is an update of an existing post.
     * @return void
     */
    public function save_post($post_id, $post, $update)
    {
        delete_transient("premio_folders_without_trash");
    }

    /**
     * Clear the folder count cache when a post or attachment is added, edited, trashed or deleted.
     *
     * @param int $postID Post ID.
     * @return void
     */
    public function delete_post($postID)
    {
        delete_transient("premio_folders_without_trash");
    }
}
