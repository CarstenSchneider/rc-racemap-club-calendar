<?php
/**
 * Caching layer.
 *
 * A thin wrapper around the WordPress Transients API. Isolating it here means
 * the rest of the plugin never talks to transients directly, and the caching
 * strategy can be swapped (object cache, custom table, …) without touching
 * callers.
 *
 * @package RC_RaceMap_Club_Calendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class RC_RCC_Cache
 */
class RC_RCC_Cache {

	/**
	 * Prefix for every transient key created by this plugin.
	 */
	private const PREFIX = 'rc_rcc_';

	/**
	 * Option key holding the list of active cache keys (for targeted flush).
	 */
	private const INDEX_OPTION = 'rc_rcc_cache_index';

	/**
	 * Retrieve a cached value.
	 *
	 * @param string $key Logical cache key (without prefix).
	 * @return mixed|null Cached value, or null when missing/expired.
	 */
	public function get( string $key ) {
		$value = get_transient( $this->build_key( $key ) );

		return ( false === $value ) ? null : $value;
	}

	/**
	 * Store a value in the cache.
	 *
	 * @param string $key   Logical cache key (without prefix).
	 * @param mixed  $value Value to store.
	 * @param int    $ttl   Time to live in seconds. 0 = no expiration.
	 * @return void
	 */
	public function set( string $key, $value, int $ttl ): void {
		set_transient( $this->build_key( $key ), $value, max( 0, $ttl ) );
		$this->remember_key( $key );
	}

	/**
	 * Delete a single cached value.
	 *
	 * @param string $key Logical cache key (without prefix).
	 * @return void
	 */
	public function delete( string $key ): void {
		delete_transient( $this->build_key( $key ) );
		$this->forget_key( $key );
	}

	/**
	 * Remove every transient created by this plugin.
	 *
	 * @return void
	 */
	public function flush_all(): void {
		$index = get_option( self::INDEX_OPTION, array() );

		if ( is_array( $index ) ) {
			foreach ( $index as $key ) {
				delete_transient( $this->build_key( (string) $key ) );
			}
		}

		delete_option( self::INDEX_OPTION );
	}

	/**
	 * Build the fully-qualified, length-safe transient key.
	 *
	 * Transient keys are limited to 172 characters; hashing keeps us safe
	 * regardless of the logical key length (e.g. long club IDs).
	 *
	 * @param string $key Logical key.
	 * @return string
	 */
	private function build_key( string $key ): string {
		return self::PREFIX . md5( $key );
	}

	/**
	 * Track a key in the index so it can be flushed later.
	 *
	 * @param string $key Logical key.
	 * @return void
	 */
	private function remember_key( string $key ): void {
		$index = get_option( self::INDEX_OPTION, array() );
		if ( ! is_array( $index ) ) {
			$index = array();
		}

		if ( ! in_array( $key, $index, true ) ) {
			$index[] = $key;
			update_option( self::INDEX_OPTION, $index, false );
		}
	}

	/**
	 * Remove a key from the index.
	 *
	 * @param string $key Logical key.
	 * @return void
	 */
	private function forget_key( string $key ): void {
		$index = get_option( self::INDEX_OPTION, array() );
		if ( ! is_array( $index ) ) {
			return;
		}

		$index = array_values( array_diff( $index, array( $key ) ) );
		update_option( self::INDEX_OPTION, $index, false );
	}
}
