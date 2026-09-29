<?php
namespace Folders\Folders;

defined( 'ABSPATH' ) || exit;

/**
 * User access level for folders.
 *
 * In this version every user gets full ("admin") folder access; per-role
 * restrictions such as "view-only" and "no-access" are Pro features.
 */
class UserFolders {

    private static $user_role = null;

    /**
     * Get the current user's folder access level.
     *
     * @return string Always "admin".
     */
    public static function get_user_role() {
        if ( null !== self::$user_role ) {
            return self::$user_role;
        }

        self::$user_role = "admin";
        return self::$user_role;
    }
}
