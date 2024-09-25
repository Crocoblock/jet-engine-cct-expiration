<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Jet_Engine_CCT_Expiration_ {

    public static $instance = null;

    public function __construct() {
        add_action( 'jet-engine/custom-content-types/edit-type/custom-settings', array( $this, 'controls' ), 999 ); 
		add_filter( 'jet-engine/custom-content-types/data/sanitized-request', array( $this, 'request' ), 999, 2 );
    }

    public function controls() {
        ?>
        <cx-vui-switcher
            label="<?php _e( 'Atomatically update items after some period of time', 'jet-engine' ); ?>"
            description="<?php _e( 'Enable this option if you want automatically delete, change status or change any fields values after selected period of time.', 'jet-engine' ); ?>"
            :wrapper-css="[ 'equalwidth' ]"
            v-model="generalSettings.auto_update"
        ></cx-vui-switcher>
        <cx-vui-input
			label="<?php _e( 'Time period (days)', 'jet-engine' ); ?>"
			description="<?php _e( 'Set the number of days for expiration.', 'jet-engine' ); ?>"
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
            label="<?php _e( 'Update type', 'jet-engine' ); ?>"
            description="<?php _e( 'Select the type of expiration event.', 'jet-engine' ); ?>"
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
		label="<?php _e( 'Fields to update', 'jet-engine' ); ?>"
		description="<?php _e( 'One option per line. Allowed format:<br>field_name=value', 'jet-engine' ); ?>"
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
			$sanitized_request['args'][ $key ] = ! empty( $raw_request['args'][$key] ) ? filter_var( $raw_request['args'][ $key ], FILTER_VALIDATE_BOOLEAN ) : $default;
		}

		foreach ( $default_args as $key => $default ) {
			$sanitized_request['args'][ $key ] = ! empty( $raw_request['args'][$key] ) ? $raw_request['args'][ $key ] : $default;
		}

		//var_dump( $sanitized_request, $raw_request );
		return $sanitized_request;
	}

    public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

}

Jet_Engine_CCT_Expiration_::instance();
