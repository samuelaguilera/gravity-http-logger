<?php
/**
 * List page for Gravity HTTP Logger Add-On.
 *
 * @package Gravity HTTP Logger
 * @author    Samuel Aguilera
 * @copyright Copyright (c) 2025 Samuel Aguilera
 */

defined( 'ABSPATH' ) || die();

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Extends WP core class.
 */
class Gravity_HTTP_Logger_List_Table extends WP_List_Table {

	private $table_name;

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . GRAVITY_HTTP_LOGGER_TABLE_NAME;

		parent::__construct(
			array(
				'singular' => 'request',
				'plural'   => 'requests',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Extract data from request headers.
	 */
	private function extract_headers( $value ) {
		$obj = maybe_unserialize( $value );

		if ( is_object( $obj ) ) {
			if ( method_exists( $obj, 'toArray' ) ) {
				return $obj->toArray();
			}
			$reflection = new ReflectionObject( $obj );
			foreach ( $reflection->getProperties() as $prop ) {
				$prop->setAccessible( true );
				$val = $prop->getValue( $obj );
				if ( is_array( $val ) ) {
					return $val;
				}
			}
		}

		if ( is_array( $obj ) ) {
			return $obj;
		}
	}

	private function render_json_preview( $json_value ) {
		$decoded = json_decode( $json_value, true );
		if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
			$preview   = '';
			$max_items = 5;
			$count     = 0;

			foreach ( $decoded as $key => $val ) {
				if ( $count >= $max_items ) {
					$preview .= '…';
					break;
				}
				if ( is_array( $val ) || is_object( $val ) ) {
					$val = wp_json_encode( $val, JSON_UNESCAPED_SLASHES );
				}
				$preview .= sprintf( '<strong>%s</strong>: %s<br>', esc_html( $key ), esc_html( wp_html_excerpt( $val, 80, '…' ) ) );
				++$count;
			}
			return sprintf(
				'<div style="max-height:100px;overflow:hidden;">%s</div> <button type="button" class="ghl-modal-btn button" data-content="%s">Modal View</button>',
				$preview,
				esc_attr( $json_value )
			);
		}

		$truncated = wp_html_excerpt( $json_value, 100, '…' );
		return sprintf(
			'<span>%s</span> <button type="button" class="ghl-modal-btn button" data-content="%s">Modal View</button>',
			esc_html( $truncated ),
			esc_attr( $json_value )
		);
	}

	private function render_request_args_preview( $json_value ) {
		return $this->render_json_preview( $json_value );
	}


	private function render_response_code( $code ) {
		$code  = (int) $code;
		$class = '';

		if ( $code >= 200 && $code < 300 ) {
			$class = 'ghl-http-200';
		} elseif ( $code >= 400 && $code < 500 ) {
			$class = 'ghl-http-400';
		} elseif ( $code >= 500 && $code < 600 ) {
			$class = 'ghl-http-500';
		}

		return sprintf( '<span class="%s">%d</span>', esc_attr( $class ), $code );
	}


	// Columns.

	public function column_request_args( $item ) {
		return $this->render_request_args_preview( $item['request_args'] ?? '' );
	}


	public function column_response_headers( $item ) {
		return $this->render_json_preview( $item['response_headers'] ?? '' );
	}

	public function column_response_body( $item ) {
		return $this->render_json_preview( $item['response_body'] ?? '' );
	}


	public function column_request_pattern( $item ) {
		return esc_html( $item['request_pattern'] ?? '(Empty)' );
	}

	public function column_request_url( $item ) {
		return esc_html( $item['request_url'] ?? '(Empty)' );
	}

	public function column_response_code( $item ) {
		return $this->render_response_code( $item['response_code'] ?? '(Empty)' );
	}

	public function column_response_message( $item ) {
		return esc_html( $item['response_message'] ?? '(Empty)' );
	}

	public function column_date_time( $item ) {
		return esc_html( get_date_from_gmt( $item['date_time'] ) ?? '(Empty)' );
	}

	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="request[]" value="%s" />', $item['id'] );
	}


	/**
	 * Columns for the table.
	 */
	public function get_columns() {
		return array(
			'cb'               => '<input type="checkbox" />',
			'request_pattern'  => 'Request String',
			'request_url'      => 'Request URL',
			'request_args'     => 'Request Args',
			'response_code'    => 'Response Code',
			'response_message' => 'Response Message',
			'response_headers' => 'Response Headers',
			'response_body'    => 'Response Body',
			'date_time'        => 'Date and Time',
		);
	}

