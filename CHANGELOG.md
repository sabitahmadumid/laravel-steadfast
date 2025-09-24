# Changelog

All notable changes to `laravel-steadfast` will be documented in this file.

## v2.0.0 - 2025-09-24

### 🚀 Major Release - Complete Package Rewrite

This is a major release with significant improvements, new features, and breaking changes. The package has been completely rewritten to provide a production-ready, enterprise-level integration with SteadFast Courier API.

### ✨ New Features

#### **Complete API Coverage**
- **Return Requests API**: Full implementation of return request management
  - Create return requests by invoice, consignment ID, or tracking code
  - Get single return request details
  - Get all return requests with status filtering
  - Rich return status objects with helper methods

#### **Enhanced Order Management**
- **Extended Order Fields**: Support for all SteadFast API fields
  - `alternative_phone` - Alternative contact number
  - `recipient_email` - Customer email address
  - `item_description` - Detailed item description
  - `total_lot` - Total number of items
  - `delivery_type` - Home delivery (0) or Point delivery (1)
- **Rich Response Objects**: Type-safe DTOs for all API responses
- **Enhanced Validation**: Comprehensive validation with custom error messages

#### **Advanced Bulk Processing**
- **Event System**: Complete event-driven architecture
  - `BulkOrderStarted` - Fired when bulk processing begins
  - `BulkOrderCompleted` - Fired when bulk processing completes
  - `BulkOrderFailed` - Fired when bulk processing fails
- **Improved Queue Management**: 
  - Unique job IDs to prevent duplicates
  - Exponential backoff retry strategy
  - Job batching support
  - Enhanced error handling and recovery

#### **Intelligent Caching System**
- **Response Caching**: Configurable caching for API responses
- **Smart Cache Keys**: Endpoint-specific cache management
- **Cache Invalidation**: Manual and automatic cache clearing
- **Multi-Store Support**: Redis, Database, and File cache support

#### **Comprehensive Monitoring & Logging**
- **Enhanced Logging Model**: Advanced query scopes and statistics
- **Performance Metrics**: Request duration tracking and success rates
- **Automatic Log Cleanup**: Configurable log retention policies
- **Detailed Statistics**: API usage analytics and monitoring

#### **Artisan Commands**
- **`steadfast:test`**: Test API connection and configuration
- **`steadfast:stats`**: View comprehensive usage statistics
- **`steadfast:cleanup`**: Clean up old logs and maintain database

#### **Advanced Error Handling**
- **Specific Exception Types**: 
  - `AuthenticationError` for credential issues
  - `ValidationError` for input validation failures
  - `RateLimitError` for API rate limiting
  - `NotFoundError` for missing resources
  - `ServiceUnavailable` for API downtime
- **Rich Error Context**: Detailed error information for debugging
- **Retry Logic**: Automatic retry with configurable conditions

#### **Security & Data Protection**
- **Sensitive Data Filtering**: API keys and secrets filtered from logs
- **Enhanced Validation**: Strict input validation with customizable rules
- **Secure Logging**: Configurable request/response logging levels

### 🔧 Improvements

#### **Performance Optimizations**
- **Connection Management**: Persistent HTTP connections with pooling
- **Memory Efficiency**: Optimized memory usage for large datasets
- **Chunked Processing**: Efficient batch processing with configurable chunk sizes
- **Lazy Loading**: On-demand resource loading for better performance

#### **Developer Experience**
- **Type Safety**: Full PHP 8.1+ type hints and return types
- **Rich Documentation**: Comprehensive inline documentation
- **Better Testing Tools**: Built-in commands for testing and debugging
- **IDE Support**: Complete PHPDoc annotations for better IDE integration

#### **Configuration Management**
- **Extended Configuration**: 50+ configuration options
- **Environment Variables**: Comprehensive .env support
- **Validation Settings**: Configurable validation rules
- **Performance Tuning**: Detailed timeout and retry settings

### 🛠️ Technical Improvements

#### **Code Quality**
- **PHP 8.1+ Requirements**: Modern PHP features and improvements
- **PSR Standards**: Full PSR-4 autoloading and PSR-12 coding standards
- **Type Safety**: Strict typing throughout the codebase
- **Error Handling**: Comprehensive exception handling

#### **Architecture**
- **Event-Driven Design**: Complete event system for monitoring
- **Service Container**: Proper Laravel service container integration
- **Dependency Injection**: Full DI support throughout the package
- **Facade Pattern**: Clean facade implementation for easy usage

#### **Database**
- **Enhanced Migration**: Improved database schema with indexes
- **Model Improvements**: Rich Eloquent model with scopes and methods
- **Query Optimization**: Optimized database queries for better performance
- **Data Integrity**: Enhanced data validation and constraints

### 🔄 Breaking Changes

- **PHP Version**: Minimum PHP version raised to 8.1
- **Return Types**: All methods now return typed response objects instead of arrays
- **Configuration**: Configuration structure has been reorganized
- **Validation**: Enhanced validation may reject previously accepted data
- **Exception Handling**: New exception types require updated catch blocks

### 📦 Dependencies

- **Updated Dependencies**: Latest versions of all dependencies
- **Laravel Support**: Full support for Laravel 10, 11, and 12
- **PHP 8.1+**: Modern PHP features and performance improvements

### 🔗 Migration Guide

For users upgrading from v1.x:

1. Update PHP to 8.1 or higher
2. Update configuration file: `php artisan vendor:publish --tag="laravel-steadfast-config" --force`
3. Run new migrations: `php artisan migrate`
4. Update method calls to handle new return types
5. Update exception handling for new exception types
6. Test configuration: `php artisan steadfast:test`

### 📊 Statistics

- **10+ New DTOs**: Rich data transfer objects for type safety
- **3 New Artisan Commands**: Built-in management commands
- **9 API Endpoints**: Complete SteadFast API coverage
- **50+ Configuration Options**: Extensive customization
- **5 Event Types**: Comprehensive event system
- **15+ Exception Types**: Detailed error handling

---

## v1.0.0 - 2025-04-04

### Initial Release

#### Order Management
- Basic `createOrder` method for single order creation
- Basic `bulkCreate` method for bulk order processing
- Order status checking by consignment ID, invoice, and tracking code

#### Balance Management
- Basic `getBalance` method to retrieve account balance

#### HTTP Client
- Basic HTTP client initialization with headers and timeout
