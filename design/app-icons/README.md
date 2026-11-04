# ShAdmin icon v1

Generated with the built-in ImageGen tool on 2026-10-20 using the SHPOS and SHPOS Administrator icons as style references.

- `shadmin-v1.png`: original transparent PNG.
- `shadmin-v1.ico`: Windows ICO with 16, 24, 32, 48, 64, 128 and 256 px PNG frames.
- `../../public/images/shadmin-icon.png`: 512 px application image.
- `../../public/favicon.png`: 32 px browser icon.
- `../../public/favicon.ico`: multi-resolution browser fallback.
- `../../public/favicon.svg`: standalone SVG wrapper embedding the 256 px PNG; replaces the old Laravel SVG for direct requests.
- `../../public/apple-touch-icon.png`: 180 px Apple touch icon.

Public assets are exported with System.Drawing. The shared `partials.app-icons` view provides versioned URLs to refresh cached browser icons. The `app-logo-icon` component now uses ShAdmin's image. Existing Farmacias Shadai branding is retained.

## Generation prompt

Use case: logo-brand. Create ONE finished application icon for ShAdmin, the central business management web application in the same family as the two reference icons for SHPOS (blue cashier) and SHPOS Administrator (violet). The references are style references only: match their rounded-square tile shape, proportions, white sculpted foreground, subtle Fluent 3D material, simple bold forms and polished Windows 11 visual language. For ShAdmin use a deep emerald-to-teal tile with a restrained luminous mint upper-right highlight. Central symbol: a bold white rounded dashboard window with a dark emerald inset, a simple white short header line and four large evenly spaced mint-white rounded square modules in a 2x2 grid, representing centralized management. The upper right module can have three short emerald chart bars carved into it, keep extremely simple. Strong clear silhouette recognizable at favicon size, generous safe padding, centered front view with very slight dimensional depth, tile fills about 85% of square canvas. Genuine transparent background outside tile. No letters, no text, no gear, no cash register, no receipt, no Laravel symbol, no watermark, no surrounding props, no presentation sheet, only one icon.
