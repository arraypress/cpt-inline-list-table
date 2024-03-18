<?php
/**
 * This file introduces a powerful and versatile helper function designed to streamline the registration and management
 * of custom post types within the WordPress admin dashboard. It serves as a foundational tool for creating dynamic and
 * interactive list tables, enhancing user interaction, and ensuring data management is both efficient and
 * user-friendly.
 *
 * The primary focus of this function is to:
 * - Simplify the process of registering custom post types with customizable list tables, providing a clear and concise
 * interface for data display and management.
 * - Offer extensive configuration options for columns, enabling custom callback functions for enhanced display
 * flexibility and interactivity.
 * - Ensure seamless integration within the WordPress admin by supporting script and style enqueuing on specified admin
 * screens, thereby maintaining consistency and improving usability.
 * - Facilitate the organization and navigation within the admin dashboard through menu and submenu highlighting based
 * on active post types.
 * - Enable developers to specify pagination preferences, tailoring the user experience to the needs of the project.
 * - Provide robust error handling capabilities to ensure reliability and ease of debugging during the setup process.
 *
 * By providing a structured approach to custom post type management, this helper function aims to empower developers
 * with the tools needed for building sophisticated admin interfaces, improving workflows, and delivering exceptional
 * user experiences within the WordPress ecosystem.
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

use Exception;

if ( ! function_exists( 'register_inline_table' ) ) {
	/**
	 * Initializes and registers an inline list table for managing and displaying a custom post type within the WordPress admin.
	 * This function configures the custom post type list table with specified columns, admin URLs, menu highlighting, and other settings.
	 *
	 * @param string        $post_type         The identifier for the custom post type.
	 * @param array         $column_callbacks  An associative array mapping column identifiers to callback functions for rendering column content.
	 * @param string        $hook_name         Specifies the WordPress action hook to which the custom post type registration is attached.
	 * @param int           $hook_priority     Determines the order in which the custom post type is registered on the specified hook. Defaults to 10.
	 * @param string        $admin_url         URL to the admin page for managing this post type, used for redirects within the admin.
	 * @param array         $enqueue_screens   A list of admin screen IDs where the custom post type's scripts and styles should be enqueued.
	 * @param string        $parent_file_slug  Slug for the parent file used to maintain highlight of the menu item in the admin sidebar.
	 * @param string        $submenu_file_slug Slug for the submenu file used to maintain highlight of the submenu item in the admin sidebar.
	 * @param int           $per_page          Specifies the number of items to display on each page of the list table. Affects pagination.
	 * @param callable|null $errorCallback     Optional callback for handling initialization errors. If provided, called with the exception as an argument.
	 *
	 * @return Register|null An instance of the Register class on success, or null if initialization fails and an error callback is defined.
	 */
	function register_inline_table( string $post_type, array $column_callbacks, string $hook_name, int $hook_priority = 10, string $admin_url = '', array $enqueue_screens = [], string $parent_file_slug = '', string $submenu_file_slug = '', int $per_page = 10, ?callable $errorCallback = null ): ?Register {
		try {
			return new Register( $post_type, $column_callbacks, $hook_name, $hook_priority, $admin_url, $enqueue_screens, $parent_file_slug, $submenu_file_slug, $per_page );
		} catch ( Exception $e ) {
			if ( $errorCallback && is_callable( $errorCallback ) ) {
				call_user_func( $errorCallback, $e );
			}

			// Return null on failure if error callback is provided.
			return null;
		}
	}
}

if ( ! function_exists( 'register_inline_table_post_type' ) ) {
	/**
	 * Helper function to simplify the registration of custom post types for inline table display.
	 *
	 * @param string $post_type           The unique identifier for the custom post type.
	 * @param string $singular_name       The singular name of the post type, used in labels and messages.
	 * @param string $plural_name         The plural name of the post type, used in labels and messages.
	 * @param string $slug                The slug for the post type, used in URLs and query vars.
	 * @param array  $additional_supports An array of additional features that the post type supports. Default features include 'title' and 'page-attributes'.
	 * @param bool   $show_in_rest        Whether to expose this post type in the WordPress REST API. Enables use of the Gutenberg editor and REST API queries.
	 * @param array  $args                An associative array of custom arguments to override or extend the default post type registration settings.
	 */
	function register_inline_table_post_type( string $post_type, string $singular_name, string $plural_name, string $slug, array $additional_supports = [], bool $show_in_rest = true, array $args = [] ) {
		new Post_Type( $post_type, $singular_name, $plural_name, $slug, $additional_supports, $show_in_rest, $args );
	}
}