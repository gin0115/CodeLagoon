# CodeLagoon

CodeLagoon is a WordPress-based site for creating, sharing, and forking code snippets ("lagoons").
Each lagoon is a small collection of files in any language, served with syntax highlighting,
markdown rendering, zip download, fork lineage, and comments.

> Created with the [a8cteam51/team51-project-scaffold](https://github.com/a8cteam51/team51-project-scaffold).
> The build tooling, PHP/JS linting setup, and folder layout inherit from that scaffold —
> it's still the single source of truth for how the pipeline works, so read its docs for
> anything this README doesn't explain.

---

## Repository layout

This repo only tracks the `wp-content` directory. Inside it, the three load-bearing pieces are:

- **`themes/codelagoon/`** — the block theme. Presentation only. Holds site-wide theme
  palettes (see `src/Theme/`), templates, parts, SCSS. Nothing here touches the data layer.
- **`mu-plugins/codelag-features/`** — the domain layer. CPT (`lagoon`), three taxonomies,
  custom `wp_codelag_lagoon_files` table + repository, REST routes under `codelag/v1`,
  fork service, download handler, comments enhancement, shortcodes.
- **`mu-plugins/codelag-blocks/`** — Gutenberg blocks only. `codelag/lagoon-viewer`
  (the file display), `codelag/lagoon-prose` (the editable slots around the viewer).
  Auto-discovered from `blocks/build/*` at runtime. No domain logic lives here.

Everything else is scaffolding around those three.

---

## Key features

- **Lagoon CPT** with a random auto-generated slug (so IDs aren't enumerable).
- **Monaco editor** in wp-admin for writing files, with per-user theme choice stored in
  user meta.
- **Prism syntax highlighting** + **GitHub-styled markdown** on the frontend, with a
  runtime theme picker (loads stylesheets from jsDelivr CDN, persists in `localStorage`).
- **Site-wide theme palettes** (`src/Theme/SiteThemes`) — every component's colour /
  radius / spacing reads from `var(--cl-*)`, palettes swap by flipping
  `data-site-theme` on `<html>`. Per-user choice in wp-admin profile.
- **Forking** — clone a lagoon into a new draft owned by the forker. Lineage meta
  (`_lagoon_forked_from` / `_lagoon_fork_root` / `_lagoon_fork_history`) rendered
  on the frontend via the `[codelag_fork_lineage]` shortcode.
- **Zip download** — `[codelag_download_button]` → `?codelag_download_lagoon=<id>`
  streams a zip of all files.
- **Share + theme + fork** action buttons in the lagoon header card (all shortcodes).
- **Comments** — full-width section, logged-in only, TinyMCE editor via `wp_editor()`.

---

## Build pipeline

Inherited from the scaffold — see the
[scaffold README](https://github.com/a8cteam51/team51-project-scaffold) for the full
picture. Short version:

- `npm start` — parallel watch: theme SCSS, theme JS, features JS, blocks JS/SCSS.
- `npm run build` — production build of everything.
- `composer internationalize` — regenerates `.pot` files for theme / features / blocks.
- `composer lint:php` — runs phpcs + phpmd + phpstan against all three packages.

**Note on Monaco:** `webpack.config.js` at the project root registers
`monaco-editor-webpack-plugin` so the lagoon-viewer block's editor bundle initialises
properly. Changing `webpack.config.js` requires restarting `npm start`.

---

## Credits

- **Authors:** gin0115 & WP Special Projects
- **Scaffolding:** [a8cteam51/team51-project-scaffold](https://github.com/a8cteam51/team51-project-scaffold)
- **License:** GPL-3.0-or-later