	/**
	 * Sortable columns.
	 */
	public function get_sortable_columns() {
		return array(
			'request_pattern' => array( 'request_pattern', false ),
			'request_url'     => array( 'request_url', false ),
			'response_code'   => array( 'response_code', false ),
			'date_time'       => array( 'date_time', true ),
		);
	}

	/**
	 * Return available bulk actions.
	 */
	public function get_bulk_actions() {
		return array(
			'delete' => 'Delete Selected',
		);
	}

	/**
	 * Process bulk actions.
	 */
	public function process_bulk_action() {
		// Check nonce for security.
		if ( isset( $_POST['_wpnonce'] ) && ! wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'bulk-' . GRAVITY_HTTP_LOGGER_TABLE_NAME ) ) {
			wp_die( 'Security check failed.' );
		}

		if ( 'delete' === $this->current_action() ) {
			$ids = wp_unslash( $_REQUEST['request'] ) ?? array();
			if ( ! is_array( $ids ) ) {
				$ids = array( $ids );
			}
			foreach ( $ids as $id ) {
				self::delete_request( (int) $id );
			}
			echo '<div class="updated notice"><p>Selected request(s) successfully deleted.</p></div>';
		}
	}

	/**
	 * Delete request from database.
	 *
	 * @param int $id Id number for the request to delete.
	 */
	public static function delete_request( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . GRAVITY_HTTP_LOGGER_TABLE_NAME;
		$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore
	}

	/**
	 * Get request to display from database.
	 *
	 * @param int $per_page Number of requests per page.
	 * @param int $page_number Page number.
	 */
	public static function get_requests( $per_page = 10, $page_number = 1 ) {
		global $wpdb;
		$table  = $wpdb->prefix . GRAVITY_HTTP_LOGGER_TABLE_NAME;
		$offset = ( $page_number - 1 ) * $per_page;

		$search_for     = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$requests_order = isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : '';

		// Base query.
		$sql = "SELECT * FROM $table";

		// Search condition.
		if ( ! empty( $search_for ) ) {
			$search = '%' . $wpdb->esc_like( $search_for ) . '%';
			$sql   .= ' WHERE request_pattern LIKE %s OR request_url LIKE %s OR response_message LIKE %s OR response_code LIKE %s';
			$sql    = $wpdb->prepare( $sql, $search, $search, $search, $search );
		}

		// Sort.
		if ( ! empty( $_REQUEST['orderby'] ) ) {
			$orderby = esc_sql( wp_unslash( $_REQUEST['orderby'] ) );
			$order   = ! empty( $requests_order ) ? esc_sql( $requests_order ) : 'ASC';
			$sql    .= " ORDER BY $orderby $order";
		} else {
			$sql .= ' ORDER BY date_time DESC';
		}

		// Limit and pagination.
		$sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $per_page, $offset );

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	public static function record_count() {
		global $wpdb;
		$table = $wpdb->prefix . GRAVITY_HTTP_LOGGER_TABLE_NAME;

		$sql = "SELECT COUNT(*) FROM $table";

		if ( ! empty( $_REQUEST['s'] ) ) {
			$search = '%' . $wpdb->esc_like( wp_unslash( $_REQUEST['s'] ) ) . '%';
			$sql   .= ' WHERE request_pattern LIKE %s OR request_url LIKE %s OR response_message LIKE %s OR response_code LIKE %s';
			$sql    = $wpdb->prepare( $sql, $search, $search, $search, $search );
		}

		return $wpdb->get_var( $sql );
	}

	public function prepare_items() {
		$this->process_bulk_action();

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);

		$per_page = $this->get_items_per_page( 'requests_per_page', 10 );

		$current_page = $this->get_pagenum();
		$total_items  = self::record_count();

		$items = self::get_requests( $per_page, $current_page );

		// Format of retrieved data.
		foreach ( $items as &$item ) {
			$ra                   = maybe_unserialize( $item['request_args'] ?? '' );
			$item['request_args'] = is_array( $ra ) || is_object( $ra )
				? wp_json_encode( $ra, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
				: ( $item['request_args'] ?? '' );

			$rh                       = $this->extract_headers( $item['response_headers'] ?? '' );
			$item['response_headers'] = ! empty( $rh )
				? wp_json_encode( $rh, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
				: ( $item['response_headers'] ?? '' );

			$rb                    = $item['response_body'] ?? '';
			$decoded               = json_decode( $rb, true );
			$item['response_body'] = json_last_error() === JSON_ERROR_NONE
				? wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
				: $rb;

		}

		$this->items = $items;

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			)
		);
	}
}
