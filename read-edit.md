# Read-Edit Log for AI Hairstyle Try-On Plugin

## Original Specifications (Super Prompt)
You are tasked with building a complete, functional WordPress plugin based on the exact specifications below. This plugin allows salon visitors to upload a photo (or up to 4 for angles: front/back/left/right), select a hairstyle and color, and see AI-generated previews (4 angles) of themselves with the new style applied naturally. It integrates Google Gemini API for generations, Elementor forms via webhooks for lead capture/bookings, and includes backend management. The goal is for salons to use this to let customers preview styles, collect data, and boost bookings.
Build this in VS Code. Use PHP 8.0+ compatible code, follow WordPress coding standards (e.g., no direct file writes, use transients for temp data), ensure multisite compatibility, and make it secure/GDPR-friendly (e.g., no permanent storage of user photos/emails; delete temps immediately after use/emailing; assume site-wide GDPR notice). No multi-language support needed. Target latest WordPress (6.0+). Organize code cleanly for scalability (e.g., separate files for API, frontend JS, prompts). Include an assets folder in the plugin root with initial reference images (men/women subfolders > style folders like 'bob' > 4 PNGs each: front.png, back.png, left.png, right.png—use placeholders if needed). Hard-code nothing except defaults; make configurable.
**Key Constraints:**
- All image modifications via remote Google Gemini API (image-to-image mode). Send user upload(s) + reference images + prompt; receive generated images.
- No local image processing beyond temp storage.
- Use webhooks for Elementor form integration (plugin provides endpoint; forms post to it).
- Track free generations via local storage (persistent across refreshes).
- Error handling: Frontend shows user-friendly messages (e.g., "The AI service is temporarily unavailable—try again or check back soon."); admin emails for persistent issues (e.g., invalid API key).
- Rate limiting: Retry failed API calls up to 3x; notify admin if quota exceeded.
- Emails: Use wp_mail() (integrates with WP SMTP); attach original + generated images (as attachments if easier, high quality).
**Frontend Features (Shortcode-Embeddable on Any Page):**
- Embed via shortcode [ai-hairstyle-tryon] for placement on pages (e.g., via Elementor).
- If both genders selected in backend: Inline tabs (row) for "Male" / "Female" to filter styles.
- If one gender: No tabs, just show that list.
- Hairstyle list: Column of selectable items (names from backend; filter by gender). Customizable via CSS (add classes/IDs; include a backend CSS snippet field with pre-listed IDs/explanations for easy tweaks without hunting).
- Upload field: Like the screenshot (button: "Upload Your Photo Now" or drop file; supports images/PNG/JPG). Allow up to 4 uploads (labeled front/back/left/right); fallback to single (AI approximates other angles).
- Color picker: Available from start (e.g., color wheel/swatches/hex input). Triggers new API generation on change (accept 5-20s delay; show loading spinner). Applies to the selected hairstyle image(s) naturally via AI.
- Generation: On hairstyle select/color change, call Gemini API. Generate 4 separate images (front primary, back, left, right—display as gallery). Prompt ensures: Only change hairstyle/color; fit naturally to head shape; slight tweaks for better look; blur background 15-20%; improve lighting subtly; keep authentic/realistic (no animation).
- Free limit: Customizable (default 2) generations per visitor (track via local storage to prevent refresh exploits). After limit, trigger popup (via backend-set ID) with form for name/email.
- Book Now button: Per generated set; triggers popup/form (backend ID). Form includes optional stylist selection (dropdown/cards with photos/names from backend).
- Download button: Save generated images locally (no server storage).
- Reset button: Clear uploads/start over.
- Watermark: Light salon logo (configurable/uploadable in backend; e.g., bottom corner, semi-transparent).
- Styling: Allow CSS overrides via backend snippet or Elementor/code snippets.
**Backend Features (Accessible via WP Dashboard Menu):**
- Plugin menu: "AI Hairstyle Try-On" on left sidebar.
- Tabs (like screenshot: Field Groups/Post Types style):
- **Configuration**: Radio for gender (male/female/both); primary email field (overrides WP admin email; fallback to WP SMTP if blank); Gemini API key (password field, secure storage); free generation limit (number field, default 2); pop-up/form IDs (exploration popup/form, book now popup/form); webhook setup (auto-generate URL; fields to map Elementor form IDs, e.g., stylist field name); custom CSS snippet with ID list/explanations.
- **Hairstyles**: CPT-like management (new submenu: "Hairstyles"; each as individual post with edit screen). Pre-load from assets folder. Fields/metaboxes: Name, gender (male/female), alternative names (comma-separated for SEO), enable/disable checkbox, bulk upload references (4 images: front/back/left/right via WP Media or direct; AI auto-detects angles). Add/edit/delete; search/filter; bulk actions.
- **Staff**: Submenu for adding stylists (name, photo upload via WP Media, email). Displays as selectable in book now form.
- **Analytics**: Dashboard page with totals (generations, bookings, popular hairstyles, API calls for cost tracking). No personal data stored. Exportable as CSV.\
- **Styling**: this is where a text area is with basic styling (in place as a placeholder) with ALL the selectors and basic styling so developers can go in and just make adjustments instead of rewriting entirely.
**API Integration (Dedicated Prompt File):**
- Use Google Gemini API (gemini-1.5-flash or similar for image-gen).
- Prompt template (in separate file, e.g., prompts.php): "Apply [hairstyle name] from these references [include 4 ref images] to the user's photo(s) [include uploads]. Change only the hair; make natural fit to head; slight improvements; blur BG 15-20%; better lighting; realistic. For color: [selected color]. Generate 4 angles: front, back, left, right."
- If <4 user uploads: Approximate missing angles.
- Handle costs/privacy: Inform in code comments (images sent to Google; user consent via site policy).
**Form/Webhook Handling:**
- Exploration form (after free limit): Webhook intercepts submission; sets local storage flag for unlimited generations; allows Elementor actions (e.g., marketing lists).
- Book Now form: Webhook intercepts; attaches original + generated images; emails to stylist (if selected & email set) as primary, salon as CC/BCC; reply-to = salon primary (for professionalism, even if stylist uses Gmail). If no stylist email: To salon only, note stylist name in body. Allow full Elementor features (calendar, payments).
- Map fields via backend (e.g., specify Elementor field IDs for stylist, etc.).
**Development Guidelines:**
- VS Code organization: Root > assets (men/women/styles/images); inc/ (API, prompts, webhooks); js/ (frontend scripts); css/ (styles).
- Testing: Provide installable ZIP. Test with dummy API key, Elementor forms, uploads.
- Deliver perfect first revision: Follow specs exactly—no additions/changes without asking. Comment code heavily. Ensure no bugs (e.g., delete temps post-email).
Budget/Timeline: [Your details here]. Provide Git repo for review. Questions? Ask before coding.

## Update Log
- No updates yet. This is the initial version.