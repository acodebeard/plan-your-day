<?php
declare( strict_types=1 );

namespace Acodebeard\PlanYourDay\Frontend;

use Acodebeard\PlanYourDay\Planner\CategoryCatalog;
use Acodebeard\PlanYourDay\Planner\PlannerPayloadBuilder;
use Acodebeard\PlanYourDay\Planner\PlannerStateBuilder;
use Acodebeard\PlanYourDay\Planner\RequestStateParser;
use Acodebeard\PlanYourDay\Rest\PlannerRoutes;
use Acodebeard\PlanYourDay\Security\VisitorTokenManager;
use Acodebeard\PlanYourDay\Settings\Settings;

defined( 'ABSPATH' ) || exit;

final class PlannerRenderer {
	private Settings $settings;
	private CategoryCatalog $category_catalog;
	private RequestStateParser $request_state_parser;
	private PlannerStateBuilder $planner_state_builder;
	private PlannerPayloadBuilder $planner_payload_builder;
	private VisitorTokenManager $visitor_token_manager;

	public function __construct(
		Settings $settings,
		CategoryCatalog $category_catalog,
		RequestStateParser $request_state_parser,
		PlannerStateBuilder $planner_state_builder,
		PlannerPayloadBuilder $planner_payload_builder,
		VisitorTokenManager $visitor_token_manager
	) {
		$this->settings                = $settings;
		$this->category_catalog        = $category_catalog;
		$this->request_state_parser    = $request_state_parser;
		$this->planner_state_builder   = $planner_state_builder;
		$this->planner_payload_builder = $planner_payload_builder;
		$this->visitor_token_manager   = $visitor_token_manager;
	}

