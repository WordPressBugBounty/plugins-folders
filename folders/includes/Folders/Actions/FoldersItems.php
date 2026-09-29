<?php
namespace Folders\Folders\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the items (posts, pages, media) inside folders.
 *
 * Assigns and removes items from folders, supports undoing the last change,
 * sorts the folder list, and calculates the item counts shown next to each
 * folder (excluding trashed items).
 */
class FoldersItems {

    var $transient_data = null;

    /**
     * Register the `get_terms` filter that adds item counts to folder terms.
     */
    public function __construct()
    {
        add_filter('get_terms', [$this, 'get_terms_filter_without_trash'], 10, 3);
    }

    /**
     * Undo the last folder assignment change.
     *
     * Restores the folder terms saved in the `folder_undo_settings` transient by
     * the previous move or remove action. Requires the `edit_posts` capability.
     *
     * @param array $params Request parameters with `nonce` (for `folder_nonce_{post_type}`)
     *                      and `post_type`.
     * @return array|\WP_Error Success array, or WP_Error on invalid request.
     */
    public static function undo_folders_changes($params)
    {
        $nonce = isset( $params['nonce'] ) ? sanitize_text_field( $params['nonce'] ) : '';
        $post_type = isset( $params['post_type'] ) ? sanitize_text_field( $params['post_type'] ) : '';

        if(empty($post_type) || empty($nonce) || !wp_verify_nonce($nonce, 'folder_nonce_'.$post_type)) {
            return new \WP_Error( 'error', esc_html__('Invalid request', 'folders'), array( 'status' => 403 ) );
        }

        if ( !current_user_can( 'edit_posts' ) ) {
            return new \WP_Error( 'error', esc_html__('You have not permission to undo folder changes', 'folders'), array( 'status' => 403 ) );
        }

        $settings = get_transient("folder_undo_settings");
        $post_type = \Folders\Folders\Settings::get_folder_post_type($post_type);
        if (!empty($settings) && is_array($settings)) {
            foreach ($settings as $item) {
                if (!isset($item['post_id']) || !current_user_can('edit_post', absint($item['post_id']))) {
                    continue;
                }
                $terms = get_the_terms($item['post_id'], $post_type);
                if (!empty($terms)) {
                    foreach ($terms as $term) {
                        wp_remove_object_terms($item['post_id'], $term->term_id, $post_type);
                        if (isset($trash_folders[$term->term_taxonomy_id])) {
                            unset($trash_folders[$term->term_taxonomy_id]);
                        }
                    }
                }

                if (!empty($item['terms']) && is_array($item['terms'])) {
                    foreach ($item['terms'] as $term) {
                        wp_set_post_terms($item['post_id'], $term->term_id, $post_type, true);
                        if (isset($trash_folders[$term->term_taxonomy_id])) {
                            unset($trash_folders[$term->term_taxonomy_id]);
                        }
                    }
                }
            }

            delete_transient("premio_folders_without_trash");
        }

        return array(
            'success' => true
        );
    }

