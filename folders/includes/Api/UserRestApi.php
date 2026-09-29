<?php
namespace Folders\Api;

defined( 'ABSPATH' ) || exit;

/**
 * REST API routes for user lookups.
 *
 * Registers the `folders-users/v1` endpoints, restricted to users with the
 * `manage_options` capability.
 */
class UserRestApi {

    /**
     * Register the `rest_api_init` hook.
     */
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register the user routes under `folders-users/v1`.
     *
     * @return void
     */
    public function register_routes() {
        $actions = [
            '/search-users'         => 'search_users',
        ];

        foreach ($actions as $route => $action) {
            register_rest_route( 'folders-users/v1', $route, array(
                'methods'             => 'POST',
                'callback'            => array( $this, $action ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            ) );
        }
    }

    /**
     * Search users for the user role/permission settings.
     *
     * REST callback for `POST /folders-users/v1/search-users`.
     * Delegates to {@see \Folders\Users\FoldersUsers::search_users()}.
     *
     * @param \WP_REST_Request $request Request object; all request parameters are passed through.
     * @return array|\WP_Error Result of the delegated call.
     */
    public function search_users($request)
    {
        $params = $request->get_params();
        return \Folders\Users\FoldersUsers::search_users( $params , 'user_role');
    }
}
