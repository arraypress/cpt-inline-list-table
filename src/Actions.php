<?php
/**
 * This class introduces functionality to duplicate custom posts directly from the administrative interface, extending
 * the capabilities of the standard WP_List_Table. It hooks into the WordPress admin actions to provide a seamless
 * workflow for duplicating posts, including copying post data and metadata, setting the duplicate as a draft, and
 * ensuring user permissions are respected. The class implements a singleton pattern to ensure only one instance
 * exists, preventing unnecessary re-instantiations.
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

use function absint;
use function add_post_meta;
use function admin_url;
use function get_post;
use function get_post_meta;
use function wp_die;
use function wp_insert_post;
use function wp_redirect;
use function wp_verify_nonce;

/**
 * Check if the class `Actions` is defined, and if not, define it.
 */
if ( ! class_exists( __NAMESPACE__ . '\\Actions' ) ) :

	class Actions {

		/**
		 * The single instance of the class.
		 *
		 * Can be null if the instance has not been set yet.
		 *
		 * @var Actions|null
		 */
		private static ?Actions $instance = null;

		/**
		 * Constructor.
		 */
		private function __construct() {

			add_action( 'admin_action_duplicate_inline', [ $this, 'process_duplicate_post_action' ] );

		}

		/** Singleton *****************************************************************/

		/**
		 * Gets the single instance of the class.
		 *
		 * @return Actions
		 */
		public static function get_instance(): ?Actions {
			if ( self::$instance === null ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Prevent cloning.
		 */
		public function __clone() {
		}

		/**
		 * Prevent unserialization.
		 */
		public function __wakeup() {
		}

		/** Actions *******************************************************************/

		/**
		 * Duplicates a post based on its ID.
		 *
		 * This method duplicates the specified post, including its title, content, author,
		 * and custom meta fields, appending "(Copy)" to the end of the title to indicate
		 * it is a duplicate. The duplicate post will be set as a draft regardless of the
		 * original post's status. Permission checks ensure only users with the capability
		 * to edit others' posts of the specified type can duplicate the post. Upon successful
		 * duplication, the user is redirected to the edit page of the new post.
		 *
		 * @return void
		 */
		public function process_duplicate_post_action() {
			if ( isset( $_GET['action'] ) && 'duplicate_inline' == $_GET['action'] && isset( $_GET['post'] ) ) {
				$post_id = absint( $_GET['post'] );
				$nonce   = $_GET['_wpnonce'] ?? '';


				if ( ! wp_verify_nonce( $nonce, 'duplicate_post_' . $post_id ) ) {
					wp_die( 'Security check failed' );
				}

				$post = get_post( $post_id );
				if ( empty( $post ) ) {
					wp_die( 'Post does not exist' );
				}

				if ( ! check_edit_others_caps( $post->post_type ) ) {
					wp_die( 'You do not have permission to duplicate this post' );
				}

				$original_title = $post->post_title;
				$new_title      = $original_title . ' (Copy)';

				$args = [
					'post_title'   => $new_title,
					'post_content' => $post->post_content,
					'post_status'  => 'draft',
					'post_type'    => $post->post_type,
					'post_author'  => $post->post_author,
				];

				$new_post_id = wp_insert_post( $args );

				// Duplicate all post meta
				$post_meta = get_post_meta( $post_id );
				foreach ( $post_meta as $meta_key => $meta_values ) {
					foreach ( $meta_values as $meta_value ) {
						add_post_meta( $new_post_id, $meta_key, $meta_value );
					}
				}

				// Redirect to the edit page of the new post
				wp_redirect( admin_url( 'post.php?action=edit&post=' . absint( $new_post_id ) ) );
				exit;
			}
		}


	}

endif;