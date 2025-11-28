# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What is ClassifAI?

ClassifAI is a WordPress plugin that integrates Artificial Intelligence and Machine Learning services (OpenAI, Microsoft Azure AI, Google Gemini, IBM Watson, etc.) into WordPress. It provides AI-powered features like content generation, image processing, text-to-speech, classification, and more.

## Development Setup

### Initial Setup

```bash
# Install dependencies and build
composer install && npm install && npm run build
```

### Local Development Environment

The plugin uses `@wordpress/env` for local development:

```bash
# Start local WordPress environment
npm run env:start

# Stop environment
npm run env:stop

# Destroy environment
npm run env:destroy
```

The environment is configured in `.wp-env.json` and includes test plugins like Classic Editor, ElasticPress, and WooCommerce.

## Common Commands

### Building Assets

```bash
# Development build with watch mode
npm start

# Production build
npm run build

# Build documentation site
npm run build:docs
```

### Code Quality

```bash
# PHP linting (PHPCS)
composer run lint

# Fix PHP code style issues
composer run lint-fix

# PHPStan static analysis
composer run phpstan

# JavaScript linting
npm run lint:js

# Fix JavaScript issues
npm run lint:js-fix
```

### Testing

```bash
# Run PHPUnit tests
npm test
# or
./vendor/bin/phpunit

# Run a single test file
./vendor/bin/phpunit tests/Classifai/path/to/TestFile.php

# Cypress E2E tests
npm run cypress:open    # Interactive mode
npm run cypress:run     # Headless mode
```

### WP-CLI Commands

ClassifAI provides WP-CLI commands for batch processing:

```bash
# Batch classify posts
wp classifai post <post_ids> [--post_type=<post_type>] [--limit=<limit>]

# Classify text
wp classifai text <text> [--category=<bool>] [--keyword=<bool>]

# Batch process images
wp classifai image <attachment_ids> [--limit=<limit>] [--field=<field>]

# Generate embeddings
wp classifai embeddings [--post_type=<post_type>] [--limit=<limit>]
```

See the [WP-CLI documentation](https://10up.github.io/classifai/advanced-docs/wp-cli) for full details.

## Code Architecture

### Service → Feature → Provider Pattern

ClassifAI uses a hierarchical architecture:

1. **Services** (`includes/Classifai/Services/`): High-level categories of AI functionality
   - `LanguageProcessing`: Text-based AI features
   - `ImageProcessing`: Image-based AI features
   - `ContentRecommendation`: Recommendation features

2. **Features** (`includes/Classifai/Features/`): Specific AI capabilities
   - `ExcerptGeneration`, `TitleGeneration`, `Classification`
   - `DescriptiveTextGenerator`, `ImageGeneration`
   - `TextToSpeech`, `AudioTranscriptsGeneration`
   - Each feature extends the abstract `Feature` class

3. **Providers** (`includes/Classifai/Providers/`): AI service implementations
   - `OpenAI/`, `Azure/`, `GoogleAI/`, `Watson/`, `AWS/`
   - Each provider implements specific APIs for features
   - A single feature can support multiple providers

**Example flow**: The `ExcerptGeneration` feature (under `LanguageProcessing` service) can use the `OpenAI`, `Azure`, or `GoogleAI` providers.

### PHP Namespace Structure

- **Namespace**: `Classifai\`
- **PSR-4 Autoloading**: Maps to `includes/Classifai/` directory
- **Helper Functions**: Loaded via `includes/Classifai/Helpers.php`

### JavaScript/React Structure

- **Source**: `src/js/` directory
  - `admin.js`: Main admin interface
  - `features/`: Feature-specific JS (organized by feature name)
  - `settings/`: Settings page React components
  - `components/`: Reusable React components

- **Build Output**: Built assets go to `dist/` (not tracked in git)

### Key PHP Classes

- `Plugin.php`: Main plugin bootstrap class
- `ServicesManager.php`: Manages all services and their registration
- `Admin/Settings.php`: Admin settings interface
- `Command/ClassifaiCommand.php`: WP-CLI command definitions

## Directory Structure

```
classifai/
├── includes/Classifai/     # PHP source (PSR-4: Classifai\)
│   ├── Services/           # Service layer
│   ├── Features/           # Feature implementations
│   ├── Providers/          # AI provider integrations
│   ├── Admin/              # WordPress admin interfaces
│   ├── Command/            # WP-CLI commands
│   ├── Blocks/             # Gutenberg blocks
│   └── Helpers/            # Utility functions
├── src/                    # Frontend source files
│   ├── js/                 # JavaScript/React source
│   └── scss/               # Styles
├── tests/                  # Test files
│   ├── Classifai/          # PHPUnit tests (mirrors includes structure)
│   └── cypress/            # E2E tests
├── dist/                   # Built assets (generated, not in git)
└── vendor/                 # Composer dependencies
```

## Branch Workflow

- **`develop`**: Active development branch (all PRs go here)
- **`trunk`**: Stable development version (merged from develop before release)
- **`stable`**: Current production release (auto-generated from trunk)

Always branch from and PR against `develop`.

## WordPress Integration Points

### Hooks & Filters

ClassifAI provides extensive WordPress hooks for customization. The plugin uses a consistent naming pattern:

- `classifai_{service}_{feature}_{action}`
- Example: `classifai_before_generate_excerpt`

### Custom Taxonomies

Features can automatically create and manage WordPress taxonomies for AI-generated classifications (categories, keywords, entities, concepts).

### Admin Integration

- Settings UI: Uses React for the modern settings interface
- Block Editor: Registers custom Gutenberg blocks and sidebar panels
- Classic Editor: Provides meta boxes where appropriate

## API Rate Limiting & Error Handling

When working with AI provider APIs:

- Most providers have rate limits; the plugin includes retry logic in provider classes
- Errors are returned as `WP_Error` objects
- Check `$feature->can_register()` to see if a feature has valid credentials
- Background processing uses Action Scheduler (WooCommerce Action Scheduler) for async tasks

## Testing AI Features

Since AI features require API credentials, tests often use:

- **Mocking**: Mock API responses for unit tests
- **Environment Variables**: Configure test credentials via `.wp-env.override.json` (not in git)
- **Feature Flags**: Check `$feature->is_feature_enabled()` before running

## Documentation

- **End-user docs**: [10up.github.io/classifai](https://10up.github.io/classifai/)
- **Hook documentation**: Auto-generated from docblocks using `wp-hooks-documentor`
- Build docs with: `npm run build:docs`
- Docs source: `wp-hooks-docs/` directory

## Requirements

- **PHP**: 7.4+
- **WordPress**: 6.7+
- **Composer**: For PHP dependencies
- **Node.js**: Version specified in `.nvmrc` (use `nvm use`)
