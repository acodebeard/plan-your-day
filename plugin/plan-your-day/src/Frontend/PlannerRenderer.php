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

		$request_state           = $this->request_state_parser->parse( $request );
		$should_hydrate_on_load = InitialPlannerHydration::should_hydrate_on_load( $request_state );
		$planner_state           = $this->planner_state_builder->build(
			$request_state,
			InitialPlannerHydration::build_render_state_options()
		);

		if ( $should_hydrate_on_load ) {
			$planner_state = InitialPlannerHydration::apply_loading_placeholders( $planner_state );
		}

		$category_catalog    = $this->category_catalog->get_all();
		$start_points        = $this->get_start_points();
		$results_empty_state = isset( $planner_state['results_empty_state'] )
			? (array) $planner_state['results_empty_state']
			: $this->planner_payload_builder->get_empty_results_state( $planner_state );
		$preview_empty_state = isset( $planner_state['preview_empty_state'] )
			? (array) $planner_state['preview_empty_state']
			: $this->planner_payload_builder->get_empty_preview_state( $planner_state );
		$action_url          = '' !== $action_url ? $action_url : $this->get_current_url();
		$form_action         = $action_url . '#' . $instance_id;
		$maps_link_enabled   = '' !== $planner_state['maps_url'];
		$endpoint_token      = $this->visitor_token_manager->get_endpoint_token();

		ob_start();
		?>
		<section
			class="plan-your-day"
			id="<?php echo esc_attr( $instance_id ); ?>"
			aria-labelledby="<?php echo esc_attr( $title_id ); ?>"
			data-plan-root>
			<div class="plan-your-day__surface">
				<header class="plan-your-day__hero">
					<div class="plan-your-day__hero-copy">
						<p class="plan-your-day__eyebrow"><?php esc_html_e( 'Trip builder', 'plan-your-day' ); ?></p>
						<h2 id="<?php echo esc_attr( $title_id ); ?>"><?php esc_html_e( 'Plan Your Day', 'plan-your-day' ); ?></h2>
						<p class="plan-your-day__intro">
							<?php esc_html_e( 'Search by category, choose real places from Google, then turn your picks into a walking trip.', 'plan-your-day' ); ?>
						</p>
					</div>
				</header>

				<form
					class="plan-your-day__layout"
					method="get"
					action="<?php echo esc_url( $form_action ); ?>"
					data-plan-form>
					<div class="plan-your-day__controls">
						<?php
						$this->render_start_card( $instance_id, $planner_state, $start_points );
						$this->render_category_card( $instance_id, $planner_state, $category_catalog, $results_empty_state );
						?>
					</div>

					<div class="plan-your-day__preview-panel">
						<?php
						$this->render_trip_card( $instance_id, $planner_state );
						$this->render_preview_card( $instance_id, $planner_state, $preview_empty_state, $maps_link_enabled );
						?>
					</div>
				</form>
			</div>

			<script type="application/json" data-plan-config><?php echo wp_json_encode( $this->build_config( $instance_id, $action_url, $planner_state, $start_points, $category_catalog, $endpoint_token, $should_hydrate_on_load ) ); ?></script>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	private function render_setup_notice( string $instance_id, string $title_id ): string {
		$missing = implode( ', ', $this->settings->get_missing_required_settings() );

		ob_start();
		?>
		<section
			class="plan-your-day plan-your-day--setup-required"
			id="<?php echo esc_attr( $instance_id ); ?>"
			aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
			<div class="plan-your-day__surface">
				<h2 id="<?php echo esc_attr( $title_id ); ?>"><?php esc_html_e( 'Plan Your Day setup needed', 'plan-your-day' ); ?></h2>
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s is a comma-separated list of missing setting labels. */
							__( 'The planner needs required settings before it can render: %s.', 'plan-your-day' ),
							$missing
						)
					);
					?>
				</p>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<p>
						<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . Settings::PAGE_SLUG ) ); ?>">
							<?php esc_html_e( 'Open Plan Your Day settings', 'plan-your-day' ); ?>
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
		<section class="plan-your-day__card" aria-labelledby="<?php echo esc_attr( $start_heading_id ); ?>">
			<div class="plan-your-day__card-header">
				<div>
					<h3 id="<?php echo esc_attr( $start_heading_id ); ?>"><?php esc_html_e( 'Starting point', 'plan-your-day' ); ?></h3>
					<p id="<?php echo esc_attr( $start_help_id ); ?>"><?php esc_html_e( 'Choose where the trip starts.', 'plan-your-day' ); ?></p>
				</div>
			</div>

			<div class="plan-your-day__start-panel" id="<?php echo esc_attr( $start_panel_id ); ?>" data-plan-start-panel>
				<fieldset class="plan-your-day__fieldset" aria-describedby="<?php echo esc_attr( $start_help_id ); ?>">
					<legend class="screen-reader-text"><?php esc_html_e( 'Starting point mode', 'plan-your-day' ); ?></legend>
					<div class="plan-your-day__start-options">
						<?php foreach ( $start_points as $start_key => $start_point ) : ?>
							<label class="plan-your-day__start-option">
								<input
									type="radio"
									name="start_mode"
									value="<?php echo esc_attr( $start_key ); ?>"
									<?php checked( $planner_state['start_mode'], $start_key ); ?>>
								<span class="plan-your-day__start-option-body">
									<span class="plan-your-day__start-title"><?php echo esc_html( $start_point['label'] ); ?></span>
									<span class="plan-your-day__start-description"><?php echo esc_html( $start_point['description'] ); ?></span>
								</span>
							</label>
						<?php endforeach; ?>
					</div>

					<div
						class="plan-your-day__custom-start"
						data-plan-custom-start-wrap
						<?php echo Settings::START_MODE_CUSTOM === $planner_state['start_mode'] ? '' : 'hidden'; ?>>
						<label for="<?php echo esc_attr( $custom_start_id ); ?>"><?php esc_html_e( 'Custom starting point', 'plan-your-day' ); ?></label>
						<input
							id="<?php echo esc_attr( $custom_start_id ); ?>"
							type="text"
							name="custom_start"
							value="<?php echo esc_attr( $planner_state['custom_start'] ); ?>"
							placeholder="<?php esc_attr_e( 'Hotel name or street address', 'plan-your-day' ); ?>"
							autocomplete="street-address"
							data-plan-custom-start>
					</div>
					<p class="plan-your-day__input-help" data-plan-start-note>
						<?php echo esc_html( $planner_state['start_note_text'] ); ?>
					</p>
				</fieldset>

				<input type="hidden" name="category" value="<?php echo esc_attr( $planner_state['category_key'] ); ?>" data-plan-category-input>
				<div data-plan-waypoint-inputs>
					<?php foreach ( $selected_waypoints as $waypoint_id ) : ?>
						<input type="hidden" name="waypoints[]" value="<?php echo esc_attr( (string) $waypoint_id ); ?>" data-plan-waypoint-input>
					<?php endforeach; ?>
				</div>

				<div class="plan-your-day__actions">
					<button class="plan-your-day__submit" type="submit"><?php esc_html_e( 'Update results', 'plan-your-day' ); ?></button>
				</div>
			</div>
		</section>
		<?php
	}

	private function render_category_card( string $instance_id, array $planner_state, array $category_catalog, array $results_empty_state ): void {
		$category_help_id          = $instance_id . '-category-help';
		$heading_id                = $instance_id . '-categories-heading';
		$category_search_id        = $instance_id . '-category-search';
		$custom_results_heading_id = $instance_id . '-custom-results-heading';
		$custom_results_trigger_id = $instance_id . '-custom-results-trigger';
		$custom_results_panel_id   = $instance_id . '-custom-results-panel';
		$has_custom_search         = (bool) $planner_state['is_custom_search'];
		?>
		<section class="plan-your-day__card" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
			<div class="plan-your-day__card-header">
				<div>
					<h3 id="<?php echo esc_attr( $heading_id ); ?>" data-plan-results-heading><?php esc_html_e( 'What are you looking for?', 'plan-your-day' ); ?></h3>
					<p id="<?php echo esc_attr( $category_help_id ); ?>">
						<?php esc_html_e( 'Search for any category or use a preset shortcut to load Google results.', 'plan-your-day' ); ?>
					</p>
				</div>
				<span class="plan-your-day__count-pill" data-plan-results-count><?php echo esc_html( $planner_state['search_results_label'] ); ?></span>
			</div>

			<div class="plan-your-day__category-search">
				<label for="<?php echo esc_attr( $category_search_id ); ?>" class="screen-reader-text"><?php esc_html_e( 'Search categories', 'plan-your-day' ); ?></label>
				<div class="plan-your-day__category-search-controls">
					<input
						id="<?php echo esc_attr( $category_search_id ); ?>"
						type="search"
						name="category_search"
						value="<?php echo esc_attr( (string) $planner_state['category_search'] ); ?>"
						placeholder="<?php esc_attr_e( 'Search categories', 'plan-your-day' ); ?>"
						autocomplete="off"
						spellcheck="false"
						data-plan-category-search>
					<button class="plan-your-day__category-search-button" type="submit" data-plan-action="search-category-query">
						<?php esc_html_e( 'Search', 'plan-your-day' ); ?>
					</button>
				</div>
			</div>

			<div
				class="plan-your-day__custom-search-results plan-your-day__category-accordion-item<?php echo $has_custom_search ? ' is-expanded' : ''; ?>"
				data-plan-custom-results
				<?php echo ( $has_custom_search || [] === $category_catalog ) ? '' : 'hidden'; ?>>
				<div class="plan-your-day__custom-search-header">
					<h4 class="plan-your-day__category-accordion-heading">
						<button
							class="plan-your-day__category-trigger"
							type="button"
							id="<?php echo esc_attr( $custom_results_trigger_id ); ?>"
							aria-expanded="<?php echo $has_custom_search ? 'true' : 'false'; ?>"
							aria-controls="<?php echo esc_attr( $custom_results_panel_id ); ?>"
							data-plan-custom-results-button>
							<span class="plan-your-day__category-trigger-copy">
								<span class="plan-your-day__category-title" id="<?php echo esc_attr( $custom_results_heading_id ); ?>" data-plan-custom-results-heading>
									<?php
									echo esc_html(
										$has_custom_search
											? sprintf(
												/* translators: %s is the active search label. */
												__( 'Results for %s', 'plan-your-day' ),
												$planner_state['active_search_label']
											)
											: $results_empty_state['heading']
									);
									?>
								</span>
								<span class="plan-your-day__category-description" data-plan-custom-results-description>
									<?php esc_html_e( 'Custom category search results.', 'plan-your-day' ); ?>
								</span>
							</span>
							<span class="plan-your-day__category-trigger-side" aria-hidden="true">
								<span class="plan-your-day__category-trigger-icon"></span>
							</span>
						</button>
					</h4>
				</div>
				<div
					class="plan-your-day__category-panel"
					id="<?php echo esc_attr( $custom_results_panel_id ); ?>"
					role="region"
					aria-labelledby="<?php echo esc_attr( $custom_results_trigger_id ); ?>"
					data-plan-custom-results-region
					<?php echo $has_custom_search || [] === $category_catalog ? '' : 'hidden'; ?>>
					<div class="plan-your-day__category-results-scroll" data-plan-custom-results-panel>
					<?php if ( $planner_state['is_custom_search'] && [] !== (array) $planner_state['search_results'] ) : ?>
						<ul class="plan-your-day__results-list">
							<?php foreach ( (array) $planner_state['search_results'] as $result ) : ?>
								<?php $this->render_search_result( $result, (array) $planner_state['selected_waypoint_ids'] ); ?>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<div class="plan-your-day__results-empty">
							<h4><?php echo esc_html( $results_empty_state['heading'] ); ?></h4>
							<p><?php echo esc_html( $results_empty_state['body'] ); ?></p>
						</div>
					<?php endif; ?>
					</div>
				</div>
			</div>

			<?php if ( [] !== $category_catalog ) : ?>
				<div class="plan-your-day__category-accordion" aria-describedby="<?php echo esc_attr( $category_help_id ); ?>">
					<?php foreach ( $category_catalog as $category_key => $category ) : ?>
						<?php
						$is_active          = $planner_state['category_key'] === $category_key;
						$trigger_id         = $instance_id . '-category-trigger-' . $category_key;
						$panel_id           = $instance_id . '-category-panel-' . $category_key;
						$search_result_list = $is_active ? (array) $planner_state['search_results'] : [];
						?>
						<div class="plan-your-day__category-accordion-item<?php echo $is_active ? ' is-expanded' : ''; ?>" data-plan-category-item>
							<h4 class="plan-your-day__category-accordion-heading">
								<button
									class="plan-your-day__category-trigger"
									type="submit"
									name="category"
									value="<?php echo esc_attr( (string) $category_key ); ?>"
									id="<?php echo esc_attr( $trigger_id ); ?>"
									aria-expanded="<?php echo $is_active ? 'true' : 'false'; ?>"
									aria-controls="<?php echo esc_attr( $panel_id ); ?>"
									data-plan-category-button
									data-category-key="<?php echo esc_attr( (string) $category_key ); ?>">
									<span class="plan-your-day__category-trigger-copy">
										<span class="plan-your-day__category-title"><?php echo esc_html( (string) $category['label'] ); ?></span>
										<span class="plan-your-day__category-description"><?php echo esc_html( (string) $category['description'] ); ?></span>
									</span>
									<span class="plan-your-day__category-trigger-side" aria-hidden="true">
										<span class="plan-your-day__category-trigger-icon"></span>
									</span>
								</button>
							</h4>

							<div
								class="plan-your-day__category-panel"
								id="<?php echo esc_attr( $panel_id ); ?>"
								role="region"
								aria-labelledby="<?php echo esc_attr( $trigger_id ); ?>"
								data-plan-category-region
								data-category-key="<?php echo esc_attr( (string) $category_key ); ?>"
								<?php echo $is_active ? '' : 'hidden'; ?>>
								<div class="plan-your-day__category-results-scroll" data-plan-category-results-panel data-category-key="<?php echo esc_attr( (string) $category_key ); ?>">
									<?php if ( [] !== $search_result_list ) : ?>
										<ul class="plan-your-day__results-list">
											<?php foreach ( $search_result_list as $result ) : ?>
												<?php $this->render_search_result( $result, (array) $planner_state['selected_waypoint_ids'] ); ?>
											<?php endforeach; ?>
										</ul>
									<?php elseif ( $is_active ) : ?>
										<div class="plan-your-day__results-empty">
											<h4><?php echo esc_html( $results_empty_state['heading'] ); ?></h4>
											<p><?php echo esc_html( $results_empty_state['body'] ); ?></p>
										</div>
									<?php endif; ?>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}

	private function render_search_result( array $result, array $selected_waypoint_ids ): void {
		$place_id   = (string) ( $result['id'] ?? '' );
		$label      = (string) ( $result['label'] ?? '' );
		$is_in_trip = in_array( $place_id, $selected_waypoint_ids, true );
		?>
		<li class="plan-your-day__result-item">
			<div class="plan-your-day__result-copy">
				<h4><?php echo esc_html( $label ); ?></h4>
				<?php if ( ! empty( $result['distance_label'] ) ) : ?>
					<p class="plan-your-day__result-distance"><?php echo esc_html( (string) $result['distance_label'] ); ?></p>
				<?php endif; ?>
				<p class="plan-your-day__result-meta"><?php echo esc_html( (string) ( $result['address'] ?? '' ) ); ?></p>
			</div>
			<div class="plan-your-day__result-tools">
				<?php if ( ! empty( $result['maps_uri'] ) ) : ?>
					<a
							class="plan-your-day__result-link"
							href="<?php echo esc_url( (string) $result['maps_uri'] ); ?>"
							target="_blank"
							rel="noopener noreferrer"
							aria-label="
							<?php
							/* translators: %s is a place label. */
							echo esc_attr( sprintf( __( 'View %s in Google Maps', 'plan-your-day' ), $label ) );
							?>
							">
						<?php esc_html_e( 'View in Google Maps', 'plan-your-day' ); ?>
					</a>
				<?php endif; ?>

				<?php if ( $is_in_trip ) : ?>
						<span class="plan-your-day__result-added" aria-label="
						<?php
						/* translators: %s is a place label. */
						echo esc_attr( sprintf( __( '%s is already in the trip', 'plan-your-day' ), $label ) );
						?>
						">
						<?php esc_html_e( 'In trip', 'plan-your-day' ); ?>
					</span>
				<?php else : ?>
					<button
						class="plan-your-day__result-add"
						type="submit"
						name="waypoints[]"
							value="<?php echo esc_attr( $place_id ); ?>"
							data-plan-action="add-waypoint"
							data-plan-route-mutation
							data-place-id="<?php echo esc_attr( $place_id ); ?>"
							aria-label="
							<?php
							/* translators: %s is a place label. */
							echo esc_attr( sprintf( __( 'Add %s to trip', 'plan-your-day' ), $label ) );
							?>
							">
						<?php esc_html_e( 'Add to trip', 'plan-your-day' ); ?>
					</button>
				<?php endif; ?>
			</div>
		</li>
		<?php
	}

	private function render_trip_card( string $instance_id, array $planner_state ): void {
		$heading_id       = $instance_id . '-trip-heading';
		$help_id          = $instance_id . '-trip-help';
		$waypoints        = (array) $planner_state['trip_waypoints'];
		$trip_empty_state = isset( $planner_state['trip_empty_state'] )
			? (array) $planner_state['trip_empty_state']
			: [
				'heading' => __( 'Start building the trip', 'plan-your-day' ),
				'body'    => __( 'Search Google by category, then add the exact places you want as walking-trip waypoints.', 'plan-your-day' ),
			];
		?>
		<section class="plan-your-day__card" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
			<div class="plan-your-day__card-header">
				<div>
					<h3 id="<?php echo esc_attr( $heading_id ); ?>" tabindex="-1" data-plan-trip-heading><?php esc_html_e( 'Trip waypoints', 'plan-your-day' ); ?></h3>
					<p id="<?php echo esc_attr( $help_id ); ?>"><?php esc_html_e( 'Add exact places from Google, then use the move controls to set the walking trip order.', 'plan-your-day' ); ?></p>
				</div>
				<div class="plan-your-day__trip-header-actions" data-plan-trip-header-actions>
					<span class="plan-your-day__count-pill" data-plan-trip-count><?php echo esc_html( $planner_state['trip_count_label'] ); ?></span>
					<?php if ( [] !== (array) $planner_state['selected_waypoint_ids'] ) : ?>
						<button class="plan-your-day__clear-link" type="submit" name="clear_trip" value="1" data-plan-clear-trip data-plan-action="clear-trip" data-plan-route-mutation>
							<?php esc_html_e( 'Clear trip', 'plan-your-day' ); ?>
						</button>
					<?php endif; ?>
				</div>
			</div>

			<div data-plan-trip-region data-plan-trip-help-id="<?php echo esc_attr( $help_id ); ?>">
				<?php if ( [] !== $waypoints ) : ?>
					<ol class="plan-your-day__trip-list" aria-describedby="<?php echo esc_attr( $help_id ); ?>" data-plan-trip-list>
						<?php foreach ( $waypoints as $index => $waypoint ) : ?>
							<?php $this->render_trip_waypoint( $waypoint, $index, count( $waypoints ) ); ?>
						<?php endforeach; ?>
					</ol>
				<?php else : ?>
					<div class="plan-your-day__trip-empty" data-plan-trip-empty>
						<h4><?php echo esc_html( $trip_empty_state['heading'] ); ?></h4>
						<p><?php echo esc_html( $trip_empty_state['body'] ); ?></p>
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
		<li class="plan-your-day__trip-item" data-waypoint-id="<?php echo esc_attr( $place_id ); ?>">
			<div class="plan-your-day__trip-main">
				<span class="plan-your-day__trip-number" aria-hidden="true"><?php echo esc_html( (string) ( $index + 1 ) ); ?></span>
				<div class="plan-your-day__trip-copy">
					<h4><?php echo esc_html( $label ); ?></h4>
					<p class="plan-your-day__trip-meta"><?php echo esc_html( (string) ( $waypoint['address'] ?? '' ) ); ?></p>
				</div>
			</div>
			<div class="plan-your-day__trip-tools">
				<button
					class="plan-your-day__reorder-button plan-your-day__reorder-button--up"
						type="<?php echo $index > 0 ? 'submit' : 'button'; ?>"
						name="move_waypoint"
						value="<?php echo esc_attr( $place_id . ':up' ); ?>"
						data-plan-route-mutation
						aria-label="
						<?php
						/* translators: %s is a place label. */
						echo esc_attr( sprintf( __( 'Move %s up in the trip', 'plan-your-day' ), $label ) );
						?>
						"
					<?php disabled( 0 === $index ); ?>>
					<?php esc_html_e( 'Move up', 'plan-your-day' ); ?>
				</button>
				<button
					class="plan-your-day__reorder-button plan-your-day__reorder-button--down"
						type="<?php echo $index < $waypoint_count - 1 ? 'submit' : 'button'; ?>"
						name="move_waypoint"
						value="<?php echo esc_attr( $place_id . ':down' ); ?>"
						data-plan-route-mutation
						aria-label="
						<?php
						/* translators: %s is a place label. */
						echo esc_attr( sprintf( __( 'Move %s down in the trip', 'plan-your-day' ), $label ) );
						?>
						"
					<?php disabled( $index >= $waypoint_count - 1 ); ?>>
					<?php esc_html_e( 'Move down', 'plan-your-day' ); ?>
					</button>
					<button type="submit" name="remove_waypoint" value="<?php echo esc_attr( $place_id ); ?>" data-plan-action="remove-waypoint" data-plan-route-mutation data-place-id="<?php echo esc_attr( $place_id ); ?>">
						<?php
						/* translators: %s is a place label. */
						echo esc_html( sprintf( __( 'Remove %s', 'plan-your-day' ), $label ) );
						?>
					</button>
			</div>
		</li>
		<?php
	}

	private function render_preview_card( string $instance_id, array $planner_state, array $preview_empty_state, bool $maps_link_enabled ): void {
		$heading_id     = $instance_id . '-preview-heading';
		$maps_label_id  = $instance_id . '-maps-label';
		$category_label = $planner_state['has_search'] ? (string) $planner_state['active_search_label'] : __( 'Not selected', 'plan-your-day' );
		?>
		<section class="plan-your-day__card plan-your-day__preview-card" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>" data-plan-preview-card>
			<div class="plan-your-day__card-header">
				<div>
					<h3 id="<?php echo esc_attr( $heading_id ); ?>" tabindex="-1" data-plan-preview-heading><?php esc_html_e( 'Trip preview', 'plan-your-day' ); ?></h3>
					<p><?php esc_html_e( 'The map starts as a Google place search and switches to walking directions once you add exact waypoints.', 'plan-your-day' ); ?></p>
				</div>
			</div>

			<div class="screen-reader-text" role="status" aria-live="polite" aria-atomic="true" data-plan-live-region></div>
			<?php $this->render_messages( (array) $planner_state['messages'] ); ?>

			<div class="plan-your-day__map-wrap" data-plan-map-wrap <?php echo '' !== $planner_state['iframe_src'] ? '' : 'hidden'; ?>>
				<iframe
					class="plan-your-day__map-frame"
					title="<?php esc_attr_e( 'Google Maps trip preview', 'plan-your-day' ); ?>"
					src="<?php echo '' !== $planner_state['iframe_src'] ? esc_url( $planner_state['iframe_src'] ) : ''; ?>"
					loading="lazy"
					referrerpolicy="no-referrer-when-downgrade"
					allowfullscreen
					data-plan-iframe></iframe>
			</div>

			<div class="plan-your-day__preview-empty" data-plan-preview-empty <?php echo '' !== $planner_state['iframe_src'] ? 'hidden' : ''; ?>>
				<h4 data-plan-preview-empty-heading><?php echo esc_html( $preview_empty_state['heading'] ); ?></h4>
				<p data-plan-preview-empty-body><?php echo esc_html( $preview_empty_state['body'] ); ?></p>
			</div>

			<div class="plan-your-day__summary" data-plan-summary>
				<div class="plan-your-day__summary-top">
					<p class="plan-your-day__summary-eyebrow"><?php esc_html_e( 'Trip summary', 'plan-your-day' ); ?></p>
					<p class="plan-your-day__summary-count" data-plan-summary-count><?php echo esc_html( $planner_state['trip_count_label'] ); ?></p>
				</div>
				<p class="plan-your-day__summary-overview" data-plan-summary-overview><?php echo esc_html( $planner_state['overview'] ); ?></p>
				<dl class="plan-your-day__summary-grid">
					<div>
						<dt><?php esc_html_e( 'Active search', 'plan-your-day' ); ?></dt>
						<dd data-plan-summary-category><?php echo esc_html( $category_label ); ?></dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'Google results', 'plan-your-day' ); ?></dt>
						<dd data-plan-summary-results><?php echo esc_html( $planner_state['search_results_label'] ); ?></dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'Google Maps start', 'plan-your-day' ); ?></dt>
						<dd data-plan-summary-handoff-start><?php echo esc_html( $planner_state['handoff_start_label'] ); ?></dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'Map mode', 'plan-your-day' ); ?></dt>
						<dd data-plan-summary-mode><?php echo esc_html( $planner_state['preview_mode_label'] ); ?></dd>
					</div>
				</dl>

				<div class="plan-your-day__summary-handoff">
					<p class="plan-your-day__summary-handoff-label" id="<?php echo esc_attr( $maps_label_id ); ?>"><?php esc_html_e( 'Open in Google Maps', 'plan-your-day' ); ?></p>
					<a
						class="plan-your-day__maps-link plan-your-day__maps-link--summary<?php echo $maps_link_enabled ? '' : ' is-disabled'; ?>"
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
		<ul class="plan-your-day__messages" data-plan-messages <?php echo [] === $messages ? 'hidden' : ''; ?>>
			<?php foreach ( $messages as $message ) : ?>
				<li
					class="plan-your-day__message plan-your-day__message--<?php echo esc_attr( (string) ( $message['type'] ?? 'note' ) ); ?>"
					<?php echo 'warning' === ( $message['type'] ?? 'note' ) ? 'role="alert"' : ''; ?>>
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
			Settings::START_MODE_CURRENT => __( 'Use the configured default location for on-page previews. Google Maps can start from the visitor\'s current location during handoff.', 'plan-your-day' ),
			Settings::START_MODE_DEFAULT => sprintf(
				/* translators: %s is the configured default location label. */
				__( 'Start from %s.', 'plan-your-day' ),
				$this->settings->get_default_location_label()
			),
			Settings::START_MODE_CUSTOM  => __( 'Enter a hotel name, landmark, or street address.', 'plan-your-day' ),
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

	private function build_config( string $instance_id, string $action_url, array $planner_state, array $start_points, array $category_catalog, string $endpoint_token, bool $should_hydrate_on_load ): array {
		$browse_payload = $this->planner_payload_builder->build_browse_payload( $planner_state );
		$route_payload  = $this->planner_payload_builder->build_route_payload( $planner_state );

		if ( isset( $planner_state['results_empty_state'] ) ) {
			$browse_payload['resultsEmptyState'] = (array) $planner_state['results_empty_state'];
		}

		if ( isset( $planner_state['preview_empty_state'] ) ) {
			$route_payload['emptyPreviewState'] = (array) $planner_state['preview_empty_state'];
		}

		if ( isset( $planner_state['trip_empty_state'] ) ) {
			$route_payload['tripEmptyState'] = (array) $planner_state['trip_empty_state'];
		}

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
			'hydration'       => [
				'shouldHydrateOnLoad' => $should_hydrate_on_load,
			],
			'strings'         => [
				'requestFailed'       => __( 'The planner request could not be completed. Refresh the page and try again.', 'plan-your-day' ),
				'resultsUpdated'      => __( 'Results updated.', 'plan-your-day' ),
				'searchResultsFor'    =>
					/* translators: %s is a search label. */
					__( 'Results for %s', 'plan-your-day' ),
				'categoryResultsExpanded' =>
					/* translators: %s is a category label. */
					__( '%s results expanded.', 'plan-your-day' ),
				'categoryResultsCollapsed' =>
					/* translators: %s is a category label. */
					__( '%s results collapsed.', 'plan-your-day' ),
				'customSearchResultsDescription' => __( 'Custom category search results.', 'plan-your-day' ),
				'customResultsExpanded' => __( 'Custom search results expanded.', 'plan-your-day' ),
				'customResultsCollapsed' => __( 'Custom search results collapsed.', 'plan-your-day' ),
				'tripUpdated'         => __( 'Trip updated.', 'plan-your-day' ),
				'startingPointUpdated' => __( 'Starting point updated.', 'plan-your-day' ),
				'startOptionsExpanded' => __( 'Starting point options expanded.', 'plan-your-day' ),
				'startOptionsCollapsed' => __( 'Starting point options collapsed.', 'plan-your-day' ),
				'openMapsDisabled'   => __( 'Add at least one waypoint before opening the trip in Google Maps.', 'plan-your-day' ),
				'viewInGoogleMaps'   => __( 'View in Google Maps', 'plan-your-day' ),
				'viewPlaceInGoogleMapsLabel' =>
					/* translators: %s is a place label. */
					__( 'View %s in Google Maps', 'plan-your-day' ),
				'addToTrip'          => __( 'Add to trip', 'plan-your-day' ),
				'addWaypointLabel'   =>
					/* translators: %s is a place label. */
					__( 'Add %s to trip', 'plan-your-day' ),
				'inTrip'             => __( 'In trip', 'plan-your-day' ),
				'alreadyInTripAria'  =>
					/* translators: %s is a place label. */
					__( '%s is already in the trip', 'plan-your-day' ),
				'tripEmptyHeading'   => __( 'Start building the trip', 'plan-your-day' ),
				'tripEmptyBody'      => __( 'Search Google by category, then add the exact places you want as walking-trip waypoints.', 'plan-your-day' ),
				'moveUp'             => __( 'Move up', 'plan-your-day' ),
				'moveDown'           => __( 'Move down', 'plan-your-day' ),
				'moveWaypointUpLabel' =>
					/* translators: %s is a place label. */
					__( 'Move %s up in the trip', 'plan-your-day' ),
				'moveWaypointDownLabel' =>
					/* translators: %s is a place label. */
					__( 'Move %s down in the trip', 'plan-your-day' ),
				'removeWaypointLabel' =>
					/* translators: %s is a place label. */
					__( 'Remove %s', 'plan-your-day' ),
				'clearTrip'          => __( 'Clear trip', 'plan-your-day' ),
			],
			'debug'           => defined( 'WP_DEBUG' ) && true === WP_DEBUG,
			'initialState'    => [
				'category'            => $planner_state['category_key'],
				'categorySearch'      => $planner_state['category_search'],
				'selectedWaypointIds' => array_values( $planner_state['selected_waypoint_ids'] ),
				'startMode'           => $planner_state['start_mode'],
				'customStart'         => $planner_state['custom_start'],
			],
			'initialData'     => [
				'browse' => $browse_payload,
				'route'  => $route_payload,
			],
		];
	}

	private function get_current_url(): string {
		$queried_object_id = get_queried_object_id();
		$permalink         = $queried_object_id > 0 ? get_permalink( $queried_object_id ) : false;

		return is_string( $permalink ) && '' !== $permalink ? $permalink : home_url( '/' );
	}
}
