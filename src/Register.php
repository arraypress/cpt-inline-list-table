<?php
/**
 * Facilitates various operations for managing a custom post type within the WordPress admin.
 * These operations include registering custom post types, handling admin columns, enqueuing scripts
 * and styles, managing admin menu highlighting, and more.
 *
 * Designed to work seamlessly within the WordPress environment, leveraging WordPress core functions
 * and standards.
 *
 * @package         arraypress/cpt-inline-list-table
 * @copyright       Copyright (c) 2024, ArrayPress Limited
 * @license         GPL2+
 * @version         1.0.0
 * @author          David Sherlock
 */

declare( strict_types=1 );

namespace ArrayPress\WP\CPT_Inline_List_Table;

use WP_Post;

/**
 * Check if the class `Register` is defined, and if not, define it.
 */
if ( ! class_exists( __NAMESPACE__ . '\\Register' ) ) :

	/**
	 * Register class for custom post types.
	 */
	class Register {

		/**
		 * The post type key for the custom post type.
		 *
		 * @var string
		 */
		public string $post_type;

		/**
		 * Specifies the name of the hook on which the custom post type should be registered.
		 *
		 * @var string
		 */
		public string $hook_name;

		/**
		 * Callback functions for managing custom column_callbacks in the list table.
		 *
		 * @var array
		 */
		public array $column_callbacks;

		/**
		 * The priority at which the custom post type is registered on the hook.
		 *
		 * @var int
		 */
		public int $hook_priority;

		/**
		 * The URL for the admin page related to this post type.
		 *
		 * @var string
		 */
		public string $admin_url;

		/**
		 * Specifies the admin screens on which the scripts and styles should be enqueued.
		 *
		 * @var array
		 */
		public array $enqueue_screens;

		/**
		 * The parent file slug for highlighting the menu.
		 *
		 * @var string
		 */
		public string $parent_file_slug;

		/**
		 * The submenu file slug for highlighting the menu.
		 *
		 * @var string
		 */
		public string $submenu_file_slug;

		/**
		 * The number of posts to display in the table.
		 *
		 * @var int
		 */
		public int $per_page;

		/**
		 * Initializes the Register class with settings for managing a custom post type and its admin list table.
		 *
		 * This constructor sets up the necessary properties for the custom post type management, including:
		 * - Identifying the post type and the hook to which the custom post type registration is attached.
		 * - Defining callback functions for managing custom column_callbacks within the admin list table.
		 * - Setting the priority for the hook execution.
		 * - Specifying the admin URL for redirecting after certain operations (e.g., post trashing).
		 * - Determining which admin screens the scripts and styles should be enqueued on.
		 * - Configuring the highlighting of the parent and submenu items in the admin menu.
		 *
		 * AJAX and additional actions are initialized, and WordPress hooks are set up to support the custom post type's
		 * administrative interface and functionality.
		 *
		 * @param string $post_type          The post type key. This is a required parameter to identify the custom post type.
		 * @param array  $column_callbacks   Callback functions for custom column_callbacks. An associative array where the key is the column
		 *                                   ID, and the value is a callback function for rendering the column content.
		 * @param string $hook_name          The name of the hook to register the custom post type. This is used to attach the custom
		 *                                   post type registration to a specific action within WordPress.
		 * @param int    $hook_priority      The priority at which the custom post type is registered on the hook. Defaults to 10.
		 * @param string $admin_url          The URL for the admin page related to this post type. This is used for redirects
		 *                                   and linking within the admin interface.
		 * @param array  $enqueue_screens    An array of admin screen IDs on which the scripts and styles for the custom post type
		 *                                   should be enqueued.
		 * @param string $parent_file_slug   The slug for the parent file used in highlighting the menu item in the admin sidebar.
		 * @param string $submenu_file_slug  The slug for the submenu file used in highlighting the submenu item in the admin sidebar.
		 * @param int    $per_page           The priority at which the custom post type is registered on the hook. Defaults to 10.
		 */
		public function __construct( string $post_type = '', array $column_callbacks = [], string $hook_name = '', int $hook_priority = 10, string $admin_url = '', array $enqueue_screens = [], string $parent_file_slug = '', string $submenu_file_slug = '', int $per_page = 10 ) {
			$this->set_post_type( $post_type );
			$this->set_column_callbacks( $column_callbacks );
			$this->set_hook_name( $hook_name );
			$this->set_hook_priority( $hook_priority );
			$this->set_admin_url( $admin_url );
			$this->set_enqueue_screens( $enqueue_screens );
			$this->set_parent_file_slug( $parent_file_slug );
			$this->set_submenu_file_slug( $submenu_file_slug );
			$this->set_per_page( $per_page );

			AJAX::get_instance();
			Actions::get_instance();

			$this->hooks();
		}

		/**
		 * Sets the post type.
		 *
		 * @param string $post_type The post type key.
		 */
		public function set_post_type( string $post_type ) {
			$this->post_type = $post_type;
		}

		/**
		 * Sets the column callbacks.
		 *
		 * @param array $column_callbacks Array of columns and their callbacks.
		 */
		public function set_column_callbacks( array $column_callbacks ) {
			$this->column_callbacks = $column_callbacks;
		}

		/**
		 * Sets the hook name.
		 *
		 * @param string $hook_name The name of the hook.
		 */
		public function set_hook_name( string $hook_name ) {
			$this->hook_name = $hook_name;
		}

		/**
		 * Sets the hook priority.
		 *
		 * @param int $hook_priority The priority of the hook.
		 */
		public function set_hook_priority( int $hook_priority = 10 ) {
			$this->hook_priority = $hook_priority;
		}

		/**
		 * Sets the admin URL.
		 *
		 * @param string $admin_url The admin URL.
		 */
		public function set_admin_url( string $admin_url ) {
			$this->admin_url = $admin_url;
		}

		/**
		 * Sets the enqueue screens.
		 *
		 * @param array $enqueue_screens Screens on which to enqueue scripts.
		 */
		public function set_enqueue_screens( array $enqueue_screens ) {
			$this->enqueue_screens = $enqueue_screens;
		}

		/**
		 * Sets the parent file slug.
		 *
		 * @param string $parent_file_slug The parent file slug.
		 */
		public function set_parent_file_slug( string $parent_file_slug ) {
			$this->parent_file_slug = $parent_file_slug;
		}

		/**
		 * Sets the submenu file slug.
		 *
		 * @param string $submenu_file_slug The submenu file slug.
		 */
		public function set_submenu_file_slug( string $submenu_file_slug ) {
			$this->submenu_file_slug = $submenu_file_slug;
		}

		/**
		 * Sets the per page.
		 *
		 * @param int $per_page The per page number.
		 */
		public function set_per_page( int $per_page = 10 ) {
			$this->per_page = $per_page;
		}

		/**
		 * Validates the post type and column_callbacks configuration.
		 *
		 * Checks if the specified post type exists, supports 'page-attributes', is non-hierarchical, and verifies that
		 * the column_callbacks configuration is valid. Collects and displays error messages for any validation failures.
		 *
		 * @return bool True if all validations pass, false otherwise.
		 */
		private function validate_setup(): bool {
			$error_messages = [];

			if ( empty( $this->post_type ) ) {
				$error_messages[] = "No post type has been specified. The post type is required to configure the custom list table correctly. Please specify a valid post type.";
			} else {
				if ( ! get_post_type_object( $this->post_type ) ) {
					$error_messages[] = "The post type '{$this->post_type}' does not exist. Please verify that you have specified the correct post type.";
				}

				if ( ! post_type_supports( $this->post_type, 'page-attributes' ) ) {
					$error_messages[] = "The post type '{$this->post_type}' does not support 'page-attributes'. Sortable post types must support 'page-attributes'.";
				}

				if ( is_post_type_hierarchical( $this->post_type ) ) {
					$error_messages[] = "The post type '{$this->post_type}' is hierarchical. Sortable post types cannot be hierarchical.";
				}
			}

			if ( ! empty( $this->column_callbacks ) && ! $this->is_valid_column_callbacks( $this->column_callbacks ) ) {
				$error_messages[] = "The column settings for '{$this->post_type}' post type are invalid. Each column must have a non-empty string key and a defined label to be considered valid.";
			}

			// Verify that the enqueue_screens array is not empty
			if ( empty( $this->enqueue_screens ) ) {
				$error_messages[] = "The enqueue_screens array is empty. At least one admin screen must be specified where scripts and styles will be enqueued.";
			}

			if ( ! empty( $error_messages ) ) {
				echo '<div class="cpt-list-table-error">';
				foreach ( $error_messages as $message ) {
					echo '<p>' . esc_html( $message ) . '</p>';
				}
				echo '</div>';

				return false; // Validation failed
			}

			return true; // Validation succeeded
		}

		/**
		 * Registers all necessary hooks for the plugin.
		 *
		 * This method sets up hooks for enqueueing scripts and styles, managing redirections
		 * after certain actions, modifying admin menu highlights, and customizing post preview links
		 * and post update messages. It ensures that the custom post type integrates well within
		 * the WordPress admin by hooking into the appropriate WordPress actions and filters.
		 */
		private function hooks() {

			// Add hook for enqueueing admin scripts and styles
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

			// Add hook for redirecting after trashing a post
			add_action( 'load-edit.php', [ $this, 'redirect_after_trash' ] );

			// Add hook for highlighting the menu item in admin
			add_action( 'admin_head', [ $this, 'menu_highlight' ] );

			// Add filter for modifying the post preview link
			add_filter( 'preview_post_link', [ $this, 'preview_post_link' ], 10, 2 );

			// Add filter for customizing post updated messages
			add_filter( 'post_updated_messages', [ $this, 'custom_post_type_messages' ] );

			// Conditionally add action for displaying the custom post type table
			if ( ! empty( $this->hook_name ) ) {
				add_action( $this->hook_name, [ $this, 'display' ], $this->hook_priority );
			}

			// Add hook for redirecting after permanently deleting a post
			add_action( 'deleted_post', [ $this, 'redirect_after_permanent_delete' ], 10, 2 );

		}

		/**
		 * Redirects the user to a specified admin URL after a post of the custom post type has been permanently deleted.
		 *
		 * Checks if the deleted post matches the custom post type managed by this class and if the action was to 'delete'.
		 * If both conditions are met, the user is redirected to the custom admin URL provided during class instantiation.
		 *
		 * @param int     $post_id The ID of the post that was deleted.
		 * @param WP_Post $post    The post object that was deleted.
		 */
		public function redirect_after_permanent_delete( $post_id, $post ) {
			if ( isset( $post->post_type ) && $post->post_type === $this->post_type && isset( $_GET['action'] ) && $_GET['action'] === 'delete' ) {
				wp_redirect( admin_url( $this->admin_url ) );
				exit;
			}
		}

		/**
		 * Displays the custom post type list table.
		 *
		 * This method is responsible for rendering the list table associated with the custom post type.
		 * It first validates the post type and its column configuration to ensure they meet the necessary
		 * criteria. If validation passes, it proceeds to instantiate and prepare the custom table class,
		 * preparing the items to be displayed based on the current query parameters. Finally, it renders
		 * the table within a wrapping <div> for proper formatting in the WordPress admin.
		 *
		 * The method relies on the `Table` class, which should be defined elsewhere in the plugin. This
		 * class extends `WP_List_Table` and is tailored to display the custom post type's data according
		 * to the specified columns and configurations.
		 *
		 * If the post type or columns fail validation, the method will halt execution and output error
		 * messages to the admin interface, notifying the user of the configuration issues.
		 */
		public function display() {
			if ( ! $this->validate_setup() ) {
				return false; // Stop execution if there are validation errors
			}

			$table = new List_Table( [
				'post_type' => $this->post_type,
				'columns'   => $this->column_callbacks,
				'per_page'  => $this->per_page
			] );
			$table->prepare_items();
			?>
            <div class="wrap">
				<?php $table->display(); ?>
            </div>
			<?php
		}

		/**
		 * Validates the column_callbacks configuration for the table.
		 *
		 * Checks each column to ensure that the key is a non-empty string and the label is set and not empty.
		 * If any column fails these validation criteria, the function immediately returns false, indicating
		 * the column configuration is invalid.
		 *
		 * @param array $column_callbacks The column_callbacks configuration array.
		 *
		 * @return bool True if all column_callbacks have non-empty string keys and non-empty labels, false otherwise.
		 */
		public function is_valid_column_callbacks( array $column_callbacks ): bool {
			if ( ! empty( $column_callbacks ) ) {
				foreach ( $column_callbacks as $key => $column ) {
					if ( ! is_string( $key ) || empty( $key ) || empty( $column['label'] ) ) {
						return false;
					}
				}
			}

			return true; // If the loop completes without finding any invalid column_callbacks, return true.
		}

		/**
		 * Enqueues scripts and styles specifically for the custom post type admin pages.
		 *
		 * Checks if the current admin page is among the specified enqueue screens and, if so,
		 * enqueues the necessary JavaScript and CSS files. This ensures that scripts and styles
		 * are only loaded where they are needed.
		 *
		 * @param string $hook The current page hook suffix to compare against the specified enqueue screens.
		 */
		public function enqueue_scripts( string $hook ) {
			if ( ! in_array( $hook, $this->enqueue_screens, true ) ) {
				return;
			}

			// Enqueue the JavaScript file
			wp_enqueue_script(
				'cpt-list-table-js',
				plugins_url( 'assets/js/list-table.js', __FILE__ ),
				[ 'jquery', 'jquery-ui-sortable' ],
				filemtime( __DIR__ . '/assets/js/list-table.js' ),
				true
			);

			// Enqueue the CSS file
			wp_enqueue_style(
				'cpt-list-table-css',
				plugins_url( 'assets/css/list-table.css', __FILE__ ),
				[],
				filemtime( __DIR__ . '/assets/css/list-table.css' )
			);

			// Localize script variables
			wp_localize_script(
				'cpt-list-table-js',
				'cpt_inline_list_table_localized_data',
				array(
					'_wpnonce'      => wp_create_nonce( 'cpt-list-table-ordering-nonce' ),

					// Generic message without specifying the post type
					'reset_message' => esc_html__( 'Are you sure you want to reset the ordering?', 'text-domain' ),

					// Generic message without specifying the post type
					'trash_message' => esc_html__( 'Are you sure you want to permanently delete all items currently in the trash?', 'text-domain' ),
				)
			);

		}

		/**
		 * Redirects the user after a post has been trashed.
		 *
		 * This method checks if the current action is related to trashing a post of the custom post type
		 * and redirects to a specified admin URL if so. This can be used to redirect users to a more
		 * relevant page after an action has been taken.
		 */
		public function redirect_after_trash() {
			$screen = get_current_screen();

			if ( ! empty( $this->admin_url ) && "edit-{$this->post_type}" == $screen->id ) {
				if ( isset( $_GET['trashed'] ) && intval( $_GET['trashed'] ) > 0 ) {
					wp_redirect( admin_url( $this->admin_url ) );
					exit();
				}
			}
		}

		/**
		 * Modifies the preview post link for the custom post type.
		 *
		 * Allows customizing the URL used for previewing posts of the custom post type. This can be
		 * useful if the default preview behavior needs to be adjusted or overridden.
		 *
		 * @param string  $preview_link The current preview link URL.
		 * @param WP_Post $post         The post object being previewed.
		 *
		 * @return string The modified preview link URL.
		 */
		public function preview_post_link( string $preview_link, WP_Post $post ): string {
			if ( ! empty( $this->admin_url ) && $this->post_type === $post->post_type ) {
				return admin_url( $this->admin_url );
			}

			// Return the original preview link for all other cases
			return $preview_link;
		}

		/**
		 * Customizes the post updated messages for the custom post type.
		 *
		 * Filters the array of default WordPress messages displayed after posts are updated. This allows
		 * for custom post type-specific messages that provide clearer feedback to the user based on the
		 * action taken.
		 *
		 * @param array $messages The array of default post updated messages.
		 *
		 * @return array The modified array of post updated messages.
		 */
		function custom_post_type_messages( array $messages ): array {
			$post = get_post();

			// Early return if no post object is found or if it's not the custom post type we're interested in.
			if ( ! $post || $this->post_type !== $post->post_type ) {
				return $messages;
			}

			$post_type_object = get_post_type_object( $this->post_type );

			// Check if the post type object was successfully retrieved.
			if ( ! $post_type_object ) {
				return $messages;
			}

			$singular_name = $post_type_object->labels->singular_name;
			$name          = $post_type_object->labels->name;
			$admin_url     = ! empty( $this->admin_url ) ? $this->admin_url : 'edit.php?post_type=' . $this->post_type;
			$admin_url     = admin_url( $admin_url );

			// Construct the return link using the provided or default admin URL.
			$return_link = sprintf( ' <a href="%s">%s</a>', esc_url( $admin_url ), sprintf( __( 'Return to %s overview.' ), $name ) );

			// Modify the messages specific to the custom post type.
			$messages[ $this->post_type ] = [
				0  => '', // Unused. Messages start at index 1.
				1  => sprintf( __( '%s updated.' ), $singular_name ) . $return_link,
				2  => __( 'Custom field updated.' ),
				3  => __( 'Custom field deleted.' ),
				4  => sprintf( __( '%s updated.' ), $singular_name ),
				5  => isset( $_GET['revision'] ) ? sprintf( __( '%s restored to revision from %s' ), $singular_name, wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
				6  => sprintf( __( '%s published.' ), $singular_name ) . $return_link,
				7  => sprintf( __( '%s saved.' ), $singular_name ),
				8  => sprintf( __( '%s submitted.' ), $singular_name ) . $return_link,
				9  => sprintf( __( '%s scheduled for: <strong>%1$s</strong>.' ), $singular_name, date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ) ) . $return_link,
				10 => sprintf( __( '%s draft updated.' ), $singular_name ) . $return_link,
			];

			return $messages;
		}

		/**
		 * Adjusts admin menu highlighting based on the current post type.
		 *
		 * This method ensures that the appropriate admin menu item remains highlighted when editing
		 * a post of the custom post type. It adjusts the global variables used by WordPress to
		 * determine which menu item is highlighted.
		 */
		public function menu_highlight() {
			global $parent_file, $submenu_file, $post_type;

			if ( ! empty( $this->parent_file_slug ) && $this->post_type == $post_type ) {
				$parent_file = $this->parent_file_slug; // e.g., 'edit.php?post_type=download';

				if ( ! empty( $this->submenu_file_slug ) ) {
					$submenu_file = $this->submenu_file_slug; // e.g., 'edd-settings';
				}
			}
		}

	}

endif;
