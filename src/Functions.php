<?php
/**
 * This helper utilities file offers a collection of functions crucial for operational efficiency within a WordPress
 * plugin environment, specifically targeting API communications for license key operations. It includes functions for:
 *
 * - Generating a secure, nonce-protected link for duplicating posts, accessible only to users with appropriate
 * permissions.
 * - Verifying a user's capabilities to edit or delete posts of a particular post type, reinforcing security and proper
 * access control within the plugin.
 *
 * These utilities are essential for maintaining code cleanliness, ensuring data integrity and security, and
 * simplifying common tasks related to post management and capability checking. Through abstracting repetitive tasks
 * into well-defined functions, the plugin achieves greater maintainability and reduced vulnerability to errors and
 * security risks.
 *
 * @package         arraypress/cpt-inline-list-table
 * @copyright       Copyright (c) 2024, ArrayPress Limited
 * @license         GPL2+
 * @version         1.0.0
 * @author          David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\WP\CPT_Inline_List_Table;

// If this file is called directly, abort.
defined( 'ABSPATH' ) || exit;

use function add_query_arg;
use function admin_url;
use function apply_filters;
use function current_user_can;
use function get_post;
use function get_post_type_object;
use function wp_create_nonce;

if ( ! function_exists( 'get_duplicate_post_link' ) ) {
	/**
	 * Generates a duplicate post link for a given post.
	 *
	 * Checks if the current user has the capability to delete the post to ensure they have sufficient permissions to duplicate it.
	 * Returns a nonce-secured URL for duplicating the specified post.
	 *
	 * @param int|WP_Post $post The post ID or post object. Defaults to global post.
	 *
	 * @return string|null The URL to duplicate the post if successful, null otherwise.
	 */
	function get_duplicate_post_link( $post = 0 ): ?string {
		$post = get_post( $post );

		if ( ! $post ) {
			return null;
		}

		$post_type_object = get_post_type_object( $post->post_type );

		if ( ! $post_type_object || ! current_user_can( 'delete_post', $post->ID ) ) {
			return null;
		}

		// Prepare the duplicate link with a security nonce
		$duplicate_link = add_query_arg( array(
			'post'     => $post->ID,
			'action'   => 'duplicate_inline',
			'_wpnonce' => wp_create_nonce( 'duplicate_post_' . $post->ID ),
		), admin_url( 'post.php' ) );

		/**
		 * Filters the URL used for duplicating a post. This allows plugins and themes to modify the duplicate post link.
		 * Useful for adding or removing query args, or changing the base URL, based on specific conditions or requirements.
		 *
		 * @param string $duplicate_link The URL to duplicate the post, including nonce for security.
		 * @param int    $post_id        The ID of the post being duplicated.
		 */
		return apply_filters( 'cpt_inline_list_table_duplicate_post_link', $duplicate_link, $post->ID );
	}
}

if ( ! function_exists( 'check_edit_others_caps' ) ) {
	/**
	 * Checks to see if the current user has the capability to "edit others" for a post type
	 *
	 * @param string $post_type Post type name
	 *
	 * @return bool True or false
	 */
	function check_edit_others_caps( string $post_type ): bool {
		$post_type_object = get_post_type_object( $post_type );
		$edit_others_cap  = empty( $post_type_object ) ? 'edit_others_' . $post_type . 's' : $post_type_object->cap->edit_others_posts;

		/**
		 * Filters the capability check result for editing posts created by other users. This can be used to dynamically
		 * alter permission checks, allowing or restricting user capabilities based on custom logic (e.g., user roles,
		 * specific conditions, or post types).
		 *
		 * @param bool   $can_edit_others True if the current user has the capability to edit others' posts, false otherwise.
		 * @param string $post_type       The post type being checked for the 'edit others' capability.
		 */

		return apply_filters( 'cpt_inline_list_table_edit_others_capability', current_user_can( $edit_others_cap ), $post_type );
	}
}

if ( ! function_exists( 'check_delete_others_caps' ) ) {
	/**
	 * Checks to see if the current user has the capability to "delete others' posts" for a post type
	 *
	 * @param string $post_type Post type name
	 *
	 * @return bool True if the user has the capability to delete others' posts of the specified post type, false otherwise.
	 */
	function check_delete_others_caps( string $post_type ): bool {
		$post_type_object  = get_post_type_object( $post_type );
		$delete_others_cap = empty( $post_type_object ) ? 'delete_others_' . $post_type . 's' : $post_type_object->cap->delete_others_posts;

		/**
		 * Filters the capability check result for deleting posts created by other users. Similar to the edit capability filter,
		 * this allows for custom control over who can delete others' posts within specific contexts or conditions.
		 *
		 * @param bool   $can_delete_others True if the current user has the capability to delete others' posts, false otherwise.
		 * @param string $post_type         The post type being checked for the 'delete others' capability.
		 */

		return apply_filters( 'cpt_inline_list_table_delete_others_capability', current_user_can( $delete_others_cap ), $post_type );
	}
}

if ( ! function_exists( 'is_post_type_sortable' ) ) {
	/**
	 * Checks if a given post type supports sorting based on 'page-attributes' and is not hierarchical.
	 *
	 * This function determines if a post type is considered "sortable" by checking
	 * if it supports 'page-attributes' and ensuring it is not hierarchical. It allows
	 * for overriding the sortable status through a filter hook, offering flexibility
	 * in adjusting the sortability condition for custom post types.
	 *
	 * @param string $post_type The post type to check for sortability. Defaults to 'post'.
	 *
	 * @return bool True if the post type is sortable, false otherwise.
	 */
	function is_post_type_sortable( string $post_type = 'post' ): bool {
		$sortable = ( post_type_supports( $post_type, 'page-attributes' ) && ! is_post_type_hierarchical( $post_type ) );

		/**
		 * Filters the determination of whether a post type is considered sortable. Sortability can be defined by themes
		 * or plugins based on whether a post type supports 'page-attributes' and is not hierarchical. This filter allows
		 * overriding the default sortability condition to accommodate custom logic or requirements for sorting posts.
		 *
		 * @param bool   $sortable  Whether the post type is sortable, determined by support for 'page-attributes' and non-hierarchical structure.
		 * @param string $post_type The post type being evaluated for sortability.
		 */

		return apply_filters( 'cpt_inline_list_table_post_type_sortable', $sortable, $post_type );
	}
}