    /**
     * Sort the folder list and save the chosen order for the post type.
     *
     * Supported values: "a-z" and "z-a" (by name), "n-o" (newest first) and
     * "o-n" (oldest first). Falls back to the saved order when `sort` is empty.
     *
     * @param array $params Request parameters with `nonce` (for `folder_nonce_{post_type}`),
     *                      `post_type` and `sort`.
     * @return array|\WP_Error Result array with the sorted folders in `data`, or WP_Error on invalid request.
     */
    public static function sort_folder_data($params) {
        $nonce = isset( $params['nonce'] ) ? sanitize_text_field( $params['nonce'] ) : '';
        $post_type = isset( $params['post_type'] ) ? sanitize_text_field( $params['post_type'] ) : '';
        $order_field = isset( $params['sort'] ) ? sanitize_text_field( $params['sort'] ) : '';

        if(empty($order_field)) {
            $order_field = get_option('wcp_custom_sort_'.$post_type, 'a-z');
        }

        if(empty($post_type) || empty($order_field) || empty($nonce) || !wp_verify_nonce($nonce, 'folder_nonce_'.$post_type)) {
            return new \WP_Error( 'error', esc_html__('Invalid request', 'folders'), array( 'status' => 403 ) );
        }

        $folder_type = \Folders\Folders\Settings::get_folder_post_type($post_type);
        update_option("wcp_custom_sort_" . $post_type, $order_field) or add_option("wcp_custom_sort_" . $post_type, $order_field);

        $order_by = "";
        $order = "ASC";

        if ($order_field == "a-z" || $order_field == "z-a") {
            $order_by = 'title';
            if ($order_field == "z-a") {
                $order = "DESC";
            }
        } else if ($order_field == "n-o" || $order_field == "o-n") {
            $order_by = 'ID';
            if ($order_field == "o-n") {
                $order = "ASC";
            } else {
                $order = "DESC";
            }
        }

        if(!empty($order_by) && !empty($order)) {
            $folders = \Folders\Folders\FoldersTree::get_folders($folder_type, $post_type, true, $order_by, $order);
        } else {
            $folders = \Folders\Folders\FoldersTree::get_folders($folder_type, $post_type);
        }

        return array(
            'success' => true,
            'data'    => $folders,
        );
    }

    /**
     * Remove items from a folder after checking whether they belong to other folders.
     *
     * If any item is also in another folder, nothing is removed and the item IDs
     * are returned with `success` false so the UI can ask the user whether to
     * remove them from the current folder only or from all folders. Otherwise
     * the items are removed via {@see remove_post_from_folders()}.
     *
     * @param array $params Request parameters with `nonce` (for `folder_nonce_{post_type}`),
     *                      `post_type`, `folder_id` and `post_ids`.
     * @return array|\WP_Error Result array, or WP_Error on invalid request.
     */
    public static function check_for_other_folders($params) {
        $nonce = isset( $params['nonce'] ) ? sanitize_text_field( $params['nonce'] ) : '';
        $post_type = isset( $params['post_type'] ) ? sanitize_text_field( $params['post_type'] ) : '';
        $folder_id = isset( $params['folder_id'] ) ? sanitize_text_field( $params['folder_id'] ) : '';
        $post_ids  = isset( $params['post_ids'] ) ? map_deep( $params['post_ids'], 'sanitize_text_field' ) : [];

        if(empty($post_type) || empty($folder_id) || empty($post_ids) || empty($nonce) || !wp_verify_nonce($nonce, 'folder_nonce_'.$post_type)) {
            return new \WP_Error( 'error', esc_html__('Invalid request', 'folders'), array( 'status' => 403 ) );
        }

        $folder_type = \Folders\Folders\Settings::get_folder_post_type($post_type);

        foreach ($post_ids as $id) {
            $terms = get_the_terms($id, $folder_type);
            if (!empty($terms) && is_array($terms)) {
                foreach ($terms as $term) {
                    if ($term->term_id != $folder_id) {
                        return array(
                            'success' => false,
                            'data' => [
                                'post_ids' => $post_ids,
                            ]
                        );
                    }
                }
            }
        }

        return self::remove_post_from_folders($params);
    }