	public function render( array $request = [], string $action_url = '' ): string {
		$instance_id = wp_unique_id( 'plan-your-day-' );
		$title_id    = $instance_id . '-title';

		if ( ! $this->settings->has_required_settings() ) {
			return $this->render_setup_notice( $instance_id, $title_id );
		}

		$request_state       = $this->request_state_parser->parse( $request );
		$planner_state       = $this->planner_state_builder->build( $request_state );
		$category_catalog    = $this->category_catalog->get_all();
		$start_points        = $this->get_start_points();
		$results_empty_state = $this->planner_payload_builder->get_empty_results_state( $planner_state );
		$preview_empty_state = $this->planner_payload_builder->get_empty_preview_state( $planner_state );
		$action_url          = '' !== $action_url ? $action_url : $this->get_current_url();
		$form_action         = $action_url . '#' . $instance_id;
		$maps_link_enabled   = '' !== $planner_state['maps_url'];
		$endpoint_token      = $this->visitor_token_manager->get_endpoint_token();

		ob_start();
		?>
		<section
			class="plan-your-day dkc-plan"
			id="<?php echo esc_attr( $instance_id ); ?>"
			aria-labelledby="<?php echo esc_attr( $title_id ); ?>"
			data-plan-root>
			<div class="dkc-plan__surface">
				<header class="dkc-plan__hero">
					<div class="dkc-plan__hero-copy">
						<p class="dkc-plan__eyebrow"><?php esc_html_e( 'Trip builder', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></p>
						<h2 id="<?php echo esc_attr( $title_id ); ?>"><?php esc_html_e( 'Plan Your Day', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></h2>
						<p class="dkc-plan__intro">
							<?php esc_html_e( 'Search by category, choose real places from Google, then turn your picks into a walking trip.', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?>
						</p>
					</div>
				</header>

				<form
					class="dkc-plan__layout"
					method="get"
					action="<?php echo esc_url( $form_action ); ?>"
					data-plan-form>
					<div class="dkc-plan__controls">
						<?php
						$this->render_start_card( $instance_id, $planner_state, $start_points );
						$this->render_category_card( $instance_id, $planner_state, $category_catalog, $results_empty_state );
						?>
					</div>

					<div class="dkc-plan__preview-panel">
						<?php
						$this->render_trip_card( $instance_id, $planner_state );
						$this->render_preview_card( $instance_id, $planner_state, $preview_empty_state, $maps_link_enabled );
						?>
					</div>
				</form>
			</div>

			<script type="application/json" data-plan-config><?php echo wp_json_encode( $this->build_config( $instance_id, $action_url, $planner_state, $start_points, $category_catalog, $endpoint_token ) ); ?></script>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	private function render_setup_notice( string $instance_id, string $title_id ): string {
		$missing = implode( ', ', $this->settings->get_missing_required_settings() );

		ob_start();
		?>
		<section
			class="plan-your-day dkc-plan dkc-plan--setup-required"
			id="<?php echo esc_attr( $instance_id ); ?>"
			aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
			<div class="dkc-plan__surface">
				<h2 id="<?php echo esc_attr( $title_id ); ?>"><?php esc_html_e( 'Plan Your Day setup needed', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></h2>
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s is a comma-separated list of missing setting labels. */
							__( 'The planner needs required settings before it can render: %s.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
							$missing
						)
					);
					?>
				</p>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<p>
						<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . Settings::PAGE_SLUG ) ); ?>">
							<?php esc_html_e( 'Open Plan Your Day settings', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	private function render_start_card( string $instance_id, array $planner_state, array $start_points ): void {
		$start_help_id      = $instance_id . '-start-help';
		$start_panel_id     = $instance_id . '-start-panel';
		$custom_start_id    = $instance_id . '-custom-start';
		$start_heading_id   = $instance_id . '-start-heading';
		$selected_waypoints = (array) $planner_state['selected_waypoint_ids'];
		?>
		<section class="dkc-plan__card" aria-labelledby="<?php echo esc_attr( $start_heading_id ); ?>">
			<div class="dkc-plan__card-header">
				<div>
					<h3 id="<?php echo esc_attr( $start_heading_id ); ?>"><?php esc_html_e( 'Starting point', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></h3>
					<p id="<?php echo esc_attr( $start_help_id ); ?>"><?php esc_html_e( 'Choose where the trip starts.', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></p>
				</div>
			</div>

			<div class="dkc-plan__start-panel" id="<?php echo esc_attr( $start_panel_id ); ?>" data-plan-start-panel>
				<fieldset class="dkc-plan__fieldset" aria-describedby="<?php echo esc_attr( $start_help_id ); ?>">
					<legend class="screen-reader-text"><?php esc_html_e( 'Starting point mode', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></legend>
					<div class="dkc-plan__start-options">
						<?php foreach ( $start_points as $start_key => $start_point ) : ?>
							<label class="dkc-plan__start-option">
								<input
									type="radio"
									name="start_mode"
									value="<?php echo esc_attr( $start_key ); ?>"
									<?php checked( $planner_state['start_mode'], $start_key ); ?>>
								<span class="dkc-plan__start-option-body">
									<span class="dkc-plan__start-title"><?php echo esc_html( $start_point['label'] ); ?></span>
									<span class="dkc-plan__start-description"><?php echo esc_html( $start_point['description'] ); ?></span>
								</span>
							</label>
						<?php endforeach; ?>
					</div>

					<div
						class="dkc-plan__custom-start"
						data-plan-custom-start-wrap
						<?php echo Settings::START_MODE_CUSTOM === $planner_state['start_mode'] ? '' : 'hidden'; ?>>
						<label for="<?php echo esc_attr( $custom_start_id ); ?>"><?php esc_html_e( 'Custom starting point', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></label>
						<input
							id="<?php echo esc_attr( $custom_start_id ); ?>"
							type="text"
							name="custom_start"
							value="<?php echo esc_attr( $planner_state['custom_start'] ); ?>"
							placeholder="<?php esc_attr_e( 'Hotel name or street address', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?>"
							autocomplete="street-address"
							data-plan-custom-start>
					</div>
					<p class="dkc-plan__input-help" data-plan-start-note>
						<?php echo esc_html( $planner_state['start_note_text'] ); ?>
					</p>
				</fieldset>

				<input type="hidden" name="category" value="<?php echo esc_attr( $planner_state['category_key'] ); ?>" data-plan-category-input>
				<div data-plan-waypoint-inputs>
					<?php foreach ( $selected_waypoints as $waypoint_id ) : ?>
						<input type="hidden" name="waypoints[]" value="<?php echo esc_attr( (string) $waypoint_id ); ?>" data-plan-waypoint-input>
					<?php endforeach; ?>
				</div>

				<div class="dkc-plan__actions">
					<button class="dkc-plan__submit" type="submit"><?php esc_html_e( 'Update results', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></button>
				</div>
			</div>
		</section>
		<?php
	}

	private function render_category_card( string $instance_id, array $planner_state, array $category_catalog, array $results_empty_state ): void {
		$category_help_id = $instance_id . '-category-help';
		$heading_id       = $instance_id . '-categories-heading';
		?>
		<section class="dkc-plan__card" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
			<div class="dkc-plan__card-header">
				<div>
					<h3 id="<?php echo esc_attr( $heading_id ); ?>" data-plan-results-heading><?php esc_html_e( 'What are you looking for?', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></h3>
					<p id="<?php echo esc_attr( $category_help_id ); ?>">
						<?php esc_html_e( 'Open a category to search Google for real places.', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?>
					</p>
				</div>
				<span class="dkc-plan__count-pill" data-plan-results-count><?php echo esc_html( $planner_state['search_results_label'] ); ?></span>
			</div>

			<div class="dkc-plan__category-accordion" aria-describedby="<?php echo esc_attr( $category_help_id ); ?>">
				<?php foreach ( $category_catalog as $category_key => $category ) : ?>
					<?php
					$is_active          = $planner_state['category_key'] === $category_key;
					$trigger_id         = $instance_id . '-category-trigger-' . $category_key;
					$panel_id           = $instance_id . '-category-panel-' . $category_key;
					$search_result_list = $is_active ? (array) $planner_state['search_results'] : [];
					?>
					<div class="dkc-plan__category-accordion-item<?php echo $is_active ? ' is-expanded' : ''; ?>">
						<h4 class="dkc-plan__category-accordion-heading">
							<button
								class="dkc-plan__category-trigger"
								type="submit"
								name="category"
								value="<?php echo esc_attr( (string) $category_key ); ?>"
								id="<?php echo esc_attr( $trigger_id ); ?>"
								aria-expanded="<?php echo $is_active ? 'true' : 'false'; ?>"
								aria-controls="<?php echo esc_attr( $panel_id ); ?>"
								data-plan-category-button
								data-category-key="<?php echo esc_attr( (string) $category_key ); ?>">
								<span class="dkc-plan__category-trigger-copy">
									<span class="dkc-plan__category-title"><?php echo esc_html( (string) $category['label'] ); ?></span>
									<span class="dkc-plan__category-description"><?php echo esc_html( (string) $category['description'] ); ?></span>
								</span>
							</button>
						</h4>

						<div
							class="dkc-plan__category-panel"
							id="<?php echo esc_attr( $panel_id ); ?>"
							role="region"
							aria-labelledby="<?php echo esc_attr( $trigger_id ); ?>"
							<?php echo $is_active ? '' : 'hidden'; ?>>
							<div class="dkc-plan__category-results-scroll" data-plan-category-results-panel data-category-key="<?php echo esc_attr( (string) $category_key ); ?>">
								<?php if ( [] !== $search_result_list ) : ?>
									<ul class="dkc-plan__results-list">
										<?php foreach ( $search_result_list as $result ) : ?>
											<?php $this->render_search_result( $result, (array) $planner_state['selected_waypoint_ids'] ); ?>
										<?php endforeach; ?>
									</ul>
								<?php elseif ( $is_active ) : ?>
									<div class="dkc-plan__results-empty">
										<h4><?php echo esc_html( $results_empty_state['heading'] ); ?></h4>
										<p><?php echo esc_html( $results_empty_state['body'] ); ?></p>
									</div>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	private function render_search_result( array $result, array $selected_waypoint_ids ): void {
		$place_id   = (string) ( $result['id'] ?? '' );
		$label      = (string) ( $result['label'] ?? '' );
		$is_in_trip = in_array( $place_id, $selected_waypoint_ids, true );
		?>
		<li class="dkc-plan__result-item">
			<div class="dkc-plan__result-copy">
				<h4><?php echo esc_html( $label ); ?></h4>
				<?php if ( ! empty( $result['distance_label'] ) ) : ?>
					<p class="dkc-plan__result-distance"><?php echo esc_html( (string) $result['distance_label'] ); ?></p>
				<?php endif; ?>
				<p class="dkc-plan__result-meta"><?php echo esc_html( (string) ( $result['address'] ?? '' ) ); ?></p>
			</div>
			<div class="dkc-plan__result-tools">
				<?php if ( ! empty( $result['maps_uri'] ) ) : ?>
					<a class="dkc-plan__result-link" href="<?php echo esc_url( (string) $result['maps_uri'] ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'View in Google Maps', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?>
					</a>
				<?php endif; ?>

				<?php if ( $is_in_trip ) : ?>
					<span class="dkc-plan__result-added" aria-label="<?php echo esc_attr( sprintf( __( '%s is already in the trip', PLAN_YOUR_DAY_TEXT_DOMAIN ), $label ) ); ?>">
						<?php esc_html_e( 'In trip', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?>
					</span>
				<?php else : ?>
					<button
						class="dkc-plan__result-add"
						type="submit"
						name="waypoints[]"
						value="<?php echo esc_attr( $place_id ); ?>"
						data-plan-action="add-waypoint"
						data-place-id="<?php echo esc_attr( $place_id ); ?>">
						<?php esc_html_e( 'Add to trip', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?>
					</button>
				<?php endif; ?>
			</div>
		</li>
		<?php
	}

	private function render_trip_card( string $instance_id, array $planner_state ): void {
		$heading_id = $instance_id . '-trip-heading';
		$help_id    = $instance_id . '-trip-help';
		$waypoints  = (array) $planner_state['trip_waypoints'];
		?>
		<section class="dkc-plan__card" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
			<div class="dkc-plan__card-header">
				<div>
					<h3 id="<?php echo esc_attr( $heading_id ); ?>" tabindex="-1" data-plan-trip-heading><?php esc_html_e( 'Trip waypoints', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></h3>
					<p id="<?php echo esc_attr( $help_id ); ?>"><?php esc_html_e( 'Add exact places from Google, then use the move controls to set the walking trip order.', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></p>
				</div>
				<div class="dkc-plan__trip-header-actions" data-plan-trip-header-actions>
					<span class="dkc-plan__count-pill" data-plan-trip-count><?php echo esc_html( $planner_state['trip_count_label'] ); ?></span>
					<?php if ( [] !== (array) $planner_state['selected_waypoint_ids'] ) : ?>
						<button class="dkc-plan__clear-link" type="submit" name="clear_trip" value="1" data-plan-clear-trip data-plan-action="clear-trip">
							<?php esc_html_e( 'Clear trip', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?>
						</button>
					<?php endif; ?>
				</div>
			</div>

			<div data-plan-trip-region>
				<?php if ( [] !== $waypoints ) : ?>
					<ol class="dkc-plan__trip-list" aria-describedby="<?php echo esc_attr( $help_id ); ?>" data-plan-trip-list>
						<?php foreach ( $waypoints as $index => $waypoint ) : ?>
							<?php $this->render_trip_waypoint( $waypoint, $index, count( $waypoints ) ); ?>
						<?php endforeach; ?>
					</ol>
				<?php else : ?>
					<div class="dkc-plan__trip-empty" data-plan-trip-empty>
						<h4><?php esc_html_e( 'Start building the trip', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></h4>
						<p><?php esc_html_e( 'Search Google by category, then add the exact places you want as walking-trip waypoints.', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}

	private function render_trip_waypoint( array $waypoint, int $index, int $waypoint_count ): void {
		$place_id = (string) ( $waypoint['id'] ?? '' );
		$label    = (string) ( $waypoint['label'] ?? '' );
		?>
		<li class="dkc-plan__trip-item" data-waypoint-id="<?php echo esc_attr( $place_id ); ?>">
			<div class="dkc-plan__trip-main">
				<span class="dkc-plan__trip-number" aria-hidden="true"><?php echo esc_html( (string) ( $index + 1 ) ); ?></span>
				<div class="dkc-plan__trip-copy">
					<h4><?php echo esc_html( $label ); ?></h4>
					<p class="dkc-plan__trip-meta"><?php echo esc_html( (string) ( $waypoint['address'] ?? '' ) ); ?></p>
				</div>
			</div>
			<div class="dkc-plan__trip-tools">
				<button
					class="dkc-plan__reorder-button dkc-plan__reorder-button--up"
					type="<?php echo $index > 0 ? 'submit' : 'button'; ?>"
					name="move_waypoint"
					value="<?php echo esc_attr( $place_id . ':up' ); ?>"
					<?php disabled( 0 === $index ); ?>>
					<?php esc_html_e( 'Move up', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?>
				</button>
				<button
					class="dkc-plan__reorder-button dkc-plan__reorder-button--down"
					type="<?php echo $index < $waypoint_count - 1 ? 'submit' : 'button'; ?>"
					name="move_waypoint"
					value="<?php echo esc_attr( $place_id . ':down' ); ?>"
					<?php disabled( $index >= $waypoint_count - 1 ); ?>>
					<?php esc_html_e( 'Move down', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?>
				</button>
				<button type="submit" name="remove_waypoint" value="<?php echo esc_attr( $place_id ); ?>" data-plan-action="remove-waypoint" data-place-id="<?php echo esc_attr( $place_id ); ?>">
					<?php echo esc_html( sprintf( __( 'Remove %s', PLAN_YOUR_DAY_TEXT_DOMAIN ), $label ) ); ?>
				</button>
			</div>
		</li>
		<?php
	}

	private function render_preview_card( string $instance_id, array $planner_state, array $preview_empty_state, bool $maps_link_enabled ): void {
		$heading_id     = $instance_id . '-preview-heading';
		$maps_label_id  = $instance_id . '-maps-label';
		$category_label = $planner_state['has_category'] ? (string) $planner_state['category']['label'] : __( 'Not selected', PLAN_YOUR_DAY_TEXT_DOMAIN );
		?>
		<section class="dkc-plan__card dkc-plan__preview-card" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>" data-plan-preview-card>
			<div class="dkc-plan__card-header">
				<div>
					<h3 id="<?php echo esc_attr( $heading_id ); ?>" tabindex="-1" data-plan-preview-heading><?php esc_html_e( 'Trip preview', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></h3>
					<p><?php esc_html_e( 'The map starts as a Google place search and switches to walking directions once you add exact waypoints.', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></p>
				</div>
			</div>

			<div class="screen-reader-text" aria-live="polite" data-plan-live-region></div>
			<?php $this->render_messages( (array) $planner_state['messages'] ); ?>

			<div class="dkc-plan__map-wrap" data-plan-map-wrap <?php echo '' !== $planner_state['iframe_src'] ? '' : 'hidden'; ?>>
				<iframe
					class="dkc-plan__map-frame"
					title="<?php esc_attr_e( 'Google Maps trip preview', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?>"
					src="<?php echo '' !== $planner_state['iframe_src'] ? esc_url( $planner_state['iframe_src'] ) : ''; ?>"
					loading="lazy"
					referrerpolicy="no-referrer-when-downgrade"
					allowfullscreen
					data-plan-iframe></iframe>
			</div>

			<div class="dkc-plan__preview-empty" data-plan-preview-empty <?php echo '' !== $planner_state['iframe_src'] ? 'hidden' : ''; ?>>
				<h4 data-plan-preview-empty-heading><?php echo esc_html( $preview_empty_state['heading'] ); ?></h4>
				<p data-plan-preview-empty-body><?php echo esc_html( $preview_empty_state['body'] ); ?></p>
			</div>

			<div class="dkc-plan__summary" data-plan-summary>
				<div class="dkc-plan__summary-top">
					<p class="dkc-plan__summary-eyebrow"><?php esc_html_e( 'Trip summary', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></p>
					<p class="dkc-plan__summary-count" data-plan-summary-count><?php echo esc_html( $planner_state['trip_count_label'] ); ?></p>
				</div>
				<p class="dkc-plan__summary-overview" data-plan-summary-overview><?php echo esc_html( $planner_state['overview'] ); ?></p>
				<dl class="dkc-plan__summary-grid">
					<div>
						<dt><?php esc_html_e( 'Active category', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></dt>
						<dd data-plan-summary-category><?php echo esc_html( $category_label ); ?></dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'Google results', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></dt>
						<dd data-plan-summary-results><?php echo esc_html( $planner_state['search_results_label'] ); ?></dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'Google Maps start', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></dt>
						<dd data-plan-summary-handoff-start><?php echo esc_html( $planner_state['handoff_start_label'] ); ?></dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'Map mode', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></dt>
						<dd data-plan-summary-mode><?php echo esc_html( $planner_state['preview_mode_label'] ); ?></dd>
					</div>
				</dl>

				<div class="dkc-plan__summary-handoff">
					<p class="dkc-plan__summary-handoff-label" id="<?php echo esc_attr( $maps_label_id ); ?>"><?php esc_html_e( 'Open in Google Maps', PLAN_YOUR_DAY_TEXT_DOMAIN ); ?></p>
					<a
						class="dkc-plan__maps-link dkc-plan__maps-link--summary<?php echo $maps_link_enabled ? '' : ' is-disabled'; ?>"
						<?php echo $maps_link_enabled ? 'href="' . esc_url( $planner_state['maps_url'] ) . '"' : ''; ?>
						target="_blank"
						rel="noopener noreferrer"
						data-plan-open-link
						<?php echo $maps_link_enabled ? '' : 'aria-disabled="true"'; ?>>
						<span data-plan-open-link-label><?php echo esc_html( $planner_state['maps_link_label'] ); ?></span>
					</a>
				</div>
			</div>
		</section>
		<?php
	}

	private function render_messages( array $messages ): void {
		?>
		<ul class="dkc-plan__messages" data-plan-messages <?php echo [] === $messages ? 'hidden' : ''; ?>>
			<?php foreach ( $messages as $message ) : ?>
				<li class="dkc-plan__message dkc-plan__message--<?php echo esc_attr( (string) ( $message['type'] ?? 'note' ) ); ?>">
					<?php echo esc_html( (string) ( $message['text'] ?? '' ) ); ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	private function get_start_points(): array {
		$allowed_modes = $this->settings->get_allowed_start_modes();
		$choices       = Settings::start_mode_choices();
		$descriptions  = [
			Settings::START_MODE_CURRENT => __( 'Use the configured default location for on-page previews. Google Maps can start from the visitor\'s current location during handoff.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			Settings::START_MODE_DEFAULT => sprintf(
				/* translators: %s is the configured default location label. */
				__( 'Start from %s.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				$this->settings->get_default_location_label()
			),
			Settings::START_MODE_CUSTOM  => __( 'Enter a hotel name, landmark, or street address.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
		];
		$start_points  = [];

		foreach ( $allowed_modes as $mode ) {
			if ( ! isset( $choices[ $mode ] ) ) {
				continue;
			}

			$start_points[ $mode ] = [
				'label'       => $choices[ $mode ],
				'description' => $descriptions[ $mode ] ?? '',
			];
		}

		return $start_points;
	}

	private function build_config( string $instance_id, string $action_url, array $planner_state, array $start_points, array $category_catalog, string $endpoint_token ): array {
		return [
			'actionUrl'       => $action_url,
			'sectionId'       => $instance_id,
			'startPoints'     => $start_points,
			'categoryCatalog' => $category_catalog,
			'rest'            => [
				'browseUrl'      => rest_url( PlannerRoutes::REST_NAMESPACE . '/browse' ),
				'routeUrl'       => rest_url( PlannerRoutes::REST_NAMESPACE . '/route' ),
				'endpointToken'  => $endpoint_token,
			],
			'strings'         => [
				'requestFailed'       => __( 'The planner request could not be completed. Refresh the page and try again.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'resultsUpdated'      => __( 'Results updated.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'tripUpdated'         => __( 'Trip updated.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'startingPointUpdated' => __( 'Starting point updated.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'startOptionsExpanded' => __( 'Starting point options expanded.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'startOptionsCollapsed' => __( 'Starting point options collapsed.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'openMapsDisabled'   => __( 'Add at least one waypoint before opening the trip in Google Maps.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'viewInGoogleMaps'   => __( 'View in Google Maps', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'addToTrip'          => __( 'Add to trip', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'inTrip'             => __( 'In trip', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'alreadyInTripAria'  => __( '%s is already in the trip', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'tripEmptyHeading'   => __( 'Start building the trip', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'tripEmptyBody'      => __( 'Search Google by category, then add the exact places you want as walking-trip waypoints.', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'moveUp'             => __( 'Move up', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'moveDown'           => __( 'Move down', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'removeWaypointLabel' => __( 'Remove %s', PLAN_YOUR_DAY_TEXT_DOMAIN ),
				'clearTrip'          => __( 'Clear trip', PLAN_YOUR_DAY_TEXT_DOMAIN ),
			],
			'initialState'    => [
				'category'            => $planner_state['category_key'],
				'selectedWaypointIds' => array_values( $planner_state['selected_waypoint_ids'] ),
				'startMode'           => $planner_state['start_mode'],
				'customStart'         => $planner_state['custom_start'],
			],
			'initialData'     => [
				'browse' => $this->planner_payload_builder->build_browse_payload( $planner_state ),
				'route'  => $this->planner_payload_builder->build_route_payload( $planner_state ),
			],
		];
	}

	private function get_current_url(): string {
		$queried_object_id = get_queried_object_id();
		$permalink         = $queried_object_id > 0 ? get_permalink( $queried_object_id ) : false;

		return is_string( $permalink ) && '' !== $permalink ? $permalink : home_url( '/' );
	}
}
