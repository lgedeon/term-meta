<?php
/**
 * Plugin Name: Term Meta Polyfill
 * Plugin URL: https://github.com/lgedeon/term-meta
 * Description: Term Meta for WordPress without adding or modifying tables. Adds term meta to terms of select taxonomies. This is achieved by pairing a custom-post-type post with each registered taxonomy. The functions are designed to be forward compatible. So as parts of the term meta added to core https://core.trac.wordpress.org/ticket/10142 functions in this plugin can be updated and eventually replaced.
 * Version:     0.2
 * Author:      lgedeon, ericmann, 10up
 */

require_once ( 'term-data-store/term-data-store.php' );
require_once ( 'class-term-meta.php' );

/**
 * Pre-register taxonomies that should have term meta.
 *
 * This paired with filtering term_meta_allow_late_registration to return false will restrict term meta to only specific
 * taxonomies.
 *
 * This implementation is designed to be forward compatible with expected changes to WordPress core and will be much
 * more efficient then. For now, limiting term meta to taxonomies that need it could save some overhead.
 *
 * @param $taxonomy
 * @return bool
 */
if ( ! function_exists( 'register_meta_taxonomy' ) ) {
	function register_meta_taxonomy( $taxonomy ) {
		$term_meta = Term_Meta::instance();
		return $term_meta->register_meta_taxonomy( $taxonomy );
	}
}

/**
 * Add meta data field to a term using the taxonomy and term names.
 *
 * @uses Term_Meta
 *
 * @param string $taxonomy Taxonomy name.
 * @param string $term Term name.
 * @param string $meta_key Metadata name.
 * @param mixed $meta_value Metadata value.
 * @param bool $unique Optional, default is false. Whether the same key should not be added.
 * @return int|bool Meta ID on success, false on failure.
 */
if ( ! function_exists( 'add_term_meta' ) ) {
	function add_term_meta( $taxonomy, $term, $meta_key, $meta_value, $unique = false ) {
		$term_meta = Term_Meta::instance();
		if ( $term_id = $term_meta->get_taxonomy_term_id( $taxonomy, $term ) ) {
			return add_metadata('post', $term_id, $meta_key, $meta_value, $unique);
		}
	}
}


/**
 * Remove term meta matching given criteria using the taxonomy and term names.
 *
 * You can match based on the meta key, or key and value. Removing based on key and
 * value, will keep from removing duplicate metadata with the same key. It also
 * allows removing all metadata matching key, if needed.
 *
 * @uses Term_Meta
 *
 * @param string $taxonomy Taxonomy name.
 * @param string $term Term name.
 * @param string $meta_key Metadata name.
 * @param mixed $meta_value Optional. Metadata value.
 * @return bool True on success, false on failure.
 */
if ( ! function_exists( 'delete_term_meta' ) ) {
	function delete_term_meta( $taxonomy, $term, $meta_key, $meta_value = '' ) {
		$term_meta = Term_Meta::instance();
		if ( $term_id = $term_meta->get_taxonomy_term_id( $taxonomy, $term ) ) {
			return delete_metadata( 'post', $term_id, $meta_key, $meta_value );
		}
	}
}

/**
 * Retrieve post meta field for a term, using the taxonomy and term names.
 *
 * @uses Term_Meta
 *
 * @param string $taxonomy Taxonomy name.
 * @param string $term Term name.
 * @param string $key Optional. The meta key to retrieve. By default, returns data for all keys.
 * @param bool $single Whether to return a single value.
 * @return mixed Will be an array if $single is false. Will be value of meta data field if $single
 *  is true.
 */
if ( ! function_exists( 'get_term_meta' ) ) {
	function get_term_meta( $taxonomy, $term, $key = '', $single = false) {
		$term_meta = Term_Meta::instance();
		if ( $term_id = $term_meta->get_taxonomy_term_id( $taxonomy, $term ) ) {
			return get_metadata('post', $term_id, $key, $single);
		}
	}
}

/**
 * Update post meta field based on new style term ID, using the taxonomy and term names.
 *
 * Use the $prev_value parameter to differentiate between meta fields with the
 * same key and post ID.
 *
 * If the meta field for the post does not exist, it will be added.
 *
 * @uses Term_Meta
 *
 * @param string $taxonomy Taxonomy name.
 * @param string $term Term name.
 * @param string $meta_key Metadata key.
 * @param mixed $meta_value Metadata value.
 * @param mixed $prev_value Optional. Previous value to check before removing.
 * @return bool True on success, false on failure.
 */
if ( ! function_exists( 'update_term_meta' ) ) {
	function update_term_meta( $taxonomy, $term, $meta_key, $meta_value, $prev_value = '') {
		$term_meta = Term_Meta::instance();
		if ( $term_id = $term_meta->get_taxonomy_term_id( $taxonomy, $term ) ) {
			return update_metadata('post', $term_id, $meta_key, $meta_value, $prev_value );
		}
	}
}