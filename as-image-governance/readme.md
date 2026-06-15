# Image Governance

Author: AlphaSys  
Version: 0.1.0  
Status: MVP  

## Purpose

Image Governance helps WordPress site owners record where images came from, what authority exists to use them, where they are used, and how they should be attributed.

## Key Features

- Governance fields on image attachments: Source, Authority Level, Authority Notes, and Attribution.
- Media Library columns and filters for governance review.
- Flat image collections using the `ig_collection` attachment taxonomy.
- Bulk authority and collection assignment in the Media Library.
- Manual usage scanner for featured images, inline images, galleries, pages, posts, and public custom post types.
- Settings page for attribution display and scanner behavior.
- Frontend attribution page output for a referring page.
- Optional footer attribution link when the current page has attributed images.

## Folder Structure

```text
as-image-governance/
├── as-image-governance.php
├── readme.md
├── uninstall.php
├── functions/
│   ├── setup.php
│   ├── admin.php
│   ├── assets.php
│   ├── rest.php
│   └── helpers.php
├── scripts/
│   └── as-image-governance.js
├── styles/
│   └── as-image-governance.css
└── templates/
    └── .gitkeep
```

## Important Notes

- The plugin never deletes media files or changes image file paths.
- Governance metadata is retained on deactivation.
- Uninstall cleanup only runs when the explicit cleanup setting is enabled.
- The usage scanner is manual and stores results in the `asig_usage_index` option.
- The attribution page must be selected under Settings > Image Governance.

## Future Considerations

- More robust block parsing for advanced gallery/image blocks.
- Background scanning for very large sites.
- Export tools for governance reports.
- More detailed collection management screens.
