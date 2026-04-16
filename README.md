# Destination Kona Coast Plan Your Day

Standalone PHP version of the Destination Kona Coast trip planner. This copy does not load WordPress and is intended to run from this directory with `index.php`, `plan.css`, `plan.js`, and the local icon assets.

## Repository Setup

1. Create a new GitHub repository.
2. Add this directory as the repository root.
3. Keep the private API key file out of git. The active key file is `c88e3e98.php`, and `.gitignore` excludes it.
4. Copy `c88e3e98.example.php` to `c88e3e98.php`.
5. Open `c88e3e98.php` and add the Google Maps API key where the empty string is defined.
6. In Google Cloud, make sure the key can use Maps Embed API, Places API (New), and Geocoding API.
7. Restrict the key in Google Cloud before using it outside local development.
8. Serve the directory through PHP/Apache, then open `/plan-your-day/` in a browser.

## Files

- `index.php` renders the page, handles focused JSON requests, and contains the standalone PHP runtime.
- `c88e3e98.php` is the private local API key config and should not be committed.
- `c88e3e98.example.php` is the safe template for creating the private config file.
- `plan.css` contains the planner reset and component styles.
- `plan.js` handles local UI state, focused data requests, trip selection, and reordering.
- `icons/` contains local SVG assets used by the planner UI.
- `.htaccess` blocks direct browser access to private key config files.

## Creating A Trip

1. Choose a starting point.
   Select Current location, Kailua Pier, or Custom starting point. Current location uses Kailua Pier for the on-page preview and lets Google Maps start from the visitor's location during handoff. Custom starting point lets the visitor enter a hotel, resort, vacation rental, or address.

2. Open a category.
   Choose Coffee, Food, Shopping, Beaches, History / culture, Scenic spots, or Other tourist activities. The planner searches Google for real places near the selected starting area.

3. Review the results.
   Each result includes the place name, address, and a Google Maps link when Google provides one. Distance hints are approximate straight-line distances from the on-page starting area.

4. Add places to the trip.
   Use Add to trip on any result. Added places become exact waypoints for the walking trip.

5. Reorder the waypoints.
   Drag waypoints when JavaScript enhancement is active, or use Move up and Move down. The visible order becomes the walking route order.

6. Remove anything unwanted.
   Use Remove on a waypoint, or Clear trip to start over while keeping the page available.

7. Check the preview.
   The preview starts as a category search map. After one or more waypoints are selected, it switches to walking directions when a valid Maps Embed API key is configured.

8. Open the route in Google Maps.
   Use Go! to hand the selected route to Google Maps. The link still works as a Google Maps search or route handoff even if the embedded preview is unavailable.

## Accessibility Notes

The component uses semantic form controls, real buttons for actions, live status messaging, keyboard-accessible move controls, visible focus states from `plan.css`, and a no-JavaScript form fallback. Any future changes should preserve WCAG 2.1 or better.
