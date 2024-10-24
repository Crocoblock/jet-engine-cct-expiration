<?php

namespace Jet_Engine_CCT_Expiration;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Main {

	private static $instance = null;

	public $hook = 'je_cct_expiration_cron_event';

	public function __construct() {
		add_action( 'jet-engine/custom-content-types/edit-type/custom-settings', array( $this, 'controls' ), 999 );
		add_filter( 'jet-engine/custom-content-types/data/sanitized-request', array( $this, 'request' ), 999, 2 );
		add_action( $this->hook, array( $this, 'actions' ), 999 );
		$this->set_cron();
		register_deactivation_hook( JET_CCT_EXP__FILE__, array( $this, 'unset_cron' ) );
	}

	public function controls() {
		?>
		<cx-vui-switcher
			label="<?php esc_html_e( 'Atomatically update items after some period of time', 'jet-engine' ); ?>"
			description="<?php esc_html_e( 'Enable this option if you want automatically delete, change status or change any fields values after selected period of time.', 'jet-engine' ); ?>"
			:wrapper-css="[ 'equalwidth' ]"
			v-model="generalSettings.auto_update"
		></cx-vui-switcher>
		<cx-vui-input
			label="<?php esc_html_e( 'Time period (days)', 'jet-engine' ); ?>"
			description="<?php esc_html_e( 'Set the number of days for expiration.', 'jet-engine' ); ?>"
			:wrapper-css="[ 'equalwidth' ]"
			:size="'fullwidth'"
			v-model="generalSettings.auto_update_period"
			:conditions="[
				{
					'input':   generalSettings.auto_update,
					'compare': 'equal',
					'value':   true,
				}
			]"
		></cx-vui-input>
		<cx-vui-select
			label="<?php esc_html_e( 'Update type', 'jet-engine' ); ?>"
			description="<?php esc_html_e( 'Select the type of expiration event.', 'jet-engine' ); ?>"
			:wrapper-css="[ 'equalwidth' ]"
			size="fullwidth"
			:options-list="[
						{
							value: 'draft',
							label: 'Set to draft',
						},
						{
							value: 'delete',
							label: 'Delete Item',
						},
						{
							value: 'update_fields',
							label: 'Change selected fields values',
						},
					]"
			v-model="generalSettings.auto_update_type"
			:conditions="[
				{
					'input':   generalSettings.auto_update,
					'compare': 'equal',
					'value':   true,
				}
			]"
		></cx-vui-select>
		<cx-vui-textarea
		label="<?php esc_html_e( 'Fields to update', 'jet-engine' ); ?>"
		description="<?php esc_html_e( 'One option per line. Allowed format:<br>field_name=value', 'jet-engine' ); ?>"
		:wrapper-css="[ 'equalwidth' ]"
		size="fullwidth"
		v-model="generalSettings.auto_update_fields"
		:conditions="[
				{
					'input':   generalSettings.auto_update_type,
					'compare': 'equal',
					'value':   'update_fields',
				},
				{
					'input':   generalSettings.auto_update,
					'compare': 'equal',
					'value':   true,
				}
			]"
	></cx-vui-textarea>
		<?php
	}

	public function request( $sanitized_request, $raw_request ) {

		$boolean_args = array(
			'auto_update' => 'false',
		);

		$default_args = array(
			'auto_update_period' => '30',
			'auto_update_type'   => 'draft',
			'auto_update_fields' => '',
		);

		foreach ( $boolean_args as $key => $default ) {
			$sanitized_request['args'][ $key ] = ! empty( $raw_request['args'][ $key ] ) ? filter_var( $raw_request['args'][ $key ], FILTER_VALIDATE_BOOLEAN ) : $default;
		}

		foreach ( $default_args as $key => $default ) {
			$sanitized_request['args'][ $key ] = ! empty( $raw_request['args'][ $key ] ) ? $raw_request['args'][ $key ] : $default;
		}

		return $sanitized_request;
	}


	public function get_updated_types() {

		$updated_types = array();

		if ( ! empty( $this->updated_types ) ) {
			return $this->updated_types;
		}

		$ccts = jet_engine()->modules->get_module( 'custom-content-types' )->instance->manager->get_content_types();

		foreach ( $ccts as $slug => $cct ) {

			$period = $cct->args['auto_update_period'];

			if ( ! isset( $cct->args['auto_update'] ) || ! filter_var( $cct->args['auto_update'], FILTER_VALIDATE_BOOLEAN ) ) {
				unset( $ccts[ $slug ] );
				continue;
			}

			$updated_types[ $slug ]['action'] = $cct->args['auto_update_type'];
			$updated_types[ $slug ]['items']  = $this->get_items( $slug, $period );

			if ( ! empty( $this->parse_options( $cct->args ) && 'update_fields' === $updated_types[ $slug ]['action'] ) ) {
				foreach ( $updated_types[ $slug ]['items'] as $i => $item ) {
					$updated_types[ $slug ]['items'][ $i ]['fields'] = $this->parse_options( $cct->args );
				}
			}
		}

		return $updated_types;
	}

	public function get_items( $slug, $period ) {

		if ( ! is_numeric( $period ) ) {
			$period = '30';
		}

		$period_str      = $period * 86400;
		$today           = strtotime( 'now' );
		$expiration_date = $today - $period_str;
		$datetime        = gmdate( 'Y-m-d H:i:s', $expiration_date );

		$type_object = \Jet_Engine\Modules\Custom_Content_Types\Module::instance()->manager->get_content_types( $slug );
		$args        = $type_object->prepare_query_args( array(
			array(
				'field'    => 'cct_created',
				'operator' => '<=',
				'value'    => $datetime,
				'type'     => 'CHAR',
			),
		) );

		$limit  = 999;
		$offset = 0;
		$order  = array();
		$rel    = 'AND';

		return $type_object->db->query( $args, $limit, $offset, $order, $rel );
	}

	public function actions() {

		$items_for_action = $this->get_updated_types();

		foreach ( $items_for_action as $cct => $args ) {

			switch ( $args['action'] ) {
				case 'draft':
					foreach ( $args['items'] as $index => $item ) {
						$this->get_handler( $item['cct_slug'] )->update_item( array(
							'_ID' => $item['_ID'],
							'cct_status' => 'draft',
						) );
					}
					break;
				case 'delete':
					foreach ( $args['items'] as $index => $item ) {
						$this->get_handler( $item['cct_slug'] )->raw_delete_item( $item['_ID'] );
					}
					break;
				case 'update_fields':
					foreach ( $args['items'] as $item ) {

						$new_item['_ID'] = $item['_ID'];

						foreach ( $item['fields'] as $group ) {
							$new_item[ $group['key'] ] = $group['value'];
						}

						$this->get_handler( $item['cct_slug'] )->update_item( $new_item );
					}
					break;
			}
		}
	}

	public function get_handler( $slug ) {
		$type_object = \Jet_Engine\Modules\Custom_Content_Types\Module::instance()->manager->get_content_types( $slug );
		$handler     = $type_object->get_item_handler();
		return $handler;
	}

	public function parse_options( $field ) {

		$raw = ! empty( $field['auto_update_fields'] ) ? $field['auto_update_fields'] : '';

		if ( empty( $raw ) ) {
			return;
		}

		$result = [];

		$raw = preg_split( '/\r\n|\r|\n/', $raw );

		if ( empty( $raw ) ) {
			return $result;
		}

		foreach ( $raw as $value ) {
			$parsed_value = explode( '=', trim( $value ) );
			$result[] = array(
				'key'        => $parsed_value[0],
				'value'      => isset( $parsed_value[1] ) ? $parsed_value[1] : $parsed_value[0],
			);
		}

		return $result;
	}

	public function set_cron() {

		if ( ! wp_next_scheduled( $this->hook ) ) {
			wp_schedule_event( time(), 'daily', $this->hook );
		}
	}

	public function unset_cron() {

		if ( ! wp_next_scheduled( $this->hook ) ) {
			return;
		}
		wp_unschedule_event( wp_next_scheduled( $this->hook ), $this->hook );
	}

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}

Main::instance();
