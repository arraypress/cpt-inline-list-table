<?php
/**
 * The `AJAX` class is designed to facilitate AJAX interactions for the custom implementation of the
 * WP_List_Table used in managing and displaying custom post types within the WordPress administrative interface. This
 * class handles AJAX requests related to post ordering, resetting order, and deletion from the trash, enhancing the
 * user experience by enabling dynamic page updates without requiring page reloads. Key functionalities include AJAX
 * callbacks for reordering posts, resetting the order of all posts of a given post type to their default state, and
 * deleting all posts of a specific post type that are currently in the trash. It utilizes the singleton pattern to
 * ensure a single instance of the class handles all AJAX requests efficiently.
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

/**
 * Check if the class `AJAX` is defined, and if not, define it.
 */
if ( ! class_exists( __NAMESPACE__ . '\\AJAX' ) ) :

	class AJAX {

		/**
		 * The single instance of the class.
		 *
		 * @var AJAX|null
		 */
		private static ?AJAX $instance = null;

		/**
		 * Constructor.
		 */
		private function __construct() {

			add_action( 'wp_ajax_inline_list_table_ordering', array( $this, 'ajax_post_ordering' ) );

			add_action( 'wp_ajax_inline_list_table_reset_ordering', array( $this, 'ajax_reset_post_ordering' ) );

			add_action( 'wp_ajax_inline_list_table_delete_trash', array( $this, 'ajax_delete_post_trash' ) );

		}

		/** Singleton *****************************************************************/

		/**
		 * Gets the single instance of the class.
		 *
		 * @return AJAX
		 */
		public static function get_instance(): ?AJAX {
			if ( self::$instance === null ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Prevent cloning.
		 */
		private function __clone() {
		}

		/**
		 * Prevent unserialization.
		 */
		private function __wakeup() {
		}

		/** AJAX **********************************************************************/

		/**
		 * AJAX callback for post ordering.
		 */
		public function ajax_post_ordering() {

			// check and make sure we have what we need
			if ( empty( $_POST['id'] ) || ( ! isset( $_POST['previd'] ) && ! isset( $_POST['nextid'] ) ) ) {
				die( - 1 );
			}

			$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ) : '';

			if ( ! wp_verify_nonce( $nonce, 'cpt-list-table-ordering-nonce' ) ) {
				die( - 1 );
			}

			$post_id  = empty( $_POST['id'] ) ? false : (int) $_POST['id'];
			$previd   = empty( $_POST['previd'] ) ? false : (int) $_POST['previd'];
			$nextid   = empty( $_POST['nextid'] ) ? false : (int) $_POST['nextid'];
			$start    = empty( $_POST['start'] ) ? 1 : (int) $_POST['start'];
			$excluded = empty( $_POST['excluded'] ) ? array( $_POST['id'] ) : array_filter( (array) json_decode( $_POST['excluded'] ), 'intval' );

			// real post?
			$post = empty( $post_id ) ? false : get_post( (int) $post_id );
			if ( ! $post ) {
				die( - 1 );
			}

			// does user have the right to manage these post objects?
			if ( ! check_edit_others_caps( $post->post_type ) ) {
				die( - 1 );
			}

			$result = self::page_ordering( $post_id, $previd, $nextid, $start, $excluded );

			if ( is_wp_error( $result ) ) {
				die( - 1 );
			}

			die( wp_json_encode( $result ) );
		}

		/**
		 * AJAX callback for resetting post ordering.
		 */
		public function ajax_reset_post_ordering() {
			// Implementation
			global $wpdb;

			$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ) : '';

			if ( ! wp_verify_nonce( $nonce, 'cpt-list-table-ordering-nonce' ) ) {
				die( - 1 );
			}

			// check and make sure we have what we need
			$post_type = isset( $_POST['post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) : '';

			if ( empty( $post_type ) ) {
				die( - 1 );
			}

			// does user have the right to manage these post objects?
			if ( ! check_edit_others_caps( $post_type ) ) {
				die( - 1 );
			}

			// reset the order of all posts of given post type
			$wpdb->update( 'wp_posts', array( 'menu_order' => 0 ), array( 'post_type' => $post_type ), array( '%d' ), array( '%s' ) );

			die( 0 );
		}

		/**
		 * Page ordering reset ajax callback
		 *
		 * @return void
		 */
		public function ajax_delete_post_trash() {
			global $wpdb;

			$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ) : '';

			if ( ! wp_verify_nonce( $nonce, 'cpt-list-table-ordering-nonce' ) ) {
				die( - 1 );
			}

			// check and make sure we have what we need
			$post_type = isset( $_POST['post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) : '';

			if ( empty( $post_type ) ) {
				die( - 1 );
			}

			// does user have the right to manage these post objects?
			if ( ! check_delete_others_caps( $post_type ) ) {
				die( - 1 );
			}

			// Delete all posts of the given post type that are in the trash.
			$wpdb->delete( $wpdb->posts, array( 'post_type' => $post_type, 'post_status' => 'trash' ), array(
				'%s',
				'%s'
			) );

			die( 0 );
		}

		/** Page Ordering *************************************************************/

		/**
		 * Page ordering function
		 *
		 * @param int   $post_id  The post ID.
		 * @param int   $previd   The previous post ID.
		 * @param int   $nextid   The next post ID.
		 * @param int   $start    The start index.
		 * @param array $excluded Array of post IDs.
		 *
		 * @return object|\WP_Error|"children"
		 */
		public static function page_ordering( int $post_id, int $previd, int $nextid, int $start, array $excluded ) {
			// real post?
			$post = empty( $post_id ) ? false : get_post( (int) $post_id );
			if ( ! $post ) {
				return new \WP_Error( 'invalid', __( 'Missing mandatory parameters.', 'simple-page-ordering' ) );
			}

			// Badly written plug-in hooks for save post can break things.
			if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
				error_reporting( 0 ); // phpcs:ignore
			}

			global $wp_version;

			$previd   = empty( $previd ) ? false : (int) $previd;
			$nextid   = empty( $nextid ) ? false : (int) $nextid;
			$start    = empty( $start ) ? 1 : (int) $start;
			$excluded = empty( $excluded ) ? array( $post_id ) : array_filter( (array) $excluded, 'intval' );

			$new_pos     = array(); // store new positions for ajax
			$return_data = new \stdClass();

			do_action( 'cpt_inline_list_table_pre_order_posts', $post, $start );

			// attempt to get the intended parent... if either sibling has a matching parent ID, use that
			$parent_id        = $post->post_parent;
			$next_post_parent = $nextid ? wp_get_post_parent_id( $nextid ) : false;

			if ( $previd === $next_post_parent ) {    // if the preceding post is the parent of the next post, move it inside
				$parent_id = $next_post_parent;
			} elseif ( $next_post_parent !== $parent_id ) {  // otherwise, if the next post's parent isn't the same as our parent, we need to study
				$prev_post_parent = $previd ? wp_get_post_parent_id( $previd ) : false;
				if ( $prev_post_parent !== $parent_id ) {    // if the previous post is not our parent now, make it so!
					$parent_id = ( false !== $prev_post_parent ) ? $prev_post_parent : $next_post_parent;
				}
			}

			// if the next post's parent isn't our parent, it might as well be false (irrelevant to our query)
			if ( $next_post_parent !== $parent_id ) {
				$nextid = false;
			}

			$max_sortable_posts = (int) apply_filters( 'cpt_inline_list_table_limit', 50 );    // should reliably be able to do about 50 at a time

			if ( $max_sortable_posts < 5 ) {    // don't be ridiculous!
				$max_sortable_posts = 50;
			}

			// we need to handle all post stati, except trash (in case of custom stati)
			$post_stati = get_post_stati(
				array(
					'show_in_admin_all_list' => true,
				)
			);

			$siblings_query = array(
				'depth'                  => 1,
				'posts_per_page'         => $max_sortable_posts,
				'post_type'              => $post->post_type,
				'post_status'            => $post_stati,
				'post_parent'            => $parent_id,
				'post__not_in'           => $excluded,
				'orderby'                => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
				'suppress_filters'       => true,
				'ignore_sticky_posts'    => true,
			);

			if ( version_compare( $wp_version, '4.0', '<' ) ) {
				$siblings_query['orderby'] = 'menu_order title';
				$siblings_query['order']   = 'ASC';
			}

			$siblings = new \WP_Query( $siblings_query ); // fetch all the siblings (relative ordering)

			// don't waste overhead of revisions on a menu order change (especially since they can't *all* be rolled back at once)
			remove_action( 'post_updated', 'wp_save_post_revision' );

			foreach ( $siblings->posts as $sibling ) :
				// don't handle the actual post
				if ( $sibling->ID === $post->ID ) {
					continue;
				}

				// if this is the post that comes after our repositioned post, set our repositioned post position and increment menu order
				if ( $nextid === $sibling->ID ) {
					wp_update_post(
						array(
							'ID'          => $post->ID,
							'menu_order'  => $start,
							'post_parent' => $parent_id,
						)
					);

					$ancestors            = get_post_ancestors( $post->ID );
					$new_pos[ $post->ID ] = array(
						'menu_order'  => $start,
						'post_parent' => $parent_id,
						'depth'       => count( $ancestors ),
					);

					$start ++;
				}

				// if repositioned post has been set, and new items are already in the right order, we can stop
				if ( isset( $new_pos[ $post->ID ] ) && $sibling->menu_order >= $start ) {
					$return_data->next = false;
					break;
				}

				// set the menu order of the current sibling and increment the menu order
				if ( $sibling->menu_order !== $start ) {
					wp_update_post(
						array(
							'ID'         => $sibling->ID,
							'menu_order' => $start,
						)
					);
				}
				$new_pos[ $sibling->ID ] = $start;
				$start ++;

				if ( ! $nextid && $previd === $sibling->ID ) {
					wp_update_post(
						array(
							'ID'          => $post->ID,
							'menu_order'  => $start,
							'post_parent' => $parent_id,
						)
					);

					$ancestors            = get_post_ancestors( $post->ID );
					$new_pos[ $post->ID ] = array(
						'menu_order'  => $start,
						'post_parent' => $parent_id,
						'depth'       => count( $ancestors ),
					);
					$start ++;
				}

			endforeach;

			// max per request
			if ( ! isset( $return_data->next ) && $siblings->max_num_pages > 1 ) {
				$return_data->next = array(
					'id'       => $post->ID,
					'previd'   => $previd,
					'nextid'   => $nextid,
					'start'    => $start,
					'excluded' => array_merge( array_keys( $new_pos ), $excluded ),
				);
			} else {
				$return_data->next = false;
			}

			do_action( 'cpt_inline_list_table_ordered_posts', $post, $new_pos );

			if ( ! $return_data->next ) {
				// if the moved post has children, we need to refresh the page (unless we're continuing)
				$children = new \WP_Query(
					array(
						'posts_per_page'         => 1,
						'post_type'              => $post->post_type,
						'post_status'            => $post_stati,
						'post_parent'            => $post->ID,
						'fields'                 => 'ids',
						'update_post_term_cache' => false,
						'update_post_meta_cache' => false,
						'ignore_sticky'          => true,
						'no_found_rows'          => true,
					)
				);

				if ( $children->have_posts() ) {
					return 'children';
				}
			}

			$return_data->new_pos = $new_pos;

			return $return_data;
		}

	}

endif;