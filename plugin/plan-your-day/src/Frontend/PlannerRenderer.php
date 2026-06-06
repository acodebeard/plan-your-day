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
			$planner_state = InitialPlannerHydration::apply_loading_placeholders( $planner_state, $this->settings->get_frontend_copy() );
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
		$color_mode_default  = $this->settings->get_color_mode_default();
		$initial_color_mode  = Settings::COLOR_MODE_SYSTEM === $color_mode_default ? '' : $color_mode_default;

		ob_start();
		?>
		<section
			class="plan-your-day"
			id="<?php echo esc_attr( $instance_id ); ?>"
			aria-labelledby="<?php echo esc_attr( $title_id ); ?>"
			data-plan-color-mode-default="<?php echo esc_attr( $color_mode_default ); ?>"
			<?php if ( '' !== $initial_color_mode ) : ?>
				data-plan-color-mode="<?php echo esc_attr( $initial_color_mode ); ?>"
			<?php endif; ?>
			data-plan-root>
			<div class="plan-your-day__surface">
				<header class="plan-your-day__hero">
					<div class="plan-your-day__hero-copy">
						<?php if ( '' !== $this->settings->get_frontend_copy_value( 'hero_eyebrow' ) ) : ?>
							<p class="plan-your-day__eyebrow"><?php echo esc_html( $this->settings->get_frontend_copy_value( 'hero_eyebrow' ) ); ?></p>
						<?php endif; ?>
						<h2 id="<?php echo esc_attr( $title_id ); ?>"><?php echo esc_html( $this->settings->get_frontend_copy_value( 'hero_title' ) ); ?></h2>
						<?php if ( '' !== $this->settings->get_frontend_copy_value( 'hero_intro' ) ) : ?>
							<p class="plan-your-day__intro"><?php echo esc_html( $this->settings->get_frontend_copy_value( 'hero_intro' ) ); ?></p>
						<?php endif; ?>
					</div>
					<div class="plan-your-day__hero-tools">
						<button
							class="plan-your-day__color-mode-toggle"
							type="button"
							hidden
							data-plan-color-mode-toggle
							aria-pressed="false">
							<span data-plan-color-mode-toggle-label><?php esc_html_e( 'Dark mode', 'plan-your-day' ); ?></span>
						</button>
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
				<h2 id="<?php echo esc_attr( $title_id ); ?>"><?php echo esc_html( $this->settings->get_frontend_copy_value( 'setup_notice_title' ) ); ?></h2>
				<p>
					<?php echo esc_html( $this->settings->format_frontend_copy( 'setup_notice_body', [ 'settings' => $missing ] ) ); ?>
				</p>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<p>
						<a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . Settings::PAGE_SLUG ) ); ?>">
							<?php echo esc_html( $this->settings->get_frontend_copy_value( 'setup_notice_link' ) ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	private function render_start_card( string $instance_id, array $planner_state, array $start_points ): void {
		$start_panel_id     = $instance_id . '-start-panel';
		$custom_start_id    = $instance_id . '-custom-start';
		$start_heading_id   = $instance_id . '-start-heading';
		$selected_waypoints = (array) $planner_state['selected_waypoint_ids'];
		?>
		<section class="plan-your-day__card" aria-labelledby="<?php echo esc_attr( $start_heading_id ); ?>">
			<div class="plan-your-day__card-header">
				<div>
					<h3 id="<?php echo esc_attr( $start_heading_id ); ?>"><?php echo esc_html( $this->settings->get_frontend_copy_value( 'start_card_heading' ) ); ?></h3>
				</div>
				<button
					class="plan-your-day__start-toggle"
					type="button"
					hidden
					data-plan-start-toggle
					aria-controls="<?php echo esc_attr( $start_panel_id ); ?>"
					aria-expanded="true">
					<span data-plan-start-toggle-label><?php esc_html_e( 'Hide options', 'plan-your-day' ); ?></span>
					<span class="plan-your-day__start-toggle-chevron" aria-hidden="true"></span>
				</button>
			</div>

			<div class="plan-your-day__start-panel" id="<?php echo esc_attr( $start_panel_id ); ?>" data-plan-start-panel>
				<fieldset class="plan-your-day__fieldset">
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
									<?php if ( '' !== $start_point['description'] ) : ?>
										<span class="plan-your-day__start-description"><?php echo esc_html( $start_point['description'] ); ?></span>
									<?php endif; ?>
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
							placeholder="<?php echo esc_attr( $this->settings->get_frontend_copy_value( 'custom_start_placeholder' ) ); ?>"
							autocomplete="street-address"
							data-plan-custom-start>
					</div>
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
		$category_help_text        = __( 'Search for any category or use a category shortcut to load Google results.', 'plan-your-day' );
		?>
		<section class="plan-your-day__card" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
			<div class="plan-your-day__card-header">
				<div>
					<h3 id="<?php echo esc_attr( $heading_id ); ?>" data-plan-results-heading><?php echo esc_html( $this->settings->get_frontend_copy_value( 'category_card_heading' ) ); ?></h3>
					<?php if ( '' !== $category_help_text ) : ?>
						<p id="<?php echo esc_attr( $category_help_id ); ?>"><?php echo esc_html( $category_help_text ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<div class="plan-your-day__category-search">
				<label for="<?php echo esc_attr( $category_search_id ); ?>" class="screen-reader-text"><?php esc_html_e( 'Search categories', 'plan-your-day' ); ?></label>
				<div class="plan-your-day__category-search-controls">
					<input
						id="<?php echo esc_attr( $category_search_id ); ?>"
						type="search"
						name="category_search"
						value="<?php echo esc_attr( (string) $planner_state['category_search'] ); ?>"
						placeholder="<?php echo esc_attr( $this->settings->get_frontend_copy_value( 'category_search_placeholder' ) ); ?>"
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
												/* translators: %s: active Google place search label. */
												__( 'Results for %s', 'plan-your-day' ),
												(string) $planner_state['active_search_label']
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
								<span class="plan-your-day__category-trigger-chevron"></span>
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
						<?php $this->render_search_results_panel( $planner_state, $results_empty_state ); ?>
					</div>
				</div>
			</div>

			<?php if ( [] !== $category_catalog ) : ?>
				<div class="plan-your-day__category-accordion" <?php echo '' !== $category_help_text ? 'aria-describedby="' . esc_attr( $category_help_id ) . '"' : ''; ?>>
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
										<span class="plan-your-day__category-trigger-chevron"></span>
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
									<?php if ( $is_active ) : ?>
										<?php $this->render_search_results_panel( $planner_state, $results_empty_state ); ?>
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

	private function render_search_results_panel( array $planner_state, array $results_empty_state ): void {
		$search_results = array_values( (array) $planner_state['search_results'] );
		?>
		<div class="plan-your-day__results-body" data-plan-results-body>
			<?php if ( [] !== $search_results ) : ?>
				<ul class="plan-your-day__results-list" data-plan-results-list>
					<?php foreach ( $search_results as $result ) : ?>
						<?php $this->render_search_result( $result, (array) $planner_state['selected_waypoint_ids'] ); ?>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<div class="plan-your-day__results-empty" data-plan-results-empty>
					<h4><?php echo esc_html( $results_empty_state['heading'] ); ?></h4>
					<p><?php echo esc_html( $results_empty_state['body'] ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		<?php if ( ! empty( $planner_state['has_more_results'] ) ) : ?>
			<div class="plan-your-day__load-more" data-plan-load-more-wrap>
				<button class="plan-your-day__load-more-button" type="button" data-plan-load-more-button>
					<?php esc_html_e( 'More results', 'plan-your-day' ); ?>
				</button>
			</div>
		<?php endif; ?>
		<?php
	}

	private function render_search_result( array $result, array $selected_waypoint_ids ): void {
		$place_id      = (string) ( $result['id'] ?? '' );
		$label         = (string) ( $result['label'] ?? '' );
		$is_in_trip    = in_array( $place_id, $selected_waypoint_ids, true );
		$map_link_aria = sprintf(
			/* translators: %s: place name. */
			__( 'View %s in Google Maps', 'plan-your-day' ),
			$label
		);
		$in_trip_aria  = sprintf(
			/* translators: %s: place name. */
			__( '%s is already in the trip', 'plan-your-day' ),
			$label
		);
		$add_trip_aria = sprintf(
			/* translators: %s: place name. */
			__( 'Add %s to trip', 'plan-your-day' ),
			$label
		);
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
						aria-label="<?php echo esc_attr( $map_link_aria ); ?>">
						<?php esc_html_e( 'View in Google Maps', 'plan-your-day' ); ?>
					</a>
				<?php endif; ?>

				<?php if ( $is_in_trip ) : ?>
					<span class="plan-your-day__result-added" aria-label="<?php echo esc_attr( $in_trip_aria ); ?>">
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
						aria-label="<?php echo esc_attr( $add_trip_aria ); ?>">
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
		$trip_help_text   = $this->settings->get_frontend_copy_value( 'trip_card_help' );
		$trip_empty_state = isset( $planner_state['trip_empty_state'] )
			? (array) $planner_state['trip_empty_state']
			: [
				'heading' => $this->settings->get_frontend_copy_value( 'trip_empty_heading' ),
				'body'    => $this->settings->get_frontend_copy_value( 'trip_empty_body' ),
			];
		?>
		<section class="plan-your-day__card" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
			<div class="plan-your-day__card-header">
				<div>
					<h3 id="<?php echo esc_attr( $heading_id ); ?>" tabindex="-1" data-plan-trip-heading><?php echo esc_html( $this->settings->get_frontend_copy_value( 'trip_card_heading' ) ); ?></h3>
					<?php if ( '' !== $trip_help_text ) : ?>
						<p id="<?php echo esc_attr( $help_id ); ?>"><?php echo esc_html( $trip_help_text ); ?></p>
					<?php endif; ?>
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

			<div data-plan-trip-region data-plan-trip-help-id="<?php echo esc_attr( '' !== $trip_help_text ? $help_id : '' ); ?>">
				<?php if ( [] !== $waypoints ) : ?>
					<ol class="plan-your-day__trip-list" <?php echo '' !== $trip_help_text ? 'aria-describedby="' . esc_attr( $help_id ) . '"' : ''; ?> data-plan-trip-list>
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
						echo esc_attr( $this->settings->format_frontend_copy( 'move_waypoint_up_aria', [ 'place' => $label ] ) );
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
						echo esc_attr( $this->settings->format_frontend_copy( 'move_waypoint_down_aria', [ 'place' => $label ] ) );
						?>
						"
					<?php disabled( $index >= $waypoint_count - 1 ); ?>>
					<?php esc_html_e( 'Move down', 'plan-your-day' ); ?>
					</button>
					<button type="submit" name="remove_waypoint" value="<?php echo esc_attr( $place_id ); ?>" data-plan-action="remove-waypoint" data-plan-route-mutation data-place-id="<?php echo esc_attr( $place_id ); ?>">
						<?php echo esc_html( $this->settings->format_frontend_copy( 'remove_waypoint_label', [ 'place' => $label ] ) ); ?>
					</button>
			</div>
		</li>
		<?php
	}

	private function render_preview_card( string $instance_id, array $planner_state, array $preview_empty_state, bool $maps_link_enabled ): void {
		$heading_id             = $instance_id . '-preview-heading';
		$has_selected_waypoints = [] !== (array) $planner_state['selected_waypoint_ids'];
		?>
		<section class="plan-your-day__card plan-your-day__preview-card" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>" data-plan-preview-card>
			<div class="plan-your-day__card-header">
				<div>
					<h3 id="<?php echo esc_attr( $heading_id ); ?>" tabindex="-1" data-plan-preview-heading><?php echo esc_html( $this->settings->get_frontend_copy_value( 'preview_card_heading' ) ); ?></h3>
					<?php if ( '' !== $this->settings->get_frontend_copy_value( 'preview_card_help' ) ) : ?>
						<p><?php echo esc_html( $this->settings->get_frontend_copy_value( 'preview_card_help' ) ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<div class="screen-reader-text" role="status" aria-live="polite" aria-atomic="true" data-plan-live-region></div>
			<?php $this->render_messages( (array) $planner_state['messages'] ); ?>

			<div class="plan-your-day__map-wrap" data-plan-map-wrap <?php echo '' !== $planner_state['iframe_src'] ? '' : 'hidden'; ?>>
				<iframe
					class="plan-your-day__map-frame"
					title="<?php echo esc_attr( $this->settings->get_frontend_copy_value( 'preview_iframe_title' ) ); ?>"
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
				<p class="plan-your-day__summary-count" data-plan-summary-count <?php echo $has_selected_waypoints ? 'hidden' : ''; ?>><?php echo esc_html( $planner_state['trip_count_label'] ); ?></p>
				<a
					class="plan-your-day__maps-link plan-your-day__maps-link--summary<?php echo $maps_link_enabled ? '' : ' is-disabled'; ?>"
					<?php echo $maps_link_enabled ? 'href="' . esc_url( $planner_state['maps_url'] ) . '"' : ''; ?>
					target="_blank"
					rel="noopener noreferrer"
					data-plan-open-link
					<?php echo $has_selected_waypoints ? '' : 'hidden'; ?>
					<?php echo $maps_link_enabled ? '' : 'aria-disabled="true" tabindex="0" role="button"'; ?>>
					<span data-plan-open-link-label><?php echo esc_html( $planner_state['maps_link_label'] ); ?></span>
				</a>
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
		$default_label = '' !== $this->settings->get_default_location_label()
			? $this->settings->get_default_location_label()
			: __( 'Default location', 'plan-your-day' );
		$choices       = [
			Settings::START_MODE_CURRENT => $this->settings->get_frontend_copy_value( 'start_mode_current_label' ),
			Settings::START_MODE_DEFAULT => $default_label,
			Settings::START_MODE_CUSTOM  => $this->settings->get_frontend_copy_value( 'start_mode_custom_label' ),
		];
		$descriptions  = [
			Settings::START_MODE_DEFAULT => sprintf(
				/* translators: %s: default starting point label. */
				__( 'Start from %s.', 'plan-your-day' ),
				$default_label
			),
			Settings::START_MODE_CUSTOM  => $this->settings->get_frontend_copy_value( 'start_mode_custom_description' ),
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
			'colorModeDefault' => $this->settings->get_color_mode_default(),
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
				'requestFailed'            => $this->settings->get_frontend_copy_value( 'request_failed' ),
				'resultsUpdated'           => $this->settings->get_frontend_copy_value( 'results_updated_announcement' ),
				'searchResultsFor'         => __( 'Results for {search}', 'plan-your-day' ),
				'moreResultsButton'        => __( 'More results', 'plan-your-day' ),
				'loadingMoreResults'       => $this->settings->get_frontend_copy_value( 'loading_more_results_status' ),
				'loadedMoreResults'        => $this->settings->get_frontend_copy_value( 'loaded_more_results_status' ),
				'noMoreResults'            => $this->settings->get_frontend_copy_value( 'no_more_results_status' ),
				'loadMoreError'            => $this->settings->get_frontend_copy_value( 'load_more_results_error_status' ),
				'categoryResultsExpanded'  => $this->settings->get_frontend_copy_value( 'category_results_expanded_announcement' ),
				'categoryResultsCollapsed' => $this->settings->get_frontend_copy_value( 'category_results_collapsed_announcement' ),
				'customSearchResultsDescription' => __( 'Custom category search results.', 'plan-your-day' ),
				'customResultsExpanded'    => $this->settings->get_frontend_copy_value( 'custom_results_expanded_announcement' ),
				'customResultsCollapsed'   => $this->settings->get_frontend_copy_value( 'custom_results_collapsed_announcement' ),
				'tripUpdated'              => $this->settings->get_frontend_copy_value( 'trip_updated_announcement' ),
				'startingPointUpdated'     => $this->settings->get_frontend_copy_value( 'starting_point_updated_announcement' ),
				'showStartOptions'         => __( 'Show options', 'plan-your-day' ),
				'hideStartOptions'         => __( 'Hide options', 'plan-your-day' ),
				'lightModeLabel'           => __( 'Light mode', 'plan-your-day' ),
				'darkModeLabel'            => __( 'Dark mode', 'plan-your-day' ),
				'startOptionsExpanded'     => '',
				'startOptionsCollapsed'    => '',
				'openMapsDisabled'         => $this->settings->get_frontend_copy_value( 'open_maps_disabled_announcement' ),
				'viewInGoogleMaps'         => __( 'View in Google Maps', 'plan-your-day' ),
				'viewPlaceInGoogleMapsLabel' => __( 'View {place} in Google Maps', 'plan-your-day' ),
				'addToTrip'                => __( 'Add to trip', 'plan-your-day' ),
				'addWaypointLabel'         => __( 'Add {place} to trip', 'plan-your-day' ),
				'inTrip'                   => __( 'In trip', 'plan-your-day' ),
				'alreadyInTripAria'        => __( '{place} is already in the trip', 'plan-your-day' ),
				'tripEmptyHeading'         => $this->settings->get_frontend_copy_value( 'trip_empty_heading' ),
				'tripEmptyBody'            => $this->settings->get_frontend_copy_value( 'trip_empty_body' ),
				'moveUp'                   => __( 'Move up', 'plan-your-day' ),
				'moveDown'                 => __( 'Move down', 'plan-your-day' ),
				'moveWaypointUpLabel'      => $this->settings->get_frontend_copy_value( 'move_waypoint_up_aria' ),
				'moveWaypointDownLabel'    => $this->settings->get_frontend_copy_value( 'move_waypoint_down_aria' ),
				'removeWaypointLabel'      => $this->settings->get_frontend_copy_value( 'remove_waypoint_label' ),
				'clearTrip'                => __( 'Clear trip', 'plan-your-day' ),
				'notSelected'              => $this->settings->get_frontend_copy_value( 'not_selected' ),
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
