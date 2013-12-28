<?php

if ( ! class_exists( 'Term_Meta' ) ) {

class Term_Meta {
	/**
	 * @var bool|Term_Meta
	 */
	protected static $_instance = false;

	/**
	 * @var int
	 */
	protected $_last_unique_term_id = null;

	/**
	 * Gets the singleton instance of this class - should only get constructed once.
	 *
	 * @return bool|Term_Meta
	 */
	public static function instance() {
		if ( ! self::$_instance ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * @var array
	 */
	protected $taxonomies = array();

	/**
	 * Constructor -  Wire up actions and filters
	 */
	protected function __construct() {
		add_filter( 'posts_results', array( $this, 'catch_missing_cpt' ), 10, 2 );
	}

	public function catch_missing_cpt ( $posts, $test_query ) {
		$query = $test_query->query;
		if ( empty ( $posts ) &&
			in_array( $query['post_type'], $this->taxonomies ) &&
			1    == $query['posts_per_page'] &&
			true == $query['ignore_sticky_posts'] &&
			true == $query['no_found_rows'] &&
			'id' == $query['tax_query'][0]['field'] &&
			is_int( $query['tax_query'][0]['terms'] )
		) {
			$term_id = $query['tax_query'][0]['terms'];
			$taxonomy = $query['tax_query'][0]['taxonomy'];
			$term = get_term( $term_id, $taxonomy );

			add_filter( 'tds_balancing_from_post', '__return_false' );
			$post_id = wp_insert_post( array(
				'post_type' => $query['post_type'],
				'post_title'  => $term->name,
				'post_name'   => $term->slug,
				'post_status' => 'publish',
			) );
			wp_set_object_terms( $post_id, $term_id, $taxonomy );
			remove_filter( 'tds_balancing_from_post', '__return_false' );

			$posts = array( get_post( $post_id ) );
			$test_query->posts = $posts;
		}

		return $posts;
	}

	/**
	 * This implementation is designed to be forward compatible with expected changes to WordPress core and will be much
	 * more efficient then. For now, only add term meta for taxonomies that need it.
	 *
	 * @param $taxonomy
	 * @param string $cpt_name
	 * @param bool $show_cpt_ui
	 * @param string $singular_name
	 * @return bool
	 */
	public function register_meta_taxonomy( $taxonomy, $cpt_name = '', $show_cpt_ui = false, $singular_name = '' ) {
		if ( $taxonomy == $cpt_name || ! taxonomy_exists( $taxonomy ) ) {
			return false;
		}

		if ( ! post_type_exists( $cpt_name ) ) {

			$cpt_name = $cpt_name ?: ucfirst( $taxonomy );
			$singular_name = $singular_name ?: $cpt_name;
			$slug = sanitize_key( $cpt_name );

			if ( $show_cpt_ui ) {
				$labels = array(
					'name'                       => $cpt_name,
					'singular_name'              => $singular_name,
					'search_items'               => "Search {$singular_name}",
					'popular_items'              => "Popular {$cpt_name}",
					'all_items'                  => "All {$cpt_name}",
					'parent_item'                => "Parent {$singular_name}",
					'parent_item_colon'          => "Parent {$singular_name}:",
					'edit_item'                  => "Edit {$singular_name}",
					'update_item'                => "Update {$singular_name}",
					'add_new'                    => "Add New",
					'add_new_item'               => "Add New {$singular_name}",
					'new_item_name'              => "New {$singular_name} Name",
					'separate_items_with_commas' => "Separate {$cpt_name} with commas",
					'add_or_remove_items'        => "Add or remove {$cpt_name}",
					'choose_from_most_used'      => "Choose from the most used {$cpt_name}",
					'not_found'                  => "No {$cpt_name} found.",
					'menu_name'                  => "{$cpt_name}",
					'new_item'                   => "New {$singular_name}",
					'view_item'                  => "View {$singular_name}",
					'not_found_in_trash'         => "No {$cpt_name} found in Trash",
				);

				$post_type_args = array(
					'show_ui'              => true,
					'show_in_menu'         => 'edit.php?post_type=' . $slug,
					'show_in_admin_bar'    => false,
					'register_meta_box_cb' => "{$slug}_taxonomy_meta_box",
					'supports'             => array( 'title' ),
					'labels'               => $labels,
				);
			} else {
				$slug = $slug . '_tax_meta';
				$post_type_args = array(
					'show_ui' => false,
					'rewrite' => false,
					'label'   => $cpt_name,
				);
			}

			register_post_type( $slug, $post_type_args );
		}

		Term_Data_Store\add_relationship( $slug, $taxonomy );
		$this->taxonomies[$taxonomy] = $slug;

	}

	/**
	 *  Get unique term ID
	 *
	 * Terms do not yet have unique IDs in WordPress core. The same term_id is used multiple times in different
	 * taxonomies.
	 *
	 * We solve this by using the post ID of the associated cpt we are using for meta storage. This function returns
	 * that unique key.
	 *
	 * @param string $taxonomy
	 * @param string $term
	 * @return bool|null|WP_Post
	 */
	public function get_unique_term_id( $taxonomy, $term = '' ) {
		$this->_last_unique_term_id = null;

		if ( ! array_key_exists( $taxonomy, $this->taxonomies ) ) {
			return false;
		}

		if ( is_int( $term ) ) {
			$term = get_term( $term, $taxonomy );
		} elseif ( is_string( $term ) ) {
			$term = get_term_by( 'name', $term, $taxonomy );
		}

		if ( is_wp_error( $term ) || ! is_object( $term ) || empty( $term->term_id ) ) {
			return false;
		}

		if ( $cpt_post = Term_Data_Store\get_related_post( $term, $taxonomy ) ) {
			$this->_last_unique_term_id = $cpt_post->ID;
			return $cpt_post->ID;
		}

		return false;
	}

	/**
	 * Thanks to old style term_id's this can be a bit tricky. We need to carefully check that we are getting
	 * the intended term. It is still possible to pass an old-style $term_id and by luck it will match a new term_id,
	 * but check
	 *
	 * @param int $maybe_unique_id
	 * @return bool
	 */
	public function check_unique_term_id( $maybe_unique_id ) {
		if ( $maybe_unique_id == $this->_last_unique_term_id  ) {
			return true;
		}

		if ( $post = get_post( $maybe_unique_id )
			&& in_array( $post->post_type, $this->taxonomies )
			&& $term = Term_Data_Store\get_related_term( $maybe_unique_id )
			&& $term->taxonomy == $this->taxonomies[$post->$post_type] )
			{
				$this->_last_unique_term_id = $maybe_unique_id;
				return true;
			}

		$this->_last_unique_term_id = null;
		return false;
	}



	/**
	 * Remove metadata matching criteria from a post.
	 *
	 * You can match based on the key, or key and value. Removing based on key and
	 * value, will keep from removing duplicate metadata with the same key. It also
	 * allows removing all metadata matching key, if needed.
	 *
	 * @uses Term_Meta
	 *
	 * @param int $term_id post ID
	 * @param string $meta_key Metadata name.
	 * @param mixed $meta_value Optional. Metadata value.
	 * @return bool True on success, false on failure.
	 */
	function delete_term_meta($term_id, $meta_key, $meta_value = '') {
		if ( ! $this->check_unique_term_id( $term_id ) ) {
			return false;
		}
		return delete_metadata('post', $term_id, $meta_key, $meta_value);
	}

	/**
	 * Retrieve post meta field for a post.
	 *
	 * @uses Term_Meta
	 *
	 * @param int $term_id Post ID.
	 * @param string $key Optional. The meta key to retrieve. By default, returns data for all keys.
	 * @param bool $single Whether to return a single value.
	 * @return mixed Will be an array if $single is false. Will be value of meta data field if $single
	 *  is true.
	 */
	function get_term_meta($term_id, $key = '', $single = false) {
		if ( ! $this->check_unique_term_id( $term_id ) ) {
			return false;
		}
		return get_metadata('post', $term_id, $key, $single);
	}

	/**
	 * Update post meta field based on post ID.
	 *
	 * Use the $prev_value parameter to differentiate between meta fields with the
	 * same key and post ID.
	 *
	 * If the meta field for the post does not exist, it will be added.
	 *
	 * @uses Term_Meta
	 *
	 * @param int $term_id Post ID.
	 * @param string $meta_key Metadata key.
	 * @param mixed $meta_value Metadata value.
	 * @param mixed $prev_value Optional. Previous value to check before removing.
	 * @return bool True on success, false on failure.
	 */
	function update_term_meta($term_id, $meta_key, $meta_value, $prev_value = '') {
		if ( ! $this->check_unique_term_id( $term_id ) ) {
			return false;
		}
		return update_metadata('post', $term_id, $meta_key, $meta_value, $prev_value);
	}

}

Term_Meta::instance();
}