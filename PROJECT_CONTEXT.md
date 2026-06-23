# PROJECT CONTEXT — VastuKundali Platform

> Handoff/context document for AI assistants and developers. Describes the
> project, architecture, current state, and pending deployment actions.

## 1. What this is
A web platform where users upload a building floor plan, pay ₹99, and receive an
AI-generated **Vastu Shastra report** (directional energy analysis with scores,
heatmap, remedies, product recommendations, and a Vastu Chakra overlay on their
plan). Brand: "VastuKundali" — positioned as a full-service
Vastu consultancy, not just a report website.

## 2. Tech stack
- **Backend:** Plain PHP (no framework, no Composer) — runs on shared hosting
  (Hostinger). Image processing via **PHP GD** library.
- **Frontend:** Vanilla HTML/CSS/JS (no framework). Pages in `frontend/`.
- **Database:** MySQL/MariaDB.
- **Payments:** Razorpay.
- **OTP:** Twilio WhatsApp API.
- **AI (optional):** Claude via AWS Bedrock or Anthropic API. If not configured,
  a built-in rule-based engine produces reports.
- **Repo:** `imsharmawpp/imsharma` on GitHub. Active branch: **`main`**
  (PR #1 `feat/vastu-ai-platform` was merged into main).

## 3. Directory structure (key files)
```
backend/
  config/config.php          # All credentials: DB, Razorpay, Bedrock/Claude, Twilio, SMTP
  config/database.php        # MySQL PDO connection (Database class)
  includes/helpers.php       # jsonResponse, getSetting, logDebug, formatDirection, etc.
  api/
    validate_plan.php        # Upload-time strict plan validation + category screening
    upload.php               # Saves plan + creates report record + captures lead
    payment_create_order.php # Razorpay order
    payment_verify.php       # Razorpay verification
    generate_report.php      # Builds the report (geometry + AI/engine + overlay + PDF)
    get_report.php           # Returns report JSON to frontend (with access control)
    send_otp.php / verify_otp.php
  lib/
    VastuImageAnalyzer.php   # CORE: Brahmasthan detection, entry, zone mapping, overlay render
    PlanClassifier.php       # Category screening (house vs office vs factory) via Claude Vision
    VastuEngine.php          # Rule-based analysis (residential + commercial) — fallback when no AI
    ClaudeAI.php             # Claude/Bedrock vision integration (analysis + classifyImage)
    ChakraOverlay.php        # Simple overlay fallback
    PDFReport.php            # HTML/PDF report generation
    Twilio.php               # WhatsApp OTP
  uploads/chakra-overlay.png # The Vastu Chakra image (North always on top)
  reports/overlays/          # Generated overlay images
  tests/                     # Standalone GD/engine test harnesses (no DB needed)
database/
  schema.sql, migration_v2.sql, migration_v3.sql
frontend/
  pages/upload.html          # The wizard (questionnaire -> upload+direction -> payment -> report)
  pages/report.html          # The report view
  js/upload.js, js/report.js
  css/style.css, css/animations.css
```

## 4. User flow (4 steps, in upload.html)
1. **Questionnaire** — Commercial/Residential toggle -> sub-type -> Size (optional)
   -> Problem Areas (LinkedIn-style pills, max 2) -> Name -> Mobile + WhatsApp OTP
   -> Email (optional). Lead captured here.
   - Commercial sub-types: land, office_space, retail_showroom, factory, warehouse
   - Residential sub-types: row_house_kothi, builder_floor_apartment, villa
   - Problem areas: wealth, health, relationship_family, career, mental_stress,
     education, other (30-char free text)
2. **Upload + Direction (split screen)** — left: drag/drop upload with live
   validation; right: compass direction selector. Cannot proceed unless the plan
   passes validation AND a direction is chosen.
3. **Payment** — Razorpay (Rs 99).
4. **Report generation** — redirects to `report.html?id=N`.

## 5. The CORE: image analysis engine (`VastuImageAnalyzer.php`)
This is what the whole system depends on. Pure GD, tested in sandbox:
- **Bounding box detection** — finds the actual drawing (ignores white margins).
- **Brahmasthan** = geometric centre of the content bounding box (the sacred
  central point where directional energies converge).
- **Entry point** — derived from the user-selected facing direction (we trust the
  explicit input; automatic door-arc detection from pixels alone is unreliable
  without vision AI).
- **`pixelToZone()`** — maps any pixel to a true compass zone (N/NE/E/.../C),
  **rotated by the facing direction**, so a plan drawn in ANY orientation is
  analyzed correctly. (Verified: East-facing top-left -> NE, North -> NW,
  South -> SE, West -> SW.)
- **`renderOverlay()`** — composites: floor plan + rotated Vastu Chakra
  **centred on the Brahmasthan** + diagonal energy lines + Brahmasthan marker
  (red dot) + entry marker (green dot) + facing banner.
  Chakra rotation formula: `(360 - facingDegrees) % 360`.
  Facing degrees clockwise from North: N=0, NE=45, E=90, SE=135, S=180, SW=225,
  W=270, NW=315.

## 6. Validation & anti-spam (`validate_plan.php`)
Runs at upload. Analyzes the **content bounding box** (robust to margins). REJECTS:
- No file -> `NOT_UPLOADED`
- Non-image / too small (<3KB) / <300x300px -> `NOT_CLEAR`
- **Photos / colourful images** (high saturation, no white background, soft edges
  instead of sharp wall lines) -> `NOT_RECOGNIZED` (e.g. a honey-box photo is
  rejected — verified in tests)
- Blank images, hand-drawn plans
Then runs **`PlanClassifier`** for category screening.

## 7. Category screening (`PlanClassifier.php`)
Verifies the uploaded plan matches the selected category (e.g. rejects a house
plan uploaded as a factory).
- **Requires Claude Vision** to be reliable. Hard-rejects residential-vs-commercial
  mismatches and office-vs-factory style mismatches; rejects non-floor-plan images.
- If AI is NOT configured -> returns `needs_manual_review` and does NOT block
  (never silently wrong, but also won't auto-reject the building type).

## 8. Report generation (`generate_report.php`)
1. Pre-computes geometry (Brahmasthan + entry) via VastuImageAnalyzer.
2. Tries Claude AI (vision, category-aware prompt). Falls back to **VastuEngine**
   (rule-based) if AI unavailable or fails.
   - **VastuEngine** branches on property type:
     - residential -> rooms (kitchen/bedroom/pooja/toilet/living/staircase...)
     - commercial -> owner/director cabin (SW), staff/workstations (E/N),
       reception (NE), accounts/cash (N/SE), machinery (SE), heavy storage (SW),
       loading bay (NW), billing counter, display area, etc. with
       commercial-specific remedies.
3. Renders the chakra overlay (centred on Brahmasthan), stores overlay_url.
4. Generates a PDF (includes overlay + highlighted "Recommendation" section that
   promotes offline consultation / positions VastuKundali as a full consultancy).
5. Emails the report (best-effort).

## 9. Report view (`report.html` / `report.js`)
- Shows: overall score, **Floor Plan with Vastu Chakra (left) SIDE-BY-SIDE with
  Energy Heatmap (right)**, Key Findings (positives/negatives), room/zone analysis,
  remedies, life/business impact, recommended products, Final Verdict, and a
  highlighted **Recommendation** section (offline consultation CTA).
- Wording is property-type aware: residential -> "home", commercial ->
  "property" / "commercial space".
- `resolveAssetUrl()` rewrites domain-absolute `/backend/...` paths to
  `../../backend/...` so assets load when deployed in a subdirectory.
- If the server overlay 404s, it auto-regenerates the overlay client-side (Canvas).

## 10. Database
- `reports` table holds plan + report data. **`migration_v3.sql`** adds:
  `property_category`, `property_subtype`, `problem_areas`, `other_problem_text`,
  `overlay_path`, `overlay_url`. Code degrades gracefully if not run, but
  category-aware analysis needs these columns to carry data.
- Other tables: users, products, orders, payments, coupons, leads, settings,
  blog_posts, otp_codes, addresses.

## 11. Configuration required (in `backend/config/config.php` or DB `settings`)
- **DB:** DB_HOST / DB_NAME / DB_USER / DB_PASS (Hostinger uses prefixed names,
  e.g. `u770423744_vastu`).
- **Razorpay:** RAZORPAY_KEY_ID / RAZORPAY_KEY_SECRET.
- **Twilio (WhatsApp OTP):** TWILIO_SID, TWILIO_TOKEN, TWILIO_WHATSAPP_FROM,
  TWILIO_CONTENT_SID. Note: Twilio sandbox numbers require the recipient to send
  the "join <code>" message first.
- **AI (optional, needed for category screening + best reports):**
  BEDROCK_API_KEY (ABSK...) or CLAUDE_API_KEY (sk-ant-...) + AWS_REGION + model.
  Without it, the rule-based engine + structural validation still work.

## 12. KNOWN PENDING ITEMS / DEPLOYMENT NOTES (IMPORTANT)
- **Deployment is in a subdirectory** (e.g. `aheadads.co.in/vastu/`). Asset paths
  are stored domain-absolute (`/backend/...`) which 404 in a subdirectory. Fixed
  in `report.js` via `resolveAssetUrl()`. If new absolute-path bugs appear
  elsewhere, apply the same pattern.
- **Run `database/migration_v3.sql`** on the live DB.
- **Set the Claude/Bedrock key** to enable category rejection (house-vs-factory)
  and AI-quality reports. Without it: photo rejection + rule-based reports work;
  category mismatch falls back to manual review.
- **Chakra image** must be deployed at `backend/uploads/chakra-overlay.png`
  (committed in repo). If the server overlay fails, `report.js` regenerates it
  client-side via Canvas.
- The full DB-backed report flow could not be run in the dev sandbox (no MySQL
  there); the image engine, validation endpoint, and rule-based engine were
  unit/integration tested and pass.

## 13. Chakra image source
The Vastu Chakra PNG (North always on top) originates from:
`https://blog.shilaavinyaas.com/uploads/news-image/88/2.png`
and is stored locally at `backend/uploads/chakra-overlay.png`.

## 14. Testing (sandbox, PHP 8.4 + GD 2.3.3)
- `backend/tests/make_test_plans.php` — generates synthetic floor plans.
- `backend/tests/make_photo.php` — generates a colourful "photo" (rejection test).
- `backend/tests/test_analyzer.php` — Brahmasthan + zone-rotation + overlay tests.
- `backend/tests/test_validation.php` — validator + classifier-fallback tests.
- Results: 4 plan orientations -> Brahmasthan centred + zones correct; photo ->
  REJECTED; no-file -> REJECTED; classifier fallback -> needs_manual_review.

## 15. Current git state
- Branch `main` has all features above committed and pushed.
- Recent commits: property-type wording fix + subdirectory asset-path fix;
  chakra overlay side-by-side with heatmap; commercial analysis in VastuEngine;
  core image-analysis engine (Brahmasthan/entry/category screening/overlay).
