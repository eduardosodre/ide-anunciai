# Design System Specification: The Sanctuary Ethos

This document outlines the visual and structural language for a high-end Christian networking and church locator platform. As designers, your goal is to move beyond the "generic template" look. We are building a digital environment that feels like an architectural space—quiet, intentional, and light-filled.

## 1. Creative North Star: "The Digital Cathedral"
The design system is built on the concept of **The Digital Cathedral**. Much like modern sacred architecture, we use light, scale, and material depth to create a sense of peace and reverence. We avoid "clutter" at all costs. Instead of boxes and borders, we use expansive white space and tonal layering to guide the user’s spirit. 

**Signature Style:**
- **Intentional Asymmetry:** Break the grid. Place a large `display-lg` headline off-center against a high-contrast `primary` background to create an editorial, premium feel.
- **Overlapping Elements:** Allow cards to slightly overlap hero sections to create a sense of physical connection and community "interweaving."

## 2. Color & Surface Philosophy
The palette balances the "Trust" of deep teals with the "Spirit" of warm golds. 

### The "No-Line" Rule
**Explicit Instruction:** Do not use 1px solid borders to section off content. Traditional dividers feel restrictive. Instead, define boundaries through background shifts.
- A card (`surface-container-lowest`) should sit on a background of `surface-container-low`.
- Sections are divided by a change from `surface` to `surface-container`.

### Surface Hierarchy & Nesting
Treat the UI as a series of stacked, semi-transparent materials.
- **Base Layer:** `surface` (#f9f9fa)
- **Content Blocks:** `surface-container-low` (#f3f3f4)
- **Interactive Cards:** `surface-container-lowest` (#ffffff)
- **Elevated Modals:** Use `surface-bright` with a 15% opacity `surface-tint` overlay.

### The Glass & Gradient Rule
To add "soul," use subtle linear gradients (135°) transitioning from `primary` (#00425e) to `primary_container` (#005b7f) for primary CTAs and hero backgrounds. This creates a sense of depth that flat color cannot replicate.

## 3. Typography: Editorial Authority
We use a dual-typeface system to balance professional modernism with welcoming warmth.

*   **Headlines (Manrope):** Use Manrope for all `display` and `headline` roles. Its geometric yet soft curves feel modern and sophisticated. 
    *   *Directorial Note:* Use `display-lg` with tight letter-spacing (-0.02em) for high-impact editorial moments.
*   **Body & Labels (Inter):** Use Inter for all `title`, `body`, and `label` roles. Inter provides world-class readability for long-form teaching content and directory listings.

**Hierarchy Roles:**
- **Display (L/M/S):** For "Hero" moments and spiritual callouts.
- **Headline (L/M/S):** For page titles and major section headers.
- **Title (L/M/S):** For card titles and navigation.
- **Body (L/M/S):** For church descriptions and community posts.
- **Label (M/S):** For metadata (e.g., "Service Times," "Distance").

## 4. Elevation & Depth
We eschew "Material" standard shadows in favor of **Tonal Layering**.

*   **The Layering Principle:** Place a `surface-container-lowest` (#ffffff) element on top of a `surface-container-low` (#f3f3f4) background. This creates a "soft lift" without a single drop shadow.
*   **Ambient Shadows:** For floating action buttons or menus, use an extra-diffused shadow: `box-shadow: 0 12px 32px -4px rgba(0, 30, 45, 0.08);`. Note the blue tint in the shadow color (derived from `on_primary_fixed_variant`).
*   **The "Ghost Border" Fallback:** If a container absolutely requires a boundary (e.g., in high-density search results), use `outline_variant` at 20% opacity. 
*   **Glassmorphism:** For top navigation bars, use `surface` at 80% opacity with a `backdrop-filter: blur(12px)`. This allows the "warmth" of background images to bleed through.

## 5. Components

### Cards & Lists (The Directory)
*   **Forbid Dividers:** Never use a horizontal line to separate list items. Use 24px of vertical padding and a subtle `surface-container` background on hover.
*   **The "Locator" Card:** Use `md` (0.75rem) rounded corners. Feature the church name in `title-lg` and use a `tertiary` (#613100) icon for the location pin to add warmth.

### Buttons
*   **Primary:** Gradient of `primary` to `primary_container`. `xl` (1.5rem) roundedness for a friendly, pill-shaped feel.
*   **Secondary:** Ghost style. No background, `outline` color for text, and a `Ghost Border` (outline-variant at 20%).
*   **Action Chips:** Use `secondary_container` backgrounds with `on_secondary_container` text for tags like "Worship," "Youth," or "Small Groups."

### Input Fields
*   **Styling:** Use `surface_container_highest` for the input track. No border. When focused, transition the background to `surface_container_lowest` and add a 2px `surface_tint` bottom-only highlight.

### Signature Component: The "Reflection" Card
A specialized card for daily verses or music quotes. Use a `tertiary_container` background with `on_tertiary_container` (warm gold tones) text, using `headline-sm` for the quote.

## 6. Do’s and Don’ts

**Do:**
- **Do** use generous whitespace (32px-64px) between sections to let the content breathe.
- **Do** use the `tertiary` (gold/orange) palette sparingly—only for moments of "energy" like notifications, "New" badges, or highlight icons.
- **Do** use high-quality, authentic photography of real people and architectural light. Avoid "stock" church photos.

**Don't:**
- **Don't** use 100% black text. Always use `on_surface` (#1a1c1d) to keep the contrast soft and readable.
- **Don't** use sharp 0px corners. This platform must feel "welcoming"; use the `DEFAULT` (0.5rem) or `md` (0.75rem) radius for all containers.
- **Don't** clutter the navigation. Use a "Mega Menu" style with tonal layering to organize music, teaching, and locator features.