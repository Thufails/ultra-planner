# Ultra Planner — Project Handoff

This file keeps the project's working context available when the Codex account or chat changes. Read it together with `README.md` and `Project Documentation & MVP.docx` before making substantial changes.

## Product

Ultra Planner is a Laravel web app for planning trail and ultrarunning routes on a map, reviewing route and terrain information, saving routes, and exporting GPX files for compatible apps or devices.

## Stack and architecture

- PHP 8.3+, Laravel 13, MySQL, Vite, and vanilla JavaScript.
- Leaflet and OpenStreetMap for maps; Nominatim for place search.
- Public OSRM compatible walking routing service for **Follow roads**; OpenTopoData SRTM 30 m for estimated terrain elevation.
- Main map interaction code is in `resources/js/route-editor.js`.
- Route, waypoint, checkpoint, and export endpoints are in `app/Http/Controllers/`.
- Geometry, checkpoint, and elevation calculations are in `app/Services/`.
- Blade pages are in `resources/views/`; database migrations are in `database/migrations/`.

## Product decisions to preserve

- Direct line mode has no 40-control-point cap. **Follow roads** is limited to 40 control points per public routing request.
- Search results move the map; they do not add route points.
- Checkpoints are derived at 5 km intervals and at the finish. They are calculated from saved track geometry and do not have their own table/model.
- Manual waypoints are stored in `waypoints`. The currently supported types are `water_refill` (Water station), `checkpoint` (Checkpoint), and `food_stop` (Food station).
- Waypoint changes are saved automatically when the user selects a map location; saved waypoints can be edited or deleted.
- Elevation is an estimate from SRTM 30 m terrain data. Never substitute zero or invented values when elevation is unavailable.
- GPX export contains route geometry, available elevation, manual waypoints, and automatic 5 km checkpoints. Waypoints are emitted in route-distance order.
- Do not add invented activity timestamps, pace, or elapsed time to GPX. A route plan and a recorded activity have different semantics. Turn-by-turn support and app/device compatibility must not be claimed without testing.
- User plans to test GPX behavior with Strava. The displayed Strava message said time information was missing; revisit this during Phase 3 planning, without fabricating activity data.
- Authentication, GPX import, race pace/ETA plans, fueling/hydration plans, and FIT/TCX export are not implemented yet.

## Development status and next phase

- Phase 0: project setup and foundation — complete.
- Phase 1: route builder, route storage/editing, elevation profile, and GPX export — implemented.
- Phase 2: route analysis, derived checkpoints, and manual waypoint management — implemented. GPX import compatibility testing remains a user-side follow-up.
- Phase 3 is next: race plan and estimated checkpoint/finish times. The MVP brief describes inputs such as start time, target pace, stop duration, checkpoint interval, and optionally pace by segment; outputs include arrival/departure estimates at checkpoints and estimated finish time. Review the brief and agree the exact scope and schema before writing migrations.

## Collaboration workflow

- The user prefers incremental work and explanations of each change.
- For a phase that requires schema changes, discuss and create the migration first. Stop there so the user can run `php artisan migrate` and confirm the result. Then continue with models/controllers/routes, followed by Blade and JavaScript as needed.
- Do not run migrations against the user's local database unless explicitly requested. Do not edit `.env` or expose its contents.
- The user may want to discuss behavior before implementation. Answer the discussion first; implement when asked to proceed or when the request clearly authorizes it.
- Report which files changed, why, and how they were checked. Do not claim a test or platform import was run unless it actually was.

## Local setup reminder

Use `README.md` for current installation steps. Local development uses the user's configured `.env`, MySQL, `php artisan serve`, and `npm run dev`. Never copy secrets from `.env` into this file or other documentation.
