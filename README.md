# Genetic Report Manager Pro

This WordPress plugin manages genetic report uploads, generation and display. It integrates with WooCommerce to associate generated reports with customer orders.

## Features
- Upload and store ZIP files containing genetic data
- Generate PDF reports through a remote API
- Display reports to users via shortcodes
- WooCommerce integration for associating reports with orders
- Admin dashboard for viewing reports, uploads and logs

## Installation
1. Copy the plugin directory `genetic-report-manager` into your WordPress `wp-content/plugins/` directory.
2. Run `composer install` inside the plugin folder to install the TCPDF library.
3. Activate **Genetic Report Manager Pro** from the WordPress Plugins screen.
4. On activation the plugin creates its database tables automatically.

## Usage
- Configure settings under **GRM Reports → Settings** in the WordPress admin.
- Reports can be uploaded through the front‑end shortcode or generated automatically after checkout.
- Admin pages provide views for reports, uploads and system logs.

## Development
The code is organized into separate folders:
- `includes` – core classes such as database access and report generation
- `public` – frontend assets, AJAX and WooCommerce integration
- `admin` – admin pages and helper classes
- `assets` – CSS and JavaScript files

See `docs/CLASS_REFERENCE.md` for a description of the main classes.

