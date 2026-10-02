<?php
namespace Folders\Folders\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Invalidates the cached folder counts when content changes.
 *
 * Deletes the `premio_folders_without_trash` transient whenever a post or
 * attachment is saved, trashed or deleted, and whenever items are added to or
 * removed from a folder or a folder is deleted.
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

        // Folder assignments changed: items moved into or out of a folder, or a folder deleted.
        add_action('set_object_terms', [$this, "object_terms_changed"], 10, 4);
        add_action('deleted_term_relationships', [$this, "term_relationships_deleted"], 10, 3);
        add_action('delete_term', [$this, "term_deleted"], 10, 3);
    }

    /**
     * Check whether a taxonomy is one of the plugin's folder taxonomies.
     *
     * @param string $taxonomy Taxonomy name.
     * @return bool
     */
    private static function is_folder_taxonomy($taxonomy)
    {
        if (!is_string($taxonomy) || $taxonomy === '') {
            return false;
        }
        return $taxonomy === 'folder' || substr($taxonomy, -7) === '_folder';
    }

    /**
     * Clear the folder count cache when an item's folders are set.
     *
     * Hooked to `set_object_terms`.
     *
     * @param int    $object_id Object (post) ID.
     * @param array  $terms     Terms that were set.
     * @param array  $tt_ids    Term taxonomy IDs that were set.
     * @param string $taxonomy  Taxonomy name.
     * @return void
     */
    public function object_terms_changed($object_id, $terms, $tt_ids, $taxonomy)
    {
        if (self::is_folder_taxonomy($taxonomy)) {
            FoldersItems::flush_count_cache();
        }
    }

    /**
     * Clear the folder count cache when an item is removed from a folder.
     *
     * Hooked to `deleted_term_relationships`.
     *
     * @param int    $object_id Object (post) ID.
     * @param array  $tt_ids    Term taxonomy IDs that were removed.
     * @param string $taxonomy  Taxonomy name.
     * @return void
     */
    public function term_relationships_deleted($object_id, $tt_ids, $taxonomy)
    {
        if (self::is_folder_taxonomy($taxonomy)) {
            FoldersItems::flush_count_cache();
        }
    }

    /**
     * Clear the folder count cache when a folder is deleted.
     *
     * Hooked to `delete_term`.
     *
     * @param int    $term     Term ID.
     * @param int    $tt_id    Term taxonomy ID.
     * @param string $taxonomy Taxonomy name.
     * @return void
     */
    public function term_deleted($term, $tt_id, $taxonomy)
    {
        if (self::is_folder_taxonomy($taxonomy)) {
            FoldersItems::flush_count_cache();
        }
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
        FoldersItems::flush_count_cache();
    }

    /**
     * Clear the folder count cache when a post or attachment is added, edited, trashed or deleted.
     *
     * @param int $postID Post ID.
     * @return void
     */
    public function delete_post($postID)
    {
        FoldersItems::flush_count_cache();
    }
}
