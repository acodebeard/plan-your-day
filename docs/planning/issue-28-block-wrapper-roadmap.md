# Issue 28: Block Wrapper Roadmap

This draft scopes the first implementation slice for block-editor support.

## Current Constraints

- The shortcode path is the only supported frontend entry point.
- The block must reuse the shared renderer instead of duplicating planner
  markup or request logic.
- The repo does not currently include a block registration or editor preview
  asset pipeline.
- The original issue depends on the shortcode-first work already being stable.

## Proposed First Slice

Build a **server-rendered wrapper block** with the smallest viable editor
surface:

1. Add block registration metadata under the plugin directory.
2. Register the block on `init`.
3. Use a `render_callback` that delegates to the existing planner renderer.
4. Provide a lightweight editor script that shows a usable placeholder/preview
   state without recreating the live planner client.
5. Keep frontend assets shortcode-compatible so the block only enqueues planner
   assets when rendered.

## Non-Goals For The First PR

- Full live planner behavior inside the editor canvas.
- Block-specific settings beyond what the shared renderer already supports.
- A separate block-only render path.
- Multi-block/page-builder interoperability work beyond the first wrapper.

## Suggested File-Level Plan

- `plugin/plan-your-day/src/Block/PlannerBlock.php`
  Register metadata and the render callback.
- `plugin/plan-your-day/src/Plugin.php`
  Wire block registration into plugin bootstrap.
- `plugin/plan-your-day/block.json`
  Define block metadata and editor asset handles.
- `plugin/plan-your-day/assets/js/`
  Add a minimal editor-only preview script.

## Acceptance Notes

The first delivery should satisfy the issue by proving:

- block registration works
- frontend rendering reuses shared output
- editor UX is understandable
- assets do not load globally when the block is unused

## Validation Expectations

- PHP lint on the plugin
- PHPCS
- PHPUnit smoke coverage for block registration wiring where feasible
- manual editor test in WordPress 6.8+
