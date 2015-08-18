<?php
/**
 * Add ui for adding and editing term meta based on the WordPress default for editing Custom Fields in the post editor.
 *
 * Works only on the edit screen.
 */
if ( ! class_exists( 'Term_Meta_Custom_Fields' ) ) {

	class Term_Meta_Custom_Fields {
		/**
		 * @var bool|Term_Meta_Custom_Fields
		 */
		protected static $_instance = false;

		/**
		 * Gets the singleton instance of this class - should only get constructed once.
		 *
		 * @return bool|Term_Meta_Custom_Fields
		 */
		public static function instance() {
			if ( ! self::$_instance ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

		/**
		 * @var array Taxonomies to add custom fields UI.
		 */
		protected $_taxonomies = array( 'category' );

		/**
		 * Constructor -  Wire up actions and filters
		 */
		protected function __construct() {
			add_action( 'init', array( $this, 'action__init' ), 11 );
		}

		public function action__init () {
			$taxonomies = (array) apply_filters( 'term_meta_custom_fields_taxonomies', array( 'category' ) );
			$this->_taxonomies = array_intersect( get_taxonomies(), $taxonomies );

			if ( ! empty( $this->_taxonomies) ) {
				foreach ( $this->_taxonomies as $taxonomy ) {
					Term_Meta::instance()->register_term_meta_taxonomy( $taxonomy );
				}
				add_action( 'add_meta_boxes', array( $this, 'action__add_meta_boxes' ) );
			}
		}

		public function action__add_meta_boxes ( $screen_id ) {
			$taxonomy = explode( 'edit-', $screen_id );
			if ( ! isset( $taxonomy[1] ) || ! in_array( $taxonomy[1], $this->_taxonomies ) ) {
				return;
			}

			require_once( ABSPATH . 'wp-admin/includes/meta-boxes.php' );
			add_meta_box( 'postcustom', __('Custom Fields'), 'post_custom_meta_box', $screen_id, 'meta-box' );

			// This makes the AJAX work.
			wp_enqueue_script( 'post' );
		}
	}

	Term_Meta_Custom_Fields::instance();
}