    /**
     * Remove items from the current folder or from all folders.
     *
     * The previous folder assignments are saved in the `folder_undo_settings`
     * transient for one day so the change can be undone.
     *
     * @param array $params {
     *     Request parameters.
     *
     *     @type string     $nonce       Nonce for the `folder_nonce_{post_type}` action.
     *     @type string     $post_type   Post type of the items.
     *     @type array      $post_ids    IDs of the items.
     *     @type int|string $folder_id   Optional. Current folder ID.
     *     @type string     $remove_from Optional. "current" to remove only from `folder_id`;
     *                                   otherwise the items are removed from all folders.
     * }
     * @return array|\WP_Error Success array, or WP_Error on invalid request.
     */
    public static function remove_post_from_folders($params)
    {
        $nonce = isset( $params['nonce'] ) ? sanitize_text_field( $params['nonce'] ) : '';
        $post_type = isset( $params['post_type'] ) ? sanitize_text_field( $params['post_type'] ) : '';
        $folder_id = isset( $params['folder_id'] ) ? sanitize_text_field( $params['folder_id'] ) : '';
        $post_ids  = isset( $params['post_ids'] ) ? map_deep( $params['post_ids'], 'sanitize_text_field' ) : [];

        if(empty($post_type) || empty($post_ids) || empty($nonce) || !wp_verify_nonce($nonce, 'folder_nonce_'.$post_type)) {
            return new \WP_Error( 'error', esc_html__('Invalid request', 'folders'), array( 'status' => 403 ) );
        }

        $folder_type = \Folders\Folders\Settings::get_folder_post_type($post_type);
        $remove_from  = isset( $params['remove_from'] ) ? sanitize_text_field( $params['remove_from'] ) : '';

        $folderUndoSettings = [];
        foreach ($post_ids as $id) {
            if (!empty($id) && is_numeric($id) && $id > 0 && current_user_can('edit_post', absint($id))) {
                $terms = get_the_terms($id, $folder_type);
                $post_terms = [
                    'post_id' => $id,
                    'terms' => $terms,
                ];
                $folderUndoSettings[] = $post_terms;
                if ($remove_from == "current" && !empty($folder_id)) {
                    wp_remove_object_terms($id, intval($folder_id), $folder_type);
                } else {
                    wp_delete_object_term_relationships($id, $folder_type);
                }
            }
        }

        delete_transient("folder_undo_settings");
        delete_transient("premio_folders_without_trash");
        set_transient("folder_undo_settings", $folderUndoSettings, DAY_IN_SECONDS);
        return array(
            'success'       => true
        );
    }

