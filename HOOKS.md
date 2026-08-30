# ClassifAI Developer Hooks Reference

This document provides a comprehensive reference for all available hooks (filters and actions) in ClassifAI. Use these hooks to extend and customize the plugin's functionality.

---

## Table of Contents

1. [Service Hooks](#service-hooks)
2. [Feature Hooks](#feature-hooks)
3. [Provider Hooks](#provider-hooks)
4. [Cache Hooks](#cache-hooks)
5. [Rate Limiting Hooks](#rate-limiting-hooks)
6. [Queue Hooks](#queue-hooks)
7. [Usage Tracking Hooks](#usage-tracking-hooks)
8. [Admin Hooks](#admin-hooks)

---

## Service Hooks

### `classifai_services`

Filter the list of available services.

**Parameters:**
- `array $services` - Associative array of service slugs and PHP class namespaces.

**Example:**
```php
add_filter( 'classifai_services', function( $services ) {
    // Add a custom service
    $services['my_custom_service'] = 'MyPlugin\Services\CustomService';
    return $services;
} );
```

---

## Feature Hooks

### `classifai_{feature}_providers`

Filter the providers available for a specific feature.

**Parameters:**
- `array $providers` - Array of provider IDs and labels.

**Example:**
```php
add_filter( 'classifai_feature_title_generation_providers', function( $providers ) {
    // Add a custom provider
    $providers['my_provider'] = __( 'My Custom Provider', 'my-plugin' );
    return $providers;
} );
```

### `classifai_{feature}_roles`

Filter the allowed WordPress roles for a feature.

**Parameters:**
- `array $roles` - Array of role slugs and names.
- `array $default_settings` - Default setting values.

**Example:**
```php
add_filter( 'classifai_feature_title_generation_roles', function( $roles, $settings ) {
    // Remove a specific role
    unset( $roles['author'] );
    return $roles;
}, 10, 2 );
```

### `classifai_{feature}_label`

Filter the feature label displayed in the UI.

**Parameters:**
- `string $label` - The feature label.

**Example:**
```php
add_filter( 'classifai_feature_title_generation_label', function( $label ) {
    return __( 'AI Title Generator', 'my-plugin' );
} );
```

### `classifai_{feature}_get_default_settings`

Filter the default settings for a feature.

**Parameters:**
- `array $defaults` - Default feature settings.
- `object $feature` - Feature instance.

**Example:**
```php
add_filter( 'classifai_feature_title_generation_get_default_settings', function( $defaults, $feature ) {
    $defaults['status'] = '1'; // Enable by default
    return $defaults;
}, 10, 2 );
```

### `classifai_{feature}_sanitize_settings`

Filter settings before they're saved.

**Parameters:**
- `array $new_settings` - Settings being saved.
- `array $current_settings` - Existing settings.

**Example:**
```php
add_filter( 'classifai_feature_title_generation_sanitize_settings', function( $new, $current ) {
    // Custom sanitization
    if ( isset( $new['custom_field'] ) ) {
        $new['custom_field'] = sanitize_text_field( $new['custom_field'] );
    }
    return $new;
}, 10, 2 );
```

### `classifai_{feature}_is_feature_enabled`

Override permission to a specific feature.

**Parameters:**
- `bool $is_enabled` - Is the feature enabled?
- `array $settings` - Current feature settings.

**Example:**
```php
add_filter( 'classifai_feature_title_generation_is_feature_enabled', function( $enabled, $settings ) {
    // Disable for non-premium users
    if ( ! current_user_can( 'premium_user' ) ) {
        return false;
    }
    return $enabled;
}, 10, 2 );
```

### `classifai_{feature}_is_enabled`

Override whether a feature is enabled.

**Parameters:**
- `bool $is_enabled` - Is the feature enabled?
- `array $settings` - Current feature settings.

### `classifai_{feature}_has_access`

Override user access to a feature.

**Parameters:**
- `bool $access` - Current access value.
- `array $settings` - Feature settings.

**Example:**
```php
add_filter( 'classifai_feature_title_generation_has_access', function( $access, $settings ) {
    // Grant access to specific user
    if ( get_current_user_id() === 123 ) {
        return true;
    }
    return $access;
}, 10, 2 );
```

### `classifai_{feature}_post_types`

Filter post types supported for a feature.

**Parameters:**
- `array $post_types` - Array of post types.

**Example:**
```php
add_filter( 'classifai_feature_classification_post_types', function( $post_types ) {
    $post_types[] = 'my_custom_post_type';
    return $post_types;
} );
```

### `classifai_{feature}_post_statuses`

Filter post statuses supported for a feature.

**Parameters:**
- `array $post_statuses` - Array of post statuses.

### `classifai_{feature}_setting_taxonomies`

Filter taxonomies shown in settings.

**Parameters:**
- `array $taxonomies` - Array of supported taxonomies.
- `object $feature` - Current feature instance.

### `classifai_{feature}_run`

Filter the results of running a feature.

**Parameters:**
- `mixed $result` - Result of running the feature.
- `object $provider_instance` - Provider used.
- `array $args` - Arguments used by the feature.
- `object $feature` - Current feature class.

**Example:**
```php
add_filter( 'classifai_feature_title_generation_run', function( $result, $provider, $args, $feature ) {
    // Modify the generated titles
    if ( is_array( $result ) ) {
        $result = array_map( 'ucwords', $result );
    }
    return $result;
}, 10, 4 );
```

### `classifai_{feature}_debug_information`

Add feature-level debug information.

**Parameters:**
- `array $debug_info` - Debug information array.
- `object $feature` - Current feature class.

---

## Provider Hooks

### `classifai_{provider}_request_args`

Filter the request arguments before making an API call.

**Parameters:**
- `array $args` - Request arguments.
- `string $endpoint` - API endpoint.

### `classifai_{provider}_response`

Filter the API response after receiving it.

**Parameters:**
- `mixed $response` - The API response.
- `string $endpoint` - API endpoint.
- `array $args` - Request arguments.

---

## Cache Hooks

### `classifai_cache_enabled`

Filter whether caching is enabled.

**Parameters:**
- `bool $enabled` - Whether caching is enabled. Default true.

**Example:**
```php
add_filter( 'classifai_cache_enabled', function( $enabled ) {
    // Disable caching in development
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        return false;
    }
    return $enabled;
} );
```

### `classifai_cache_ttl`

Filter the cache TTL (time to live).

**Parameters:**
- `int $ttl` - The cache TTL in seconds.
- `string $feature` - The feature ID.

**Example:**
```php
add_filter( 'classifai_cache_ttl', function( $ttl, $feature ) {
    // Longer cache for image processing
    if ( strpos( $feature, 'image' ) !== false ) {
        return 7200; // 2 hours
    }
    return $ttl;
}, 10, 2 );
```

### `classifai_cache_hit` (Action)

Fires when a cache hit occurs.

**Parameters:**
- `string $feature` - The feature ID.
- `string $hash` - The request hash.

---

## Rate Limiting Hooks

### `classifai_rate_limiting_enabled`

Filter whether rate limiting is enabled.

**Parameters:**
- `bool $enabled` - Whether rate limiting is enabled. Default true.

**Example:**
```php
add_filter( 'classifai_rate_limiting_enabled', function( $enabled ) {
    // Disable for administrators
    if ( current_user_can( 'manage_options' ) ) {
        return false;
    }
    return $enabled;
} );
```

### `classifai_rate_limits`

Filter the rate limits for a feature.

**Parameters:**
- `array $limits` - Rate limit configuration with 'limit' and 'window' keys.
- `string $feature` - The feature ID.

**Example:**
```php
add_filter( 'classifai_rate_limits', function( $limits, $feature ) {
    // Higher limits for premium users
    if ( current_user_can( 'premium_user' ) ) {
        $limits['limit'] = $limits['limit'] * 2;
    }
    return $limits;
}, 10, 2 );
```

---

## Queue Hooks

### `classifai_queue_job_added` (Action)

Fires when a job is added to the processing queue.

**Parameters:**
- `array $job` - The job data.

**Example:**
```php
add_action( 'classifai_queue_job_added', function( $job ) {
    // Log the job
    error_log( 'New ClassifAI job: ' . print_r( $job, true ) );
} );
```

### `classifai_queue_batch_processed` (Action)

Fires when a batch of jobs has been processed.

**Parameters:**
- `array $results` - Processing results with 'processed', 'succeeded', 'failed' counts.

### `classifai_queue_before_job` (Action)

Fires before a job is processed.

**Parameters:**
- `array $job` - The job data.

### `classifai_queue_after_job` (Action)

Fires after a job is processed.

**Parameters:**
- `array $job` - The job data.
- `mixed $result` - The processing result.

---

## Usage Tracking Hooks

### `classifai_usage_logged` (Action)

Fires when an API request is logged for usage tracking.

**Parameters:**
- `array $entry` - The usage entry with timestamp, provider, feature, tokens, etc.

**Example:**
```php
add_action( 'classifai_usage_logged', function( $entry ) {
    // Send to external analytics
    if ( $entry['input_tokens'] > 1000 ) {
        my_analytics_log( 'large_request', $entry );
    }
} );
```

---

## Admin Hooks

### `before_classifai_init` (Action)

Fires before ClassifAI services are loaded.

**Example:**
```php
add_action( 'before_classifai_init', function() {
    // Run code before ClassifAI initializes
} );
```

### `after_classifai_init` (Action)

Fires after ClassifAI services are loaded.

**Example:**
```php
add_action( 'after_classifai_init', function() {
    // Run code after ClassifAI is fully initialized
} );
```

### `classifai_error_messages`

Filter the error message mappings.

**Parameters:**
- `array $error_map` - Error code to message mappings.

**Example:**
```php
add_filter( 'classifai_error_messages', function( $map ) {
    $map['my_custom_error'] = __( 'A custom error occurred.', 'my-plugin' );
    return $map;
} );
```

---

## Feature-Specific Hooks

### Title Generation

- `classifai_feature_title_generation_providers`
- `classifai_feature_title_generation_roles`
- `classifai_feature_title_generation_is_feature_enabled`
- `classifai_feature_title_generation_run`

### Excerpt Generation

- `classifai_feature_excerpt_generation_providers`
- `classifai_feature_excerpt_generation_roles`
- `classifai_feature_excerpt_generation_is_feature_enabled`
- `classifai_feature_excerpt_generation_run`

### Classification

- `classifai_feature_classification_providers`
- `classifai_feature_classification_post_types`
- `classifai_feature_classification_post_statuses`
- `classifai_feature_classification_run`

### Image Processing

- `classifai_feature_descriptive_text_generator_providers`
- `classifai_feature_image_tags_generator_providers`
- `classifai_feature_image_cropping_providers`
- `classifai_feature_image_generation_providers`

### Text to Speech

- `classifai_feature_text_to_speech_providers`
- `classifai_feature_text_to_speech_post_types`

---

## Constants

### `CLASSIFAI_DEBUG`

Enable debug mode for logging and development.

```php
define( 'CLASSIFAI_DEBUG', true );
```

### `CLASSIFAI_ENCRYPTION_KEY`

Custom encryption key for API credentials (recommended for production).

```php
define( 'CLASSIFAI_ENCRYPTION_KEY', 'your-secure-key-here' );
```

---

## Example: Complete Custom Provider

```php
<?php
/**
 * Example: Adding a custom AI provider to ClassifAI
 */

// Step 1: Create the provider class
class MyCustomProvider extends \Classifai\Providers\Provider {
    const ID = 'my_custom_provider';

    public function get_default_provider_settings(): array {
        return [
            'api_key'       => '',
            'authenticated' => false,
        ];
    }

    public function sanitize_settings( array $settings ): array {
        // Sanitize settings
        return $settings;
    }

    public function rest_endpoint_callback( $post_id, $args = [] ) {
        // Implement the API call
        return [ 'Generated content' ];
    }
}

// Step 2: Register the provider
add_filter( 'classifai_feature_title_generation_providers', function( $providers ) {
    $providers['my_custom_provider'] = __( 'My Custom Provider', 'my-plugin' );
    return $providers;
} );

// Step 3: Initialize the provider
add_action( 'after_classifai_init', function() {
    // Register any additional hooks needed
} );
```

---

## Best Practices

1. **Use specific hooks** - Prefer feature-specific hooks over generic ones when possible.
2. **Return early** - Check conditions and return early to avoid unnecessary processing.
3. **Sanitize data** - Always sanitize data when modifying settings or outputs.
4. **Check capabilities** - Verify user capabilities when modifying access controls.
5. **Document changes** - Comment your filter modifications for future maintainability.

---

*Last updated: 2025-12-17*
