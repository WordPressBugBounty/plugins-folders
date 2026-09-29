<?php
namespace Folders\Folders;

defined( 'ABSPATH' ) || exit;

/**
 * Opens the remembered or default folder when a list screen loads.
 *
 * When a post type's list screen (or the Media Library) is opened without
 * any filters, redirects to the last opened folder, or else to the folder
 * set as default for that post type.
 */
class DefaultFolder {

    /**
     * Register the `pre_get_posts` hook.
     */
    public function __construct() {
        add_filter('pre_get_posts', [$this, 'check_for_default_folders']);
    }

    /**
     * Redirect an unfiltered list screen to the last opened or default folder.
     *
     * Runs only on edit/upload screens of folder-enabled post types, outside
     * AJAX, with no `post_status` filter and no other query arguments. The last
     * opened folder (the `last_folder_status_for{post_type}` option) takes
     * priority and is cleared once used; otherwise the folder from the
     * `default_folders` option is opened. The redirect is done with inline
     * JavaScript followed by `exit`.
     *
     * @return void
     */
    public function check_for_default_folders()
    {
        global $typenow, $current_screen;
        $isAjax = (defined('DOING_AJAX') && DOING_AJAX) ? 1 : 0;
        $options = get_option('folders_settings');
        $options = (empty($options) || !is_array($options)) ? [] : $options;
        $post_status = filter_input(INPUT_GET, 'post_status');
        $last_status = get_option("last_folder_status_for" . $typenow);
        if (empty($post_status) && !$isAjax && (in_array($typenow, $options) || !empty($last_status)) && (isset($current_screen->base) && ($current_screen->base == "edit" || ($current_screen->base == "upload")))) {
            $requests = filter_input_array(INPUT_GET);
            $requests = empty($requests) || !is_array($requests) ? [] : $requests;

            if ($typenow == "attachment") {
                if (count($requests) > 0) {
                    return;
                }
            } else if ($typenow == "post") {
                if (count($requests) > 0) {
                    return;
                }
            } else {
                if (count($requests) > 1) {
                    return;
                }
            }

            if (!empty($last_status)) {
                $status = 1;
                if ($last_status != "-1" && $last_status != "all") {
                    $type = \Folders\Folders\Settings::get_folder_post_type($typenow);
                    $term = get_term_by('slug', $last_status, $type);
                    if (empty($term) || !is_object($term)) {
                        $status = 0;
                    }
                }

                delete_option("last_folder_status_for" . $typenow);
                if ($last_status == "all") {
                    $last_status = "";
                }

                if ($status) {
                    if ($typenow == "attachment") {
                        if (!isset($_REQUEST['media_folder'])) {
                            ?>
                            <script>
                                window.location = '<?php echo esc_url(admin_url()) . "upload.php?post_type=attachment&media_folder=" . esc_attr($last_status) ?>';
                            </script>
                            <?php
                            exit;
                        }
                    } else {
                        $post_type = \Folders\Folders\Settings::get_folder_post_type($typenow);
                        if (!isset($_REQUEST[$post_type])) {
                            ?>
                            <script>
                                window.location = '<?php echo esc_url(admin_url()) . "edit.php?post_type=" . esc_attr($typenow) . "&" . esc_attr($post_type) . "=" . esc_attr($last_status) ?>';
                            </script>
                            <?php
                            exit;
                        }
                    }//end if
                }//end if
            }//end if

            $default_folders = get_option('default_folders');
            $default_folders = (empty($default_folders) || !is_array($default_folders)) ? [] : $default_folders;

            $status = 1;
            if (isset($default_folders[$typenow]) && !empty($default_folders[$typenow])) {
                $type = \Folders\Folders\Settings::get_folder_post_type($typenow);
                if ($default_folders[$typenow] != -1) {
                    $term = get_term_by('slug', $default_folders[$typenow], $type);
                    if (empty($term) || !is_object($term)) {
                        $status = 0;
                    }
                }
            } else {
                $status = 0;
            }

            if ($status) {
                if ($typenow == "attachment") {
                    if (!isset($_REQUEST['media_folder'])) {
                        if (isset($default_folders[$typenow]) && !empty($default_folders[$typenow])) {
                            ?>
                            <script>
                                window.location = '<?php echo esc_url(admin_url()) . "upload.php?post_type=attachment&media_folder=" . esc_attr($default_folders[$typenow]) ?>';
                            </script>
                            <?php
                            exit;
                        }
                    }
                } else {
                    $search = filter_input(INPUT_GET, "s");
                    if (!empty($search)) {
                        $search = esc_attr($search);
                    } else {
                        $search = "";
                    }

                    $post_type = \Folders\Folders\Settings::get_folder_post_type($typenow);
                    if (!isset($_REQUEST[$post_type])) {
                        if (isset($default_folders[$typenow]) && !empty($default_folders[$typenow])) {
                            ?>
                            <script>
                                window.location = '<?php echo esc_url(admin_url()) . "edit.php?post_type=" . esc_attr($typenow) . "&" . esc_attr($post_type) . "=" . esc_attr($default_folders[$typenow]) . "&s=" . esc_attr($search) ?>';
                            </script>
                            <?php
                            exit;
                        }
                    }
                }//end if
            }//end if
        }//end if

    }//end check_for_default_folders()

}
