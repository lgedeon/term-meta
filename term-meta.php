<?php
/**
 * Plugin Name: Term Meta
 * Plugin URL:
 * Description:
 * Version:     0.1
 * Author:      10up, lgedeon
 */

require_once ( 'term-data-store/term-data-store.php' );
require_once ( 'class-term-meta.php' );

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
if ( ! function_exists( 'register_meta_taxonomy' ) ) {
	function register_meta_taxonomy( $taxonomy, $cpt_name = '', $show_cpt_ui = false, $singular_name = '' ) {
		$term_meta = Term_Meta::instance();
		return $term_meta->register_meta_taxonomy( $taxonomy, $cpt_name, $show_cpt_ui, $singular_name );
	}
}

/**
 *  Get unique term ID
 *
 * Terms do not yet have unique IDs in WordPress core. The same term_id is used multiple times in different
 * taxonomies.
 *
 * We solve this by using the post ID of the associated cpt we are using for meta storage. This function returns
 * that unique key.
 */

/**
 * @param string $taxonomy
 * @param string $term
 * @return bool|null|WP_Post
 */
if ( ! function_exists( 'get_unique_term_id' ) ) {
	function get_unique_term_id( $taxonomy, $term = '' ) {
		$term_meta = Term_Meta::instance();
		return $term_meta->get_unique_term_id( $taxonomy, $term );
	}
}

/**
 * Add meta data field to a term.
 *
 * @uses Term_Meta
 *
 * @param int $term_id A new unique term ID distinct from the ones currently in core. Use get_unique_term_id() to get.
 * @param string $meta_key Metadata name.
 * @param mixed $meta_value Metadata value.
 * @param bool $unique Optional, default is false. Whether the same key should not be added.
 * @return int|bool Meta ID on success, false on failure.
 */
if ( ! function_exists( 'add_term_meta' ) ) {
	function add_term_meta( $term_id, $meta_key, $meta_value, $unique = false ) {
		$term_meta = Term_Meta::instance();
		if ( ! $term_meta->check_unique_term_id( $term_id ) ) {
			return false;
		}
		if ( $term_meta->check_unique_term_id( $term_id) ) {
			return add_metadata('post', $term_id, $meta_key, $meta_value, $unique);
		}
	}
}

/**
 * Remove metadata matching criteria from a term.
 *
 * You can match based on the key, or key and value. Removing based on key and
 * value, will keep from removing duplicate metadata with the same key. It also
 * allows removing all metadata matching key, if needed.
 *
 * @uses Term_Meta
 *
 * @param int $term_id A new unique term ID distinct from the ones currently in core. Use get_unique_term_id() to get.
 * @param string $meta_key Metadata name.
 * @param mixed $meta_value Optional. Metadata value.
 * @return bool True on success, false on failure.
 */
if ( ! function_exists( 'delete_term_meta' ) ) {
	function delete_term_meta( $term_id, $meta_key, $meta_value = '' ) {
		$term_meta = Term_Meta::instance();
		if ( ! $term_meta->check_unique_term_id( $term_id ) ) {
			return false;
		}
		if ( $term_meta->check_unique_term_id( $term_id) ) {
			return delete_metadata( 'post', $term_id, $meta_key, $meta_value );
		}
	}
}

/**
 * Retrieve post meta field for a term.
 *
 * @uses Term_Meta
 *
 * @param int $term_id A new unique term ID distinct from the ones currently in core. Use get_unique_term_id() to get.
 * @param string $key Optional. The meta key to retrieve. By default, returns data for all keys.
 * @param bool $single Whether to return a single value.
 * @return mixed Will be an array if $single is false. Will be value of meta data field if $single
 *  is true.
 */
if ( ! function_exists( 'get_term_meta' ) ) {
	function get_term_meta($term_id, $key = '', $single = false) {
		$term_meta = Term_Meta::instance();
		if ( ! $term_meta->check_unique_term_id( $term_id ) ) {
			return false;
		}
		if ( $term_meta->check_unique_term_id( $term_id) ) {
			return get_metadata('post', $term_id, $key, $single);
		}
	}
}

/**
 * Update post meta field based on new style term ID.
 *
 * Use the $prev_value parameter to differentiate between meta fields with the
 * same key and post ID.
 *
 * If the meta field for the post does not exist, it will be added.
 *
 * @uses Term_Meta
 *
 * @param int $term_id A new unique term ID distinct from the ones currently in core. Use get_unique_term_id() to get.
 * @param string $meta_key Metadata key.
 * @param mixed $meta_value Metadata value.
 * @param mixed $prev_value Optional. Previous value to check before removing.
 * @return bool True on success, false on failure.
 */
if ( ! function_exists( 'update_term_meta' ) ) {
	function update_term_meta($term_id, $meta_key, $meta_value, $prev_value = '') {
		$term_meta = Term_Meta::instance();
		if ( ! $term_meta->check_unique_term_id( $term_id ) ) {
			return false;
		}
		if ( $term_meta->check_unique_term_id( $term_id) ) {
			return update_metadata('post', $term_id, $meta_key, $meta_value, $prev_value);
		}
	}
}

function add_term_meta_by_name() {}
function delete_term_meta_by_name() {}
function get_term_meta_by_name() {}
function update_term_meta_by_name() {}
