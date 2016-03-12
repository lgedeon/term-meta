<?php
/**
 * Plugin Name: Term Meta Polyfill ( aka Taxonomy Meta )
 * Plugin URL: https://github.com/lgedeon/term-meta
 * Description: Term Meta for WordPress without adding or modifying tables. Adds term meta to terms of select taxonomies. This is achieved by pairing a custom-post-type post with each registered taxonomy. The functions are designed to be forward compatible. So as parts of the term meta added to core https://core.trac.wordpress.org/ticket/10142 functions in this plugin can be updated and eventually replaced.
 * Version:     0.2
 * Author:      lgedeon, ericmann, 10up
 */

require_once ( 'term-data-store/term-data-store.php' );
require_once ( 'class-term-meta.php' );
require_once ( 'class-ui.php');
//require_once ( 'class-custom-fields.php');


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
if ( ! function_exists( 'register_term_meta_taxonomy' ) ) {
	function register_term_meta_taxonomy( $taxonomy ) {
		$term_meta = Term_Meta::instance();
		return $term_meta->register_term_meta_taxonomy( $taxonomy );
	}
}

/**
 * Add meta data field to a term using the taxonomy and term names.
 *
 * Migration notes: Use new method if available.
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
if ( ! function_exists( 'add_term_meta_by_taxonomy' ) ) {
	function add_term_meta_by_taxonomy( $taxonomy, $term, $meta_key, $meta_value, $unique = false ) {
		$term_meta = Term_Meta::instance();

		if ( function_exists( 'add_term_meta' ) ) {
			$term_id = $term_meta->get_term_meta_id( $taxonomy, $term );
			return add_term_meta( $term_id, $meta_key, $meta_value, $unique );
		}

		elseif ( $term_id = $term_meta->get_term_meta_post_id( $taxonomy, $term ) ) {
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
 * Migration notes: If we need to remove an old value remove it from both storage methods.
 *
 * @uses Term_Meta
 *
 * @param string $taxonomy Taxonomy name.
 * @param string $term Term name.
 * @param string $meta_key Metadata name.
 * @param mixed $meta_value Optional. Metadata value.
 * @return bool True on success, false on failure.
 */
if ( ! function_exists( 'delete_term_meta_by_taxonomy' ) ) {
	function delete_term_meta_by_taxonomy( $taxonomy, $term, $meta_key, $meta_value = '' ) {
		$term_meta = Term_Meta::instance();
		$success = false;

		if ( function_exists( 'delete_metadata' ) && $term_id = $term_meta->get_term_meta_id( $taxonomy, $term ) ) {
			$success = delete_metadata( 'term', $term_id, $meta_key, $meta_value );
		}

		if ( $term_id = $term_meta->get_term_meta_post_id( $taxonomy, $term ) ) {
			$success = delete_metadata( 'post', $term_id, $meta_key, $meta_value ) || $success;
		}

		return $success;
	}
}

/**
 * Retrieve post meta field for a term, using the taxonomy and term names.
 *
 * Migration notes: Use new method if available. If not available or nothing returned, use old.
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
if ( ! function_exists( 'get_term_meta_by_taxonomy' ) ) {
	function get_term_meta_by_taxonomy( $taxonomy, $term, $meta_key = '', $single = false) {
		$term_meta = Term_Meta::instance();

		if ( function_exists( 'get_term_meta' ) ) {
			$term_id = $term_meta->get_term_meta_id( $taxonomy, $term );
			if ( $value = get_term_meta( $term_id, $meta_key, $single ) ) {
				return $value;
			}
		}

		if ( $term_id = $term_meta->get_term_meta_post_id( $taxonomy, $term ) ) {
			return get_metadata( 'post', $term_id, $meta_key, $single );
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
 * Migration notes: Use new method if available.
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
if ( ! function_exists( 'update_term_meta_by_taxonomy' ) ) {
	function update_term_meta_by_taxonomy( $taxonomy, $term, $meta_key, $meta_value, $prev_value = '') {
		$term_meta = Term_Meta::instance();

		if ( function_exists( 'update_term_meta' ) ) {
			$term_id = $term_meta->get_term_meta_id( $taxonomy, $term );
			return update_term_meta( $term_id, $meta_key, $meta_value, $prev_value );
		}

		elseif ( $term_id = $term_meta->get_term_meta_post_id( $taxonomy, $term ) ) {
			return update_metadata( 'post', $term_id, $meta_key, $meta_value, $prev_value );
		}
	}
}

/**
 * Once the new term meta is available, switch all old meta to new.
 */
function term_meta_migrate () {
	// Check for the database version in core that has the table setup needed for the new term meta to work.
	// Have to check db version because functions exist before update has run and if we get called in that gap....
	if ( get_option( 'db_version' ) < 34370 ) {
		return false;
	}

	$term_meta = Term_Meta::instance();

	// get post_types that were used for term meta
	$post_types = $term_meta->get_term_meta_post_types( true );

	// get a list of posts that were used for storing term meta, 100 at a time to avoid timeout
	$wp_query = new WP_Query( array( 'post_type' => $post_types, 'fields' => 'ids', 'posts_per_page' => 100 ) );

	foreach ( $wp_query->posts as $post_id ) {
		$term = Term_Data_Store\get_related_term( $post_id );
		$meta = get_post_meta( $post_id );

		foreach ( $meta as $key => $value ) {
			/*
			 * We didn't specify a metakey in get_post_meta so that we could get all key/values, but that means that all
			 * values are returned inside arrays. We are assuming that values are single and extracting the 1st value.
			 */
			update_term_meta( $term->term_id, $key, $value[0] );
		}

		// cleanup: remove posts that are no longer needed
		wp_delete_post( $post_id, true );
	}

}
add_action( 'gobutton_clicked', 'term_meta_migrate' );

