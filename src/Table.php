<?php
/**
 * Custom implementation of WP_List_Table for managing and displaying custom post types in an administrative interface.
 * This class extends the WP_List_Table to provide a tailored view for specific post types, allowing for custom
 * columns, sortable columns, and an enhanced listing to include actions such as duplicating posts directly from the
 * list.
 *
 * Features include:
 * - Listing posts with custom columns specific to the post type.
 * - Ability to sort posts by custom defined sortable columns.
 * - Integration of row actions including a custom duplication feature.
 * - Pagination, search, and filtering by post status.
 * - A method to reset custom post type's menu order.
 *
 * @package         arraypress/cpt-inline-list-table
 * @copyright       Copyright (c) 2024, ArrayPress Limited
 * @license         GPL2+
 * @version         1.0.0
 * @author          David Sherlock
 */

namespace ArrayPress\WP\CPT_Inline_List_Table;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

use WP_List_Table;
use WP_Post;
use function get_post_meta;
use function wp_count_posts;

/**
 * Check if the class `Table` is defined, and if not, define it.
 */
if ( ! class_exists( __NAMESPACE__ . '\\Table' ) ) :
	/**
	 * List_Table class for custom post types.
	 *
	 * Extends the WP_List_Table class to provide a custom table view for a specific post type,
	 * including support for custom columns, sorting, and actions.
	 */
	class Table extends WP_List_Table {

		/**
		 * Number of results to show per page.
		 *
		 * @var int
		 */
		public int $per_page = 10;

		/**
		 * Customizable options for items per page.
		 *
		 * @var array
		 */
		protected array $per_page_options = [ 10, 20, 50, 100 ];

		/**
		 * Custom post type for the table.
		 *
		 * @var string
		 */
		private string $post_type;

		/**
		 * Callbacks for custom columns.
		 *
		 * @var array
		 */
		private array $columns_callbacks;

		/**
		 * Singular name for the listed items.
		 *
		 * @var string
		 */
		private string $singular_name;

		/**
		 * Plural name for the listed items.
		 *
		 * @var string
		 */
		private string $plural_name;

		/**
		 * Count of posts by status.
		 *
		 * @var array
		 */
		private array $counts = [];

		/**
		 * Constructs a new instance of the list table with custom settings.
		 *
		 * This constructor initializes the list table with specified settings, including handling
		 * singular and plural labels for the items based on the post type object. If the post type
		 * does not have specific singular or plural labels, it defaults to 'item' and 'items',
		 * respectively. Additionally, this method sets up the columns for the list table, determines
		 * the number of items to display per page, and initializes support for Ajax if specified.
		 *
		 * @param array $args   Configuration arguments for the list table. Expected keys are:
		 *                      - 'post_type' (string): The post type key. Required.
		 *                      - 'columns' (array): An associative array of columns where the key is the column ID
		 *                      and the value is a callback function or a display string. Required.
		 *                      - 'per_page' (int): Number of items to display on each page. Optional, defaults to 10.
		 */
		public function __construct( $args ) {
			if ( empty( $args['post_type'] ) ) {
				return false;
			}

			$this->post_type = $args['post_type'];

			$post_type_object = get_post_type_object( $this->post_type );
			if ( ! $post_type_object ) {
				return false; // Just in case the object retrieval fails
			}

			if ( ! is_post_type_sortable( $this->post_type ) ) {
				return false;
			}

			// Assign singular and plural names based on the post type object or use defaults
			$this->singular_name = $post_type_object->labels->singular_name ?? 'item';
			$this->plural_name   = $post_type_object->labels->name ?? 'items';

			parent::__construct( [
				'singular' => $this->singular_name,
				'plural'   => $this->plural_name,
				'ajax'     => false,
			] );

			$this->columns_callbacks = $args['columns'];
			$this->per_page          = $args['per_page'] ?? 10;

			// Method to get counts or other initializations can be called here
			$this->get_counts();
		}

		/**
		 * Retrieves and sets counts of posts by their status.
		 *
		 * Gathers counts for all relevant post statuses and stores them in the $counts property for later use.
		 */
		public function get_counts(): array {
			$counts = wp_count_posts( $this->post_type );

			$this->counts = [
				'all'     => $counts->publish + $counts->draft + $counts->pending + $counts->future + $counts->private,
				'publish' => $counts->publish,
				'draft'   => $counts->draft,
				'pending' => $counts->pending,
				'future'  => $counts->future,
				'private' => $counts->private,
				'trash'   => $counts->trash,
			];

			return $this->counts;
		}

		/**
		 * Handles the output for default columns in the list table.
		 *
		 * Enhanced to:
		 * 1. Use a specified callable callback if set.
		 * 2. Fallback to direct property of the WP_Post object or post meta based on the column name.
		 * 3. Optionally format the output using a specified formatter function.
		 *
		 * @param WP_Post $post        The current post object.
		 * @param string  $column_name The name of the current column.
		 *
		 * @return mixed The output for the given column and post.
		 */
		public function column_default( $post, $column_name ) {
			$value = '';

			// Use the callback if set
			if ( isset( $this->columns_callbacks[ $column_name ]['callback'] ) && is_callable( $this->columns_callbacks[ $column_name ]['callback'] ) ) {
				$value = call_user_func( $this->columns_callbacks[ $column_name ]['callback'], $post );
			} else {
				// Try to get the value directly from the post object or post meta
				if ( property_exists( $post, $column_name ) ) {
					$value = $post->{$column_name};
				} else {
					$meta_value = get_post_meta( $post->ID, $column_name, true );
					if ( ! empty( $meta_value ) ) {
						$value = $meta_value;
					}
				}
			}

			// If a formatter is set, apply it
			if ( isset( $this->columns_callbacks[ $column_name ]['formatter'] ) && is_callable( $this->columns_callbacks[ $column_name ]['formatter'] ) ) {
				return call_user_func( $this->columns_callbacks[ $column_name ]['formatter'], $value, $post );
			}

			$value = $value !== '' ? wp_kses_post( $value ) : '—';

			// Filter & return
			return apply_filters( 'cpt_inline_list_table_column_default', $value, $post, $column_name, $this );
		}

		/**
		 * Custom output for the title column including actions and lock status.
		 *
		 * @param WP_Post $post The current post object.
		 *
		 * @return string The HTML output for the title column.
		 */
		public function column_title( $post ): string {
			$can_edit_post = current_user_can( 'edit_post', $post->ID );
			$output        = ''; // Initialize the output variable

			if ( $can_edit_post && 'trash' !== $post->post_status ) {
				$lock_holder = wp_check_post_lock( $post->ID );

				if ( $lock_holder ) {
					$lock_holder   = get_userdata( $lock_holder );
					$locked_avatar = get_avatar( $lock_holder->ID, 18 );
					$locked_text   = esc_html( sprintf( __( '%s is currently editing' ), $lock_holder->display_name ) );
				} else {
					$locked_avatar = '';
					$locked_text   = '';
				}

				$output .= '<div class="locked-info"><span class="locked-avatar">' . $locked_avatar . '</span> <span class="locked-text">' . $locked_text . "</span></div>\n";
			}

			$pad    = str_repeat( '&#8212; ', 0 );
			$output .= '<strong>';

			$title = _draft_or_post_title( $post );

			if ( $can_edit_post && 'trash' !== $post->post_status ) {
				$output .= sprintf(
					'<a class="row-title" href="%s" aria-label="%s">%s%s</a>',
					get_edit_post_link( $post->ID ),
					esc_attr( sprintf( __( '&#8220;%s&#8221; (Edit)' ), $title ) ),
					$pad,
					$title
				);
			} else {
				$output .= sprintf(
					'<span>%s%s</span>',
					$pad,
					$title
				);
			}

			// Assuming _post_states is modified to return a string instead of echoing directly
			$post_states = _post_states( $post, false );
			$output      .= $post_states;

			$output .= "</strong>\n";

			// Filter & return
			return apply_filters( 'cpt_inline_list_table_column_title', $output, $post, $this );
		}

		/**
		 * Handles the output for the date column.
		 *
		 * @param WP_Post $post The current post object.
		 *
		 * @return string The output for the date column.
		 */
		public function column_date( $post ) {
			global $mode;
			$output = ''; // Initialize the output variable.

			if ( '0000-00-00 00:00:00' === $post->post_date ) {
				$t_time    = __( 'Unpublished' );
				$time_diff = 0;
			} else {
				$t_time = sprintf(
				/* translators: 1: Post date, 2: Post time. */
					__( '%1$s at %2$s' ),
					/* translators: Post date format. See https://www.php.net/manual/datetime.format.php */
					get_the_time( __( 'Y/m/d' ), $post ),
					/* translators: Post time format. See https://www.php.net/manual/datetime.format.php */
					get_the_time( __( 'g:i a' ), $post )
				);

				$time      = get_post_timestamp( $post );
				$time_diff = time() - $time;
			}

			if ( 'publish' === $post->post_status ) {
				$status = __( 'Published' );
			} elseif ( 'future' === $post->post_status ) {
				if ( $time_diff > 0 ) {
					$status = '<strong class="error-message">' . __( 'Missed schedule' ) . '</strong>';
				} else {
					$status = __( 'Scheduled' );
				}
			} else {
				$status = __( 'Last Modified' );
			}

			// Apply filters to the status text.
			$status = apply_filters( 'cpt_inline_list_table_post_date_column_status', $status, $post, 'date', $mode );

			if ( $status ) {
				$output .= $status . '<br />';
			}

			// Apply filters to the published, scheduled, or unpublished time.
			$output .= apply_filters( 'cpt_inline_list_table_post_date_column_time', $t_time, $post, 'date', $mode );

			return $output; // Return the concatenated output.
		}

		/**
		 * Renders the author column content.
		 *
		 * This function retrieves the ID of the post's author and then generates an edit link
		 * for the author, displaying the author's name as the link text. The `get_edit_link`
		 * method is assumed to generate the appropriate HTML link based on the provided arguments.
		 *
		 * @param WP_Post $post The current post object from which the author's information is retrieved.
		 */
		public function column_author( $post ) {
			$args = array(
				'author' => get_the_author_meta( 'ID' ),
			);

			echo $this->get_edit_link( $args, get_the_author() );
		}

		/**
		 * Creates a link to edit.php with params.
		 *
		 * @param string[] $args      Associative array of URL parameters for the link.
		 * @param string   $link_text Link text.
		 * @param string   $css_class Optional. Class attribute. Default empty string.
		 *
		 * @return string The formatted link string.
		 */
		protected function get_edit_link( $args, string $link_text, string $css_class = '' ) {
			$url = add_query_arg( $args );

			$class_html   = '';
			$aria_current = '';

			if ( ! empty( $css_class ) ) {
				$class_html = sprintf(
					' class="%s"',
					esc_attr( $css_class )
				);

				if ( 'current' === $css_class ) {
					$aria_current = ' aria-current="page"';
				}
			}

			return sprintf(
				'<a href="%s"%s%s>%s</a>',
				esc_url( $url ),
				$class_html,
				$aria_current,
				$link_text
			);
		}

		/**
		 * Renders the entire list table, including navigation and headers.
		 */
		public function display() {
			$singular    = $this->_args['singular'];
			$num_columns = count( $this->get_columns() );

			$this->display_tablenav( 'top' );

			// Check if near the bottom pagination section
			$this->screen->render_screen_reader_content( 'heading_list' );
			?>

            <table class="wp-list-table <?php echo implode( ' ', $this->get_table_classes() ); ?>">
				<?php $this->print_table_description(); ?>
                <thead>
                <tr>
					<?php $this->print_column_headers(); ?>
                </tr>
                </thead>

                <tbody id="the-list"
					<?php
					if ( $singular ) {
						echo " data-wp-lists='list:$singular'";
					}
					?>
                >
				<?php $this->display_rows_or_placeholder(); ?>
                </tbody>

                <tfoot>
                <tr>
                    <th colspan="<?php esc_attr_e( $num_columns ); ?>" class="tablefooter">
						<?php $this->print_action_links(); ?>
                    </th>
                </tr>
                </tfoot>

            </table>
			<?php
			$this->display_tablenav( 'bottom' );
		}

		/**
		 * Defines the columns to be displayed in the list table.
		 *
		 * @return array An associative array of column identifiers and their respective titles.
		 */
		public function get_columns(): array {
			$columns = [
				'title' => __( 'Title' ),
			];

			if ( ! empty( $this->columns_callbacks ) ) {
				foreach ( $this->columns_callbacks as $key => $value ) {
					// Add the column only if its 'label' is set and not empty
					if ( ! empty( $value['label'] ) ) {
						$columns[ $key ] = $value['label'];
					}
				}
			}

			return $columns;
		}

		/**
		 * Retrieves a list of CSS classes for the list table.
		 *
		 * @return array An array of CSS classes for the table.
		 */
		protected function get_table_classes(): array {
			$classes   = parent::get_table_classes();
			$classes[] = 'cpt-list-table';
			$classes[] = $this->get_status( 'all' );

			return $classes;
		}

		/**
		 * Retrieves the current post status from the request or returns a default value.
		 *
		 * @param string $default The default value to return if the post status is not set.
		 *
		 * @return string The post status from the request or the default value if not set.
		 */
		public function get_status( string $default = 'all' ): string {
			$status = $this->get_request_var( 'post_status', $default );
			$status = strtolower( $status );

			// Ensure that the status is one of the allowed values or falls back to the default
			$allowed_statuses = [ 'all', 'publish', 'draft', 'pending', 'future', 'private', 'trash' ];

			return in_array( $status, $allowed_statuses ) ? $status : $default;
		}

		/**
		 * Utility method to retrieve a specific request variable.
		 *
		 * @param string $var     The variable to retrieve from the request.
		 * @param mixed  $default The default value to return if the variable is not set.
		 *
		 * @return mixed The value of the request variable or the default value.
		 */
		public function get_request_var( string $var = '', $default = false ) {
			return $_REQUEST[ $var ] ?? $default;
		}

		/**
		 * Outputs the "Add New Post" button.
		 */
		public function print_action_links() {
			?>
            <div class="actionbuttons">
                <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . $this->post_type ) ); ?>" class="add button">
					<?php echo esc_html__( 'Add New ' . $this->singular_name ); ?>
                </a>

				<?php if ( $this->has_trash() && 'trash' === $this->get_status() ): ?>
                    <a href="#" class="delete-trash button" data-posttype="<?php esc_attr_e( $this->post_type ); ?>">
						<?php echo esc_html__( 'Empty Trash' ); ?>
                    </a>
				<?php elseif ( $this->has_posts() ) : ?>
                    <a href="#" class="reset-menu-order button" data-posttype="<?php esc_attr_e( $this->post_type ); ?>">
						<?php echo esc_html__( 'Reset Order' ); ?>
                    </a>
				<?php endif; ?>
            </div>
			<?php
		}

		/**
		 * Determines if the current post type has any posts in the trash.
		 *
		 * This method checks the post count array for the 'trash' key and
		 * evaluates if the trash count is greater than zero. It is useful for
		 * conditionally displaying UI elements that interact with trashed posts,
		 * such as an 'Empty Trash' button.
		 *
		 * @return bool True if there are posts in the trash for the current post type, false otherwise.
		 */
		private function has_trash(): bool {
			return isset( $this->counts['trash'] ) && $this->counts['trash'] > 0;
		}

		/**
		 * Determines if the current post type has any posts.
		 *
		 * This method checks the post count array for the 'all' key to determine if there are
		 * any posts available, regardless of their status. It can be used to conditionally display
		 * UI elements or messages based on whether the post type has posts. For instance, it could
		 * help decide whether to show a list table or a 'No posts found' message.
		 *
		 * @return bool True if there are posts for the current post type, false otherwise.
		 */
		private function has_posts(): bool {
			return isset( $this->counts['all'] ) && $this->counts['all'] > 0;
		}

		/**
		 * Renders a single row in the list table.
		 *
		 * @param WP_Post|int $post  The post object or ID.
		 * @param int         $level The depth level for hierarchical post types.
		 */
		public function single_row( $post, $level = 0 ) {
			$global_post = get_post();

			$post = get_post( $post );

			$GLOBALS['post'] = $post;
			setup_postdata( $post );

			$classes = 'iedit author-' . ( get_current_user_id() === (int) $post->post_author ? 'self' : 'other' );

			$lock_holder = wp_check_post_lock( $post->ID );

			if ( $lock_holder ) {
				$classes .= ' wp-locked';
			}

			?>
            <tr id="post-<?php echo $post->ID; ?>" class="<?php echo implode( ' ', get_post_class( $classes, $post->ID ) ); ?>">
				<?php $this->single_row_columns( $post ); ?>
            </tr>
			<?php
			$GLOBALS['post'] = $global_post;
		}

		/**
		 * Outputs a message when no items are found.
		 */
		public function no_items() {
			echo esc_html( sprintf( __( 'No %s found.' ), strtolower( $this->plural_name ) ) );
		}

		/**
		 * Prepares the items to be displayed in the list table.
		 *
		 * Configures pagination, retrieves the items based on current query parameters, and sets up the columns.
		 */
		public function prepare_items() {
			$per_page       = $this->get_per_page();
			$current_page   = $this->get_pagenum();
			$current_filter = $this->get_status( 'all' );
			$author         = $this->get_author();

			// Get all post stati, excluding some that may not be relevant for your list
			$all_statuses = get_post_stati( [ 'show_in_admin_all_list' => true ], 'names', 'and' );

			// If the current filter is 'all', query all relevant statuses; otherwise, just query the selected status
			$query_statuses = $current_filter === 'all' ? $all_statuses : [ $current_filter ];

			// Get counts of posts for pagination
			$counts = wp_count_posts( $this->post_type );

			// Calculate total items based on all relevant statuses
			$total_items = array_sum( array_intersect_key( (array) $counts, array_flip( $query_statuses ) ) );

			$this->set_pagination_args( [
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page )
			] );

			$args = [
				'post_type'      => $this->post_type,
				'posts_per_page' => $per_page,
				'paged'          => $current_page,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'post_status'    => $query_statuses,
			];

			if ( ! empty( $author ) ) {
				$args['author'] = $author;
			}

			$this->items = get_posts( $args );

			$this->_column_headers = [ $this->get_columns(), [], $this->get_sortable_columns() ];
		}

		/**
		 * Retrieves the value for the 'per_page' setting.
		 *
		 * @return int The number of items to display per page.
		 */
		protected function get_per_page(): int {
			return absint( $this->get_request_var( 'per_page', 10 ) );
		}

		/**
		 * Retrieves the value for the 'author' setting.
		 *
		 * @return int The number of items to display per page.
		 */
		protected function get_author(): int {
			return absint( $this->get_request_var( 'author', null ) );
		}

		/**
		 * Generates and displays row action links for each post.
		 *
		 * @param WP_Post $item        The current post object.
		 * @param string  $column_name The name of the current column.
		 * @param string  $primary     The name of the primary column.
		 *
		 * @return string HTML string of action links for the post.
		 */
		protected function handle_row_actions( $item, $column_name, $primary ): string {
			if ( $primary !== $column_name ) {
				return '';
			}

			// Restores the more descriptive, specific name for use within this method.
			$post = $item;

			$post_type_object = get_post_type_object( $post->post_type );
			$can_edit_post    = current_user_can( 'edit_post', $post->ID );
			$actions          = array();
			$title            = _draft_or_post_title();

			if ( $can_edit_post && 'trash' !== $post->post_status ) {
				$actions['edit'] = sprintf(
					'<a href="%s" aria-label="%s">%s</a>',
					get_edit_post_link( $post->ID ),
					/* translators: %s: Post title. */
					esc_attr( sprintf( __( 'Edit &#8220;%s&#8221;' ), $title ) ),
					__( 'Edit' )
				);

				$actions['duplicate'] = sprintf(
					'<a href="%s" aria-label="%s">%s</a>',
					get_duplicate_post_link( $post->ID ),
					/* translators: %s: Post title. */
					esc_attr( sprintf( __( 'Duplicate &#8220;%s&#8221;' ), $title ) ),
					__( 'Duplicate' )
				);
			}

			if ( current_user_can( 'delete_post', $post->ID ) ) {
				if ( 'trash' === $post->post_status ) {
					$actions['untrash'] = sprintf(
						'<a href="%s" aria-label="%s">%s</a>',
						wp_nonce_url( admin_url( sprintf( $post_type_object->_edit_link . '&amp;action=untrash', $post->ID ) ), 'untrash-post_' . $post->ID ),
						/* translators: %s: Post title. */
						esc_attr( sprintf( __( 'Restore &#8220;%s&#8221; from the Trash' ), $title ) ),
						__( 'Restore' )
					);
				} elseif ( EMPTY_TRASH_DAYS ) {
					$actions['trash'] = sprintf(
						'<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
						get_delete_post_link( $post->ID ),
						/* translators: %s: Post title. */
						esc_attr( sprintf( __( 'Move &#8220;%s&#8221; to the Trash' ), $title ) ),
						_x( 'Trash', 'verb' )
					);
				}

				if ( 'trash' === $post->post_status || ! EMPTY_TRASH_DAYS ) {
					$actions['delete'] = sprintf(
						'<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
						get_delete_post_link( $post->ID, '', true ),
						/* translators: %s: Post title. */
						esc_attr( sprintf( __( 'Delete &#8220;%s&#8221; permanently' ), $title ) ),
						__( 'Delete Permanently' )
					);
				}
			}

			return $this->row_actions( $actions );
		}

		/**
		 * Renders extra table navigation elements.
		 *
		 * @param string $which Specifies the placement of the extra navigation ('top' or 'bottom').
		 */
		protected function extra_tablenav( $which ) {
			if ( 'top' === $which ) {
				$this->views();
			}

			if ( 'bottom' === $which && $this->has_posts() ) {
				$per_page = $this->get_per_page();
				$options  = $this->get_per_page_options();

				if ( ! empty( $options ) ) { ?>
                    <div class="tablenav-per-page">
                        <div class="per-page-selector">
                            <label for="per-page"><?php echo esc_html__( 'Items per page:' ); ?></label>
                            <select id="per-page" name="per_page">
								<?php foreach ( $options as $option ): ?>
                                    <option value="<?php echo esc_attr( $option ); ?>" <?php selected( $per_page, $option, true ); ?>><?php echo esc_html( $option ); ?></option>
								<?php endforeach; ?>
                            </select>
                        </div>
                    </div>
				<?php }
			}
		}

		/**
		 * Retrieves the options for the "Items per page" setting.
		 *
		 * @return array An array of available options for items to display per page.
		 */
		protected function get_per_page_options(): array {
			return $this->per_page_options;
		}

		/**
		 * Sets the available options for items to display per page.
		 *
		 * @param array $options An array of integers representing the options.
		 */
		public function set_per_page_options( array $options ) {
			$this->per_page_options = $options;
		}

		/** Data **********************************************************************/

		/**
		 * Retrieves the orderby request variable or returns a default value.
		 *
		 * @return string The orderby parameter from the request or a default value.
		 */
		protected function get_orderby(): string {
			return sanitize_key( $this->get_request_var( 'orderby', 'menu_order' ) );
		}

		/**
		 * Retrieves the order request variable or returns a default value.
		 *
		 * @return string The order parameter from the request or a default value.
		 */
		protected function get_order(): string {
			return sanitize_key( $this->get_request_var( 'order', 'ASC' ) );
		}

		/**
		 * Retrieves the current page number for pagination.
		 *
		 * @return int The current page number.
		 */
		protected function get_paged(): int {
			return absint( $this->get_request_var( 'paged', 1 ) );
		}

		/**
		 * Compiles the views for different post statuses with counts.
		 *
		 * @return array An associative array of status links for the list table.
		 */
		protected function get_views(): array {
			if ( empty( $this->counts ) ) {
				$this->get_counts();
			}

			$views          = [];
			$current_status = $this->get_status();

			// Add links conditionally based on count
			if ( $this->counts['all'] > 0 ) {
				$views['all'] = $this->get_post_state_link( 'all', 'All', 'all' === $current_status, $this->counts['all'] );
			}

			if ( isset( $this->counts['publish'] ) && $this->counts['publish'] > 0 ) {
				$views['publish'] = $this->get_post_state_link( 'publish', 'Published', 'publish' === $current_status, $this->counts['publish'] );
			}

			if ( isset( $this->counts['draft'] ) && $this->counts['draft'] > 0 ) {
				$views['draft'] = $this->get_post_state_link( 'draft', 'Drafts', 'draft' === $current_status, $this->counts['draft'] );
			}

			if ( isset( $this->counts['future'] ) && $this->counts['future'] > 0 ) {
				$views['future'] = $this->get_post_state_link( 'future', 'Scheduled', 'future' === $current_status, $this->counts['future'] );
			}

			if ( isset( $this->counts['trash'] ) && $this->counts['trash'] > 0 ) {
				$views['trash'] = $this->get_post_state_link( 'trash', 'Trashed', 'trash' === $current_status, $this->counts['trash'] );
			}

			return $views;
		}

		/**
		 * Generates a status link for the list table filters.
		 *
		 * @param string $state_slug The post status slug.
		 * @param string $label      The label for the link.
		 * @param bool   $is_current Whether this status is the current filter.
		 * @param int    $count      The number of posts with this status.
		 *
		 * @return string The HTML link for the status filter.
		 */
		private function get_post_state_link( string $state_slug, string $label, bool $is_current, int $count ): string {
			$class = $is_current ? ' class="current"' : '';

			// Use add_query_arg to construct the final URL with all query args
			$url = add_query_arg( [ 'post_status' => $state_slug ] );
			$url = remove_query_arg( [ 'paged', 'per_page' ], $url );

			return sprintf( '<a href="%s"%s>%s <span class="count">(%d)</span></a>', esc_url( $url ), $class, esc_html( $label ), $count );
		}

	}

endif;