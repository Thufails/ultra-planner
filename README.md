# 🏔️ Ultra Planner

A web planner for designing trail and ultrarunning routes on a map, reviewing terrain estimates, saving routes, and downloading them as GPX files.

## 🗺️ Features

- A home page and **My Routes** page for creating, opening, editing, and deleting routes.
- Interactive maps powered by Leaflet and OpenStreetMap.
- Place search suggestions and the browser's **My Location** feature.
- Two route drawing modes:
  - **Direct line** connects control points in order and has no 40-point limit.
  - **Follow roads** requests a walking route from a public routing service, with a maximum of 40 control points per request.
- Route distance, control point count, and automatically generated checkpoints every 5 km plus a finish point.
- Estimated elevation and an elevation profile based on 30 m SRTM terrain data via OpenTopoData.
- Manual waypoints on saved routes: **Water station**, **Checkpoint**, and **Food station**. Waypoint locations can be selected or moved on the map.
- GPX export for saved routes, including the track, available elevation data, manual waypoints, and automatic checkpoints.

## ⚙️ Technology

| Area | Technology |
| --- | --- |
| Backend | PHP 8.3+, Laravel 13 |
| Database | MySQL |
| Frontend | Node.js, npm, Vite |
| Mapping | Leaflet, OpenStreetMap, Nominatim |
| Routing and elevation | Public routing service, OpenTopoData (SRTM 30 m) |

## 🚀 Run locally

Make sure PHP 8.3+, Composer, MySQL, Node.js, and npm are installed. On Windows, Laragon can provide PHP and MySQL.

- Install PHP dependencies with `composer install`.
- Make sure `.env` is configured for your local MySQL database and that the database has been created. If `.env` does not exist, copy `.env.example` first.
- Generate the application key with `php artisan key:generate`.
- Run database migrations with `php artisan migrate`.
- Install frontend dependencies with `npm install`.
- Run `php artisan serve` and `npm run dev` in separate terminals.
- Open the URL shown by Artisan, usually `http://127.0.0.1:8000`.
- Build production frontend assets with `npm run build`.

## 🌐 Maps and terrain data

The app uses the following public services. Endpoints can be changed in `.env`; defaults are defined in `config/services.php`.

| Setting | Default | Purpose |
| --- | --- | --- |
| `NOMINATIM_ENDPOINT` | `https://nominatim.openstreetmap.org/search` | Place search and suggestions |
| `ROUTING_ENDPOINT` | `https://routing.openstreetmap.de/routed-foot/route/v1/driving` | Route calculation when **Follow roads** is enabled |
| `ELEVATION_ENDPOINT` | `https://api.opentopodata.org/v1/srtm30m` | Terrain elevation estimates |

Place search uses caching and request delays. Routing and elevation services also have usage limits and may be slow or temporarily unavailable. If routing fails, users can still draw a route using direct lines. OpenStreetMap attribution is shown on map pages.

## 🥾 Route planning flow

1. Create a route, give it a name, then search for a place or pan the map.
2. Click the map to add control points. Drag points to move them, or use **Undo** to remove the last point.
3. Points are connected by direct lines by default. Enable **Follow roads** to request a walking route from the routing service.
4. Review the distance, checkpoints, and estimated elevation profile. Elevation comes from terrain data, not GPS or a barometric altimeter.
5. Save the route. Reopen it from **My Routes** to edit it or add waypoints.
6. Download the GPX file from the editor or route details page.

Automatic checkpoints are calculated from the route geometry and are not stored in a separate table. Manual waypoints are stored in the `waypoints` table, and their distance is calculated against the route track.

## 📦 GPX notes

- GPX files include the route name, track geometry, and elevation only when available.
- Manual waypoints and automatic checkpoints are exported as GPX waypoints. Apps and watches may display waypoints differently.
- Pace, activity time, and per-point timestamps are not generated because Ultra Planner designs routes rather than recording GPS activities.
- Turn-by-turn navigation is not guaranteed. GPX compatibility should be checked with the target app; Strava import has not yet been tested for this project.
- GPX import is not currently available; the MVP focuses on route planning and export.

## 🧱 Key structure

```text
app/Http/Controllers/   Page, search, routing, waypoint, checkpoint, and export endpoints
app/Models/             Route, RouteControlPoint, RoutePoint, and Waypoint
app/Services/           Geometry, checkpoint, and elevation profile calculations
database/migrations/    routes, route_points, route_control_points, and waypoints schemas
resources/views/        Home, route list/details, and route editor
resources/js/           Map interactions and route editor
routes/web.php          Application routes
```

## 🔭 MVP status

User authentication, GPX import, pace/ETA planning, fueling and hydration plans, and FIT/TCX export are not available yet. The app is being developed incrementally.
