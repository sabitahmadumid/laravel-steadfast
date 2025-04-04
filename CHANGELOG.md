# Changelog

All notable changes to `laravel-steadfast` will be documented in this file.

## v1.0.0 - 2025-04-04

### New Features

Order Management:
Added createOrder method to create a single order.
Added bulkCreate method to handle bulk order creation with optional queue processing.
Added methods to check order status by consignment ID, invoice, and tracking code.
Balance Management:
Added getBalance method to retrieve the current balance.
HTTP Client Initialization:
Added initializeHttpClient method to set up the HTTP client with base URL, timeout, and headers.
