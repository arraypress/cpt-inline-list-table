<?php
/**
 * A robust class designed to simplify the registration of custom post types in WordPress.
 * By encapsulating common functionalities and configurations, this class streamlines
 * the process of setting up custom post types, including their labels, capabilities,
 * and supports. It provides a structured and extendable approach to declaring new
 * post types, ensuring consistency and reducing boilerplate code across projects.
 *
 * Features:
 * - Easy registration of custom post types with minimal code.
 * - Customizable singular and plural labels for enhanced admin UI integration.
 * - Supports an array of additional features like 'title' and 'page-attributes'.
 * - Enforces best practices by setting public visibility flags appropriately.
 *
 * @package         arraypress/cpt-inline-list-table
 * @copyright       Copyright (c) 2024, ArrayPress Limited
 * @license         GPL2+
 * @version         1.0.0
 * @author          David Sherlock
 */

namespace ArrayPress\WP\CPT_Inline_List_Table;

/**
 * Check if the class `Register_Post_Type` is defined, and if not, define it.
 */
if ( ! class_exists( __NAMESPACE__ . '\\Post_Type' ) ) :

	class Post_Type {

		/**
		 * @var string The key for the custom post type.
		 */
		protected string $post_type;

		/**
		 * @var string The singular name of the post type for labels.
		 */
		protected string $singular_name;

		/**
		 * @var string The plural name of the post type for labels.
		 */
		protected string $plural_name;

		/**
		 * @var string The slug for the post type.
		 */
		protected string $slug;

		/**
		 * @var array Additional features the post type supports.
		 */
		protected array $additional_supports;

		/**
		 * @var bool Indicates if the post type should be accessible via the WordPress REST API.
		 */
		protected bool $show_in_rest = false;

		/**
		 * Constructor for the custom post type registration class.
		 * This constructor initializes the post type with provided settings and automatically registers it with WordPress during the 'init' action.
		 *
		 * @param string $post_type           The unique identifier for the custom post type.
		 * @param string $singular_name       The singular name of the post type, used in labels and messages.
		 * @param string $plural_name         The plural name of the post type, used in labels and messages.
		 * @param string $slug                The slug for the post type, used in URLs and query vars.
		 * @param array  $additional_supports An array of additional features that the post type supports. Default features include 'title' and 'page-attributes'.
		 * @param bool   $show_in_rest        Whether to expose this post type in the WordPress REST API. Enables use of the Gutenberg editor and REST API queries.
		 */
		public function __construct( string $post_type, string $singular_name, string $plural_name, string $slug, array $additional_supports = [], bool $show_in_rest = true ) {
			$this->post_type           = $post_type;
			$this->singular_name       = $singular_name;
			$this->plural_name         = $plural_name;
			$this->slug                = $slug;
			$this->additional_supports = $additional_supports;
			$this->show_in_rest        = $show_in_rest;

			add_action( 'init', [ $this, 'register' ] );
		}

		/**
		 * Registers the custom post type with WordPress.
		 */
		public function register(): void {
			$labels   = $this->generate_labels();
			$supports = array_merge( [ 'title', 'page-attributes' ], $this->additional_supports );

			$args = [
				'labels'             => $labels,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => false,
				'query_var'          => true,
				'rewrite'            => [ 'slug' => $this->slug ],
				'capability_type'    => 'post',
				'has_archive'        => false,
				'supports'           => $supports,
				'show_in_rest'       => $this->show_in_rest,

			];

			register_post_type( $this->post_type, $args );
		}

		/**
		 * Generates the labels array for the custom post type.
		 *
		 * @return array The labels array.
		 */
		protected function generate_labels(): array {
			return [
				'name'               => _x( $this->plural_name, 'post type general name' ),
				'singular_name'      => _x( $this->singular_name, 'post type singular name' ),
				'menu_name'          => _x( $this->plural_name, 'admin menu' ),
				'name_admin_bar'     => _x( $this->singular_name, 'add new on admin bar' ),
				'add_new'            => _x( 'Add New', $this->slug ),
				'add_new_item'       => __( 'Add New ' . $this->singular_name ),
				'new_item'           => __( 'New ' . $this->singular_name ),
				'edit_item'          => __( 'Edit ' . $this->singular_name ),
				'view_item'          => __( 'View ' . $this->singular_name ),
				'all_items'          => __( 'All ' . $this->plural_name ),
				'search_items'       => __( 'Search ' . $this->plural_name ),
				'parent_item_colon'  => __( 'Parent ' . $this->plural_name . ':' ),
				'not_found'          => __( 'No ' . $this->plural_name . ' found.' ),
				'not_found_in_trash' => __( 'No ' . $this->plural_name . ' found in Trash.' )
			];
		}
	}

endif;