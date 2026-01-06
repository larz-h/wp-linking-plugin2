# Internal Linking Manager for WordPress

A comprehensive link management plugin for WordPress that helps you build natural, varied internal links while tracking anchor text usage to avoid over-optimization.

## Overview

This is **not** a simple auto-linker. It's a sophisticated link management tool that:

- Suggests internal link opportunities based on your defined target pages
- Tracks anchor text usage to prevent over-optimization
- Provides detailed statistics and health monitoring
- Gives you complete control over which links to insert

**Key Principle:** The plugin suggests links, you decide what to insert.

## Features (Phase 1)

### Target Pages Manager
- Define pages you want to link to with URL, primary anchor, and variations
- Set priority levels for conflict resolution
- Track usage statistics per target
- Categorize links (internal/affiliate/external)

### Smart Content Scanning
- Finds all occurrences of your anchor phrases
- Respects word boundaries (no partial matches)
- Skips existing links, headings, and code blocks
- Case-sensitive or case-insensitive matching

### Gutenberg Editor Integration
- Sidebar panel shows all link opportunities in current post
- View context around each match
- One-click link insertion
- Real-time usage statistics
- Warnings for over-optimized anchors

### Link Tracking & Analytics
- Logs every inserted link
- Tracks which anchor text was used
- Monitors anchor text distribution
- Identifies over-used primary anchors

### Link Density Controls
- Set max links per post
- Limit links to same URL per post
- Configurable thresholds

### Dashboard
- Link health overview
- Over-optimized anchor warnings
- Under-linked target pages
- Recent linking activity
- Orphan posts (no internal links)

## Installation

1. Upload the `internal-linking-manager` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Link Manager** in the admin menu to start adding target pages

## Usage

### 1. Add Target Pages

Navigate to **Link Manager > Target Pages** and add your first target:

- **Target URL**: `/blog/example-post` or full URL
- **Primary Anchor**: `best running shoes`
- **Anchor Variations**: One per line
  ```
  top running shoes
  running shoes for men
  best shoes for running
  ```
- **Priority**: 1-10 (higher wins in conflicts)
- **Link Type**: Internal, Affiliate, or External

### 2. Edit a Post

When editing a post in the Gutenberg editor:

1. Open the **Link Manager** sidebar (click the link icon in the top-right)
2. The plugin will scan your content and show opportunities
3. Review each suggestion with context
4. Click **Insert Link** to add the link
5. The link appears immediately in your editor

### 3. Monitor Link Health

Visit **Link Manager > Dashboard** to see:

- Total active links across your site
- Targets with over-used primary anchors
- Under-linked target pages
- Posts with no internal links (orphans)
- Recent linking activity

### 4. Configure Settings

Go to **Link Manager > Settings** to adjust:

- Max links per post (default: 10)
- Max links to same URL per post (default: 1)
- Case sensitivity for matching
- Primary anchor warning threshold (default: 30%)

## How Matching Works

### Word Boundaries
- ✅ "running shoes" matches "best running shoes for men"
- ❌ "running shoes" does NOT match "running shoestore"

### Case Sensitivity
- Default: case-insensitive
- "Running Shoes" matches "running shoes"
- Enable case sensitivity in settings if needed

### Explicit Variations Only
- No automatic plural/singular matching
- Only matches phrases you define
- Add all variations manually for complete coverage

### Skipped Content
The plugin will NOT link text in:
- Existing links
- Headings (H1-H6)
- Code blocks
- Script/style tags

## Database Structure

The plugin creates three custom tables:

- `wp_ilm_targets`: Target pages with anchors and variations
- `wp_ilm_logs`: Link insertion history
- `wp_ilm_settings`: Plugin configuration

Data is preserved on deactivation but removed on uninstall.

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- Gutenberg editor (Classic Editor support planned for Phase 2)

## Roadmap

### Phase 2 - Topic Clusters
- Create topic clusters for related content
- Assign posts and targets to clusters
- Prefer intra-cluster linking
- Pillar post priority
- Cluster health dashboard

### Phase 3 - Advanced Features
- Bulk content scanning
- Link suggestion review queue
- Advanced reporting
- CSV import/export for targets
- Classic Editor support via meta box

## Development

### File Structure
```
internal-linking-manager/
├── internal-linking-manager.php (main plugin file)
├── includes/
│   ├── class-database.php
│   ├── class-scanner.php
│   ├── class-link-inserter.php
│   ├── class-stats.php
│   └── class-rest-api.php
├── admin/
│   ├── class-target-pages.php
│   ├── class-settings.php
│   └── class-dashboard.php
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── editor.css
│   └── js/
│       ├── admin.js
│       └── editor-sidebar.js
└── README.md
```

### Key Classes

- **ILM_Database**: Database operations and schema
- **ILM_Scanner**: Content scanning and phrase matching
- **ILM_Link_Inserter**: Link insertion into Gutenberg blocks
- **ILM_Stats**: Usage statistics and analytics
- **ILM_REST_API**: REST endpoints for editor integration

## Support

For issues, feature requests, or questions:
- Check the documentation above
- Review the Dashboard for link health insights
- Check Settings to adjust thresholds

## License

GPL v2 or later

## Credits

Built for personal use with a focus on functionality over polish.