    /**
     * Remember the last opened folder for a post type.
     *
     * Stored in the `last_folder_status_for{post_type}` option.
     *
     * @param array $params Request parameters with `nonce` (for `folder_nonce_{post_type}`),
     *                      `post_type` and `folder_id`.
     * @return array|\WP_Error Success array, or WP_Error on invalid request.
     */
    public static function save_last_folder_state($params)
    {
        $nonce = isset( $params['nonce'] ) ? sanitize_text_field( $params['nonce'] ) : '';
        $post_type = isset( $params['post_type'] ) ? sanitize_text_field( $params['post_type'] ) : '';

        if(empty($post_type) || empty($nonce) || !wp_verify_nonce($nonce, 'folder_nonce_'.$post_type)) {
            return new \WP_Error( 'error', esc_html__('Invalid request', 'folders'), array( 'status' => 403 ) );
        }

        $folder_id = isset( $params['folder_id'] ) ? sanitize_text_field( $params['folder_id'] ) : '';
        if (!empty($folder_id)) {
            delete_option("last_folder_status_for" . $post_type);
            add_option("last_folder_status_for" . $post_type, $folder_id);
        } else if(!empty($folder_id)) {
            delete_option("last_folder_status_for" . $post_type);
        }

        return array(
            'success'       => true
        );
    }
    /**
     * Add a `trash_count` property to folder terms with the number of items that
     * are not trashed or auto-drafts.
     *
     * Counts are cached per term taxonomy ID in the `premio_folders_without_trash`
     * transient for three days. The `premio_folder_item_in_taxonomy` filter can
     * supply the count instead (used by translation integrations).
     *
     * @param \WP_Term[]|mixed $terms      Terms returned by get_terms().
     * @param string[]         $taxonomies Queried taxonomies.
     * @param array            $args       get_terms() query arguments.
     * @return \WP_Term[]|mixed Terms, with `trash_count` added for folder taxonomies.
     */
    public function get_terms_filter_without_trash($terms, $taxonomies, $args)
    {
        delete_transient("premio_folders_without_trash");
        $isForFolders = 0;
        if (!empty($taxonomies) && is_array($taxonomies) && count($taxonomies)) {
            foreach ($taxonomies as $taxonomy) {
                if (in_array($taxonomy, array("media_folder", "folder", "post_folder"))) {
                    $isForFolders = 1;
                } else {
                    $folder = substr($taxonomy, -7);
                    if ($folder == "_folder") {
                        $isForFolders = 1;
                    }
                }
            }
        }

        if ($isForFolders) {
            global $wpdb, $polylang;

            // Check if Polylang is active or not.
            $polylang_is_active = function_exists("pll_get_post_translations") && function_exists("pll_is_translated_post_type");

            if (!is_array($terms) && count($terms) < 1) {
                return $terms;
            }

            if($this->transient_data === null) {
                $this->transient_data = get_transient("premio_folders_without_trash");
            }

            $trash_folders = $initial_trash_folders = $this->transient_data;

            if ($trash_folders === false) {
                $trash_folders = array();
                $initial_trash_folders = array();
            }

            $post_table = $wpdb->prefix . "posts";
            $term_table = $wpdb->prefix . "term_relationships";
            $options = get_option('folders_settings');
            $option_array = array();
            if (!empty($options)) {
                foreach ($options as $option) {
                    $option_array[] = \Folders\Folders\Settings::get_folder_post_type($option);
                }
            }
            foreach ($terms as $key => $term) {
                if (isset($term->term_id) && isset($term->taxonomy) && !empty($term->taxonomy) && in_array($term->taxonomy, $option_array)) {
                    $trash_count = null;
                    if (has_filter("premio_folder_item_in_taxonomy")) {
                        $post_type = "";
                        $taxonomy = $term->taxonomy;

                        if ($taxonomy == "post_folder") {
                            $post_type = "post";
                        } else if ($taxonomy == "folder") {
                            $post_type = "page";
                        } else if ($taxonomy == "media_folder") {
                            $post_type = "attachment";
                        } else {
                            $post_type = trim($taxonomy, "'_folder'");
                        }
                        $arg = array(
                            'post_type' => $post_type,
                            'taxonomy' => $taxonomy,
                        );
                        $trash_count = apply_filters("premio_folder_item_in_taxonomy", $term->term_id, $arg);
                    } else {
                        if ($trash_count == null && isset($trash_folders[$term->term_taxonomy_id]) && !$polylang_is_active) {
                            $trash_count = $trash_folders[$term->term_taxonomy_id];
                        } else if ($trash_count == null) {
                            if (isset($trash_folders[$term->term_taxonomy_id])) {
                                $trash_count = $trash_folders[$term->term_taxonomy_id];
                            } else {
                                if ($trash_count === null) {
                                    $query = "SELECT COUNT(DISTINCT(p.ID)) 
                                    FROM {$post_table} p
                                        JOIN {$term_table} rl ON p.ID = rl.object_id
                                        WHERE rl.term_taxonomy_id = '{$term->term_taxonomy_id}'
                                          AND p.post_status != 'trash' 
                                          AND p.post_status != 'auto-draft' 
                                        LIMIT 1";
                                    $result = $wpdb->get_var($query);
                                    if (intval($result) > 0) {
                                        $trash_count = intval($result);
                                    } else {
                                        $trash_count = 0;
                                    }
                                }
                            }
                        }
                    }
                    if ($trash_count === null) {
                        $trash_count = 0;
                    }
                    $terms[$key]->trash_count = $trash_count;
                    $trash_folders[$term->term_taxonomy_id] = $trash_count;
                }
            }

            if (!empty($terms) && $initial_trash_folders != $trash_folders) {
                delete_transient("premio_folders_without_trash");
                set_transient("premio_folders_without_trash", $trash_folders, 3 * DAY_IN_SECONDS);
                $this->transient_data = $trash_folders;
            }
        }
        return $terms;
    }//end get_terms_filter_without_trash()

