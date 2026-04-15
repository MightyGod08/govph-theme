# Calendar Shortcode Improvement TODO

## Approved Plan Steps

### 1. Create TODO.md [✅ Completed]

### 2. Edit functions.php
- Clean whitespace/BR before table output
- Add `class="pad"` to all empty padding `<td>`
- Add `<tfoot>` with prev/next month links
- Optimize inline styles (remove duplicates with theme.json)
- Ensure table matches 40px cells

### 3. Edit theme.json
- Update `.pad` CSS: `visibility: hidden; background: transparent;`

### 4. Edit js/calendar.js
- Fix selectors to `.my-custom-styled-calendar`
- Add URL param handling for dynamic months
- Handle nav clicks

### 5. Test
- Insert [wp_calendar]
- Verify blank padding, no BR, nav works
- Check holidays/today highlighting

### 6. attempt_completion

**Progress: Getting started...**

