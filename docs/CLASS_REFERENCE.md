# Genetic Report Manager Pro - Class Reference

This document provides a brief overview of the main classes available in the plugin.

## GRM_Database
Handles creation and queries for the plugin tables `user_uploads` and `user_reports`.
- **init()** – assigns table names using the current `$wpdb` prefix.
- **create_tables()** – creates required tables via `dbDelta`.
- **create_upload() / get_upload() / delete_upload()** – basic CRUD for uploads.
- **create_report() / get_report() / update_report()** – basic CRUD for reports.

## GRM_Logger
Simple wrapper used for debugging and logging events.
- **log()** – store a log entry and optionally write to the database.
- **get_logs()** – fetch recent logs.
- **clear_logs()** – truncate the log table.

## GRM_File_Handler
Manages validation and storage of uploaded ZIP files.
- **handle_upload()** – validates and saves an upload.
- **delete_upload_file()** – removes an uploaded file and associated DB entry.
- **cleanup_temp_files()** – removes stale files from the temporary directory.

## GRM_Report_Generator
Responsible for communicating with the external API to generate reports.
- **generate_report()** – creates a new report for an upload and WooCommerce order.
- **regenerate_report()** – retries generation for an existing report.
- **get_report_statistics()** – summary information for the admin dashboard.

## GRM_API_Handler
Wrapper around the remote API endpoints used for report generation.
- **create_report()** – triggers report creation on the API server.
- **download_report()** – retrieves the final PDF data.
- **get_report_status()** – polls the API for progress.

## GRM_WooCommerce_Integration
Hooks into WooCommerce to tie uploads and reports to customer orders.
- Validates the cart and ensures an upload is present.
- Triggers background report generation when an order is completed.

## GRM_Admin_Orders
Adds a meta box on WooCommerce order pages listing any generated reports.

## GRM_Admin_Reports
Placeholder for future admin report utilities.

Additional classes under the `admin/` and `public/` directories provide AJAX handlers, shortcodes and admin pages.