    /**
     * Get the folder list, folder tree and item counts for a post type.
     *
     * @param array $params Request parameters with `nonce` (for `folder_nonce_{post_type}`)
     *                      and `post_type`.
     * @return array|\WP_Error Result array with `folders`, `folders_tree`, `total_items`
     *                         and `empty_items` in `data`, or WP_Error on invalid request.
     */
    public static function get_folder_items($params)
    {
        $nonce = isset( $params['nonce'] ) ? sanitize_text_field( $params['nonce'] ) : '';
        $post_type = isset( $params['post_type'] ) ? sanitize_text_field( $params['post_type'] ) : '';

        if(empty($post_type) || empty($nonce) || !wp_verify_nonce($nonce, 'folder_nonce_'.$post_type)) {
            return new \WP_Error( 'error', esc_html__('Invalid request', 'folders'), array( 'status' => 403 ) );
        }

        $folder_type = \Folders\Folders\Settings::get_folder_post_type($post_type);
        $folders = \Folders\Folders\FoldersTree::get_folders($folder_type, $post_type);
        $folders_tree = \Folders\Folders\FoldersTree::arrange_folders_to_tree( $folders, '- ' );
        $total_items = self::total_items($post_type);
        $empty_items = self::total_unassigned_items($post_type);

        return array(
            'success'       => true,
            'message'       => esc_html__('Folders updated successfully', 'folders'),
            'data'     => [
                'folders' => $folders,
                'folders_tree' => $folders_tree,
                'total_items' => $total_items,
                'empty_items' => $empty_items,
            ]
        );
    }
    /**
     * Count the items of a post type that are not in any folder.
     *
     * Counts published, scheduled, draft, private and pending posts (or inherit
     * and private attachments). The `premio_folder_un_categorized_items` filter
     * can supply the count instead.
     *
     * @param string $post_type Post type.
     * @return int|string Number of unassigned items.
     */
    public static function total_unassigned_items($post_type)
    {
        global $wpdb;

        $post_table = $wpdb->prefix . "posts";
        $term_table = $wpdb->prefix . "term_relationships";
        $term_taxonomy_table = $wpdb->prefix . "term_taxonomy";
        $term_meta = $wpdb->prefix . "termmeta";
        $taxonomy = \Folders\Folders\Settings::get_folder_post_type($post_type);;
        $tlrcds = null;
        if (has_filter("premio_folder_un_categorized_items")) {
            $tlrcds = apply_filters("premio_folder_un_categorized_items", $post_type, $taxonomy);
        }
        if ($tlrcds === null) {
            $user_filter = false;

            if (!$user_filter) {
                if ($post_type != "attachment") {
                    $query = "SELECT COUNT(DISTINCT({$post_table}.ID)) AS total_records FROM {$post_table} WHERE 1=1 AND (
                                NOT EXISTS (
                                    SELECT 1
                                    FROM {$term_table}
                                    INNER JOIN {$term_taxonomy_table}
                                    ON {$term_taxonomy_table}.term_taxonomy_id = {$term_table}.term_taxonomy_id
                                    WHERE {$term_taxonomy_table}.taxonomy = '%s'
                                    AND {$term_table}.object_id = {$post_table}.ID
                                )
                             ) AND {$post_table}.post_type = '%s' AND (({$post_table}.post_status = 'publish' OR {$post_table}.post_status = 'future' OR {$post_table}.post_status = 'draft' OR {$post_table}.post_status = 'private' OR {$post_table}.post_status = 'pending'))";

                    $query = $wpdb->prepare($query, $taxonomy, $post_type);
                } else {
                    $select = "SELECT COUNT(DISTINCT(P.ID)) AS total_records FROM {$post_table} AS P";
                    $where = ["post_type = 'attachment' "];
                    $where[] = "(post_status = 'inherit' OR post_status = 'private')";
                    $where[] = "(NOT EXISTS (
                                        SELECT 1
                                        FROM {$term_table}
                                        INNER JOIN {$term_taxonomy_table}
                                        ON {$term_taxonomy_table}.term_taxonomy_id = {$term_table}.term_taxonomy_id
                                        WHERE {$term_taxonomy_table}.taxonomy = '%s'
                                        AND {$term_table}.object_id = P.ID
                                    )
                                )";

                    $join = apply_filters('folders_count_join_query', "");
                    $where = apply_filters('folders_count_where_query', $where);

                    $query = $select . $join . " WHERE " . implode(' AND ', $where);

                    $query = $wpdb->prepare($query, $taxonomy);
                }
            } else {
                if ($post_type != "attachment") {
                    $query = "SELECT COUNT(DISTINCT({$post_table}.ID)) AS total_records FROM {$post_table} WHERE 1=1 AND (
                                NOT EXISTS (
                                    SELECT 1
                                    FROM {$term_table}
                                    INNER JOIN {$term_taxonomy_table}
                                    ON {$term_taxonomy_table}.term_taxonomy_id = {$term_table}.term_taxonomy_id
                                    INNER JOIN {$term_meta}
                                    ON {$term_meta}.term_id = {$term_table}.term_taxonomy_id AND {$term_meta}.meta_key = 'created_by' AND {$term_meta}.meta_value = {$user_id}
                                    WHERE {$term_taxonomy_table}.taxonomy = '%s'
                                    AND {$term_table}.object_id = {$post_table}.ID
                                )
                             ) AND {$post_table}.post_type = '%s' AND (({$post_table}.post_status = 'publish' OR {$post_table}.post_status = 'future' OR {$post_table}.post_status = 'draft' OR {$post_table}.post_status = 'private' OR {$post_table}.post_status = 'pending'))";

                    $query = $wpdb->prepare($query, $taxonomy, $post_type);
                } else {

                    $select = "SELECT COUNT(DISTINCT(P.ID)) AS total_records FROM {$post_table} AS P";
                    $where = ["post_type = 'attachment' "];
                    $where[] = "(post_status = 'inherit' OR post_status = 'private')";
                    $where[] = "(
                                NOT EXISTS (
                                        SELECT 1
                                        FROM {$term_table}
                                        INNER JOIN {$term_taxonomy_table}
                                        ON {$term_taxonomy_table}.term_taxonomy_id = {$term_table}.term_taxonomy_id
                                        INNER JOIN {$term_meta}
                                        ON {$term_meta}.term_id = {$term_table}.term_taxonomy_id AND {$term_meta}.meta_key = 'created_by' AND {$term_meta}.meta_value = {$user_id}
                                        WHERE {$term_taxonomy_table}.taxonomy = '%s'
                                        AND {$term_table}.object_id = P.ID
                                    )
                                )";

                    $join = apply_filters('folders_count_join_query', "");
                    $where = apply_filters('folders_count_where_query', $where);

                    $query = $select . $join . " WHERE " . implode(' AND ', $where);

                    $query = $wpdb->prepare($query, $taxonomy);
                }
            }


            $tlrcds = $wpdb->get_var($query);
        }

        if (!empty($tlrcds)) {
            return $tlrcds;
        } else {
            return 0;
        }
    }

    /**
     * Count all items of a post type shown in the "All" entry of the folder sidebar.
     *
     * Counts published, scheduled, draft, private and pending posts (or inherit
     * and private attachments). The `premio_folder_all_categorized_items` filter
     * can supply the count instead.
     *
     * @param string $post_type Post type; defaults to the current screen's post type when empty.
     * @return int|string Number of items.
     */
    public static function total_items($post_type) {
        global $typenow;
        if (empty($post_type)) {
            $post_type = $typenow;
        }
        $item_count = null;
        if (has_filter("premio_folder_all_categorized_items")) {
            $item_count = apply_filters("premio_folder_all_categorized_items", $post_type);
        }
        if ($item_count === null) {
            if ($post_type == "attachment") {
                global $wpdb;

                $select = "SELECT COUNT(ID) FROM " . $wpdb->posts . " as P ";

                $where = ["post_type = 'attachment' "];
                $where[] = "(post_status = 'inherit' OR post_status = 'private')";

                $join = apply_filters('folders_count_join_query', "");
                $where = apply_filters('folders_count_where_query', $where);

                $query = $select . $join . " WHERE " . implode(' AND ', $where);

                $item_count = $wpdb->get_var($query);

            } else {
                $count_posts = wp_count_posts($post_type);
                $item_count = (isset($count_posts->publish) ? $count_posts->publish : 0) + 
                              (isset($count_posts->draft) ? $count_posts->draft : 0) + 
                              (isset($count_posts->future) ? $count_posts->future : 0) + 
                              (isset($count_posts->private) ? $count_posts->private : 0) + 
                              (isset($count_posts->pending) ? $count_posts->pending : 0);
            }
        }
        return $item_count;
    }
    /**
     * Move items into a folder (drag and drop from the list screen).
     *
     * Items are added to the target folder and removed from the folder currently
     * being viewed (`selected_folder_id`). The previous assignments are saved for
     * undo. Requires `edit_pages` for pages or `edit_posts` otherwise.
     *
     * @param array $params {
     *     Request parameters.
     *
     *     @type string     $nonce              Nonce for the `folder_nonce_{folder_id}` action.
     *     @type int|string $folder_id          Target folder ID.
     *     @type string     $post_type          Post type of the items.
     *     @type array      $post_ids           IDs of the items to move.
     *     @type int|string $selected_folder_id Optional. ID or slug of the folder being viewed.
     * }
     * @return array|\WP_Error Success array, or WP_Error on invalid request.
     */
    public static function save_folder_items($params)
    {
        $nonce = isset( $params['nonce'] ) ? sanitize_text_field( $params['nonce'] ) : '';
        $folder_id = isset( $params['folder_id'] ) ? sanitize_text_field( $params['folder_id'] ) : '';
        $parent_id = isset( $params['parent_id'] ) ? sanitize_text_field( $params['parent_id'] ) : '';
        $post_type = isset( $params['post_type'] ) ? sanitize_text_field( $params['post_type'] ) : '';
        $selected_folder_id = isset( $params['selected_folder_id'] ) ? sanitize_text_field( $params['selected_folder_id'] ) : '';
        $post_ids  = isset( $params['post_ids'] ) ? map_deep( $params['post_ids'], 'sanitize_text_field' ) : [];

        if(empty($folder_id) || empty($post_type) || empty($post_ids) || empty($nonce) || !wp_verify_nonce($nonce, 'folder_nonce_' . $folder_id)) {
            return new \WP_Error( 'error', esc_html__('Invalid request', 'folders'), array( 'status' => 403 ) );
        } else if ($post_type == "page" && !current_user_can("edit_pages")) {
            return new \WP_Error( 'error', esc_html__('Invalid request', 'folders'), array( 'status' => 403 ) );
        } else if ($post_type != "page" && !current_user_can("edit_posts")) {
            return new \WP_Error( 'error', esc_html__('Invalid request', 'folders'), array( 'status' => 403 ) );
        }

        $folder_type = \Folders\Folders\Settings::get_folder_post_type($post_type);

        $folder_title = '';
        $term = get_term($folder_id);
        if ($term) {
            if ($post_type == "attachment" || $folder_type == "media") {
                $url = admin_url("upload.php?post_type=attachment&media_folder=" . $term->slug);
            } else if ($folder_type == "plugin") {
                $url = admin_url("plugins.php?folders4plugins_folder=" . $term->slug);
            } else {
                $url = admin_url("edit.php?post_type=" . $post_type . "&" . $folder_type . "=" . $term->slug);
            }
            $folder_title = "<a href='{$url}'>{$term->title}</a>";
        }

        $folderUndoSettings = [];
        foreach ($post_ids as $post_id) {
            if (!is_numeric($post_id) || $post_id <= 0 || !current_user_can('edit_post', absint($post_id))) {
                continue;
            }
            $terms = get_the_terms($post_id, $folder_type);
            $post_terms = [
                'post_id' => $post_id,
                'terms' => $terms,
            ];
            $folderUndoSettings[] = $post_terms;
            if (!empty($terms)) {
                foreach ($terms as $term) {
                    if (!empty($selected_folder_id) && isset($term->term_id) && ($term->term_id == $selected_folder_id || $term->slug == $selected_folder_id)) {
                        wp_remove_object_terms($post_id, $term->term_id, $folder_type);
                    }
                }
            }

            wp_set_post_terms($post_id, $folder_id, $folder_type, true);
        }

        delete_transient("folder_undo_settings");
        set_transient("folder_undo_settings", $folderUndoSettings, DAY_IN_SECONDS);

        if(!get_option("show_folder_upgrade_popup")) {
            add_option("show_folder_upgrade_popup", "hide");
        }

        return array(
            'success'       => true,
            'message'       => esc_html__('Folders updated successfully', 'folders'),
            'folder_id'     => $folder_id
        );
    }

    /**
     * Move the selected items to a folder from the list screen's bulk actions.
     *
     * A `folder_id` of -1 removes the items from all folders. The previous
     * assignments are saved for undo. Requires `edit_pages` for pages or
     * `edit_posts` otherwise.
     *
     * @param array $params {
     *     Request parameters.
     *
     *     @type string     $nonce     Nonce for the `premio_folders_bulk_action_{post_type}` action.
     *     @type string     $post_type Post type of the items.
     *     @type int|string $folder_id Target folder ID, or -1 to unassign.
     *     @type string     $post_ids  Comma-separated item IDs.
     * }
     * @return array|\WP_Error Success array, or WP_Error on invalid request.
     */
    public static function bulk_folder_action($params)
    {
        $nonce = isset( $params['nonce'] ) ? sanitize_text_field( $params['nonce'] ) : '';
        $post_type = isset( $params['post_type'] ) ? sanitize_text_field( $params['post_type'] ) : '';
        $folder_id = isset( $params['folder_id'] ) ? sanitize_text_field( $params['folder_id'] ) : '';
        $post_ids  = isset( $params['post_ids'] ) ? sanitize_text_field( $params['post_ids'] ) : '';

        if(empty($folder_id) || empty($post_type) || empty($post_ids) || empty($nonce) || !wp_verify_nonce($nonce, 'premio_folders_bulk_action_' . $post_type)) {
            return new \WP_Error( 'error', esc_html__('Invalid request', 'folders'), array( 'status' => 403 ) );
        } else if ($post_type == "page" && !current_user_can("edit_pages")) {
            return new \WP_Error( 'error', esc_html__('Invalid request', 'folders'), array( 'status' => 403 ) );
        } else if ($post_type != "page" && !current_user_can("edit_posts")) {
            return new \WP_Error( 'error', esc_html__('Invalid request', 'folders'), array( 'status' => 403 ) );
        }

        $folder_type = \Folders\Folders\Settings::get_folder_post_type($post_type);

        $folder_title = '';
        $term = get_term($folder_id);
        if ($term) {
            if ($post_type == "attachment" || $folder_type == "media") {
                $url = admin_url("upload.php?post_type=attachment&media_folder=" . $term->slug);
            } else if ($folder_type == "plugin") {
                $url = admin_url("plugins.php?folders4plugins_folder=" . $term->slug);
            } else {
                $url = admin_url("edit.php?post_type=" . $post_type . "&" . $folder_type . "=" . $term->slug);
            }
            $folder_title = "<a href='{$url}'>{$term->title}</a>";
        }

        $post_ids = explode(",", $post_ids);
        $folderUndoSettings = [];
        foreach ($post_ids as $post_id) {
            if (!is_numeric($post_id) || $post_id <= 0 || !current_user_can('edit_post', absint($post_id))) {
                continue;
            }
            $terms = get_the_terms($post_id, $folder_type);
            $post_terms = [
                'post_id' => $post_id,
                'terms' => $terms,
            ];
            $folderUndoSettings[] = $post_terms;
            if (!empty($terms)) {
                foreach ($terms as $term) {
                    if ($folder_id == -1) {
                        wp_remove_object_terms($post_id, $term->term_id, $folder_type);
                    }
                    else if (!empty($selected_folder_id) && isset($term->term_id) && ($term->term_id == $selected_folder_id || $term->slug == $selected_folder_id)) {
                        wp_remove_object_terms($post_id, $term->term_id, $folder_type);
                    }
                }
            }

            if($folder_id != -1) {
                wp_set_post_terms($post_id, $folder_id, $folder_type, true);
            }
        }

        delete_transient("folder_undo_settings");
        set_transient("folder_undo_settings", $folderUndoSettings, DAY_IN_SECONDS);

        return array(
            'success'       => true,
            'message'       => esc_html__('Folders updated successfully', 'folders'),
            'folder_id'     => $folder_id
        );
    }
}
