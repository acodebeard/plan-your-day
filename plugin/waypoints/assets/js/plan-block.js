( function( blocks, blockEditor, components, element, i18n ) {
	'use strict';

	var el = element.createElement;
	var Fragment = element.Fragment;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var Placeholder = components.Placeholder;
	var TextControl = components.TextControl;

	blocks.registerBlockType( 'waypoints/planner', {
		edit: function( props ) {
			var attributes = props.attributes || {};
			var blockProps = useBlockProps();

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{
							title: __( 'Planner settings', 'waypoints' ),
							initialOpen: true,
						},
						el( TextControl, {
							label: __( 'Action URL', 'waypoints' ),
							help: __( 'Optional. Submit planner updates to a specific page URL instead of the current page.', 'waypoints' ),
							value: attributes.actionUrl || '',
							onChange: function( value ) {
								props.setAttributes( { actionUrl: value } );
							},
						} )
					)
				),
				el(
					'div',
					blockProps,
					el(
						Placeholder,
						{
							label: __( 'Waypoints', 'waypoints' ),
							instructions: __( 'The frontend uses the same planner renderer as the shortcode. Configure an optional action URL in the block settings.', 'waypoints' ),
						},
						el(
							'p',
							null,
							__( 'Planner styles and interactions load only when this block is rendered on the page.', 'waypoints' )
						)
					)
				)
			);
		},
		save: function() {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
