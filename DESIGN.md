---
name: CLSU Feedback Management System and Service Tracker
description: Inherited CLSU Figma visual system for the MISO workspace and service portal.
colors:
  green: "#008447"
  deep: "#005b30"
  portal: "#12553d"
  portal-button: "#21714f"
  campus: "#113e2f"
  gold: "#f2b600"
  ink: "#1e3029"
  muted: "#617169"
  surface: "#f5f8f6"
  line: "#dce3de"
  soft: "#eaf3ed"
  white: "#fff"
  primary-hover: "#0d432f"
  focus: "#bd8e1a"
  dashboard-line: "#dee5df"
  dashboard-clarification: "#fff4de"
typography:
  display:
    fontFamily: '"Plus Jakarta Sans", sans-serif'
    fontSize: "48px"
    fontWeight: 500
    lineHeight: 1.18
    letterSpacing: "-0.025em"
  headline:
    fontFamily: '"Plus Jakarta Sans", sans-serif'
    fontSize: "30px"
    fontWeight: 700
    lineHeight: 1.3
    letterSpacing: "-0.035em"
  title:
    fontFamily: '"Plus Jakarta Sans", sans-serif'
    fontSize: "16px"
    fontWeight: 700
    lineHeight: 1.5
    letterSpacing: "-0.01em"
  body:
    fontFamily: '"Plus Jakarta Sans", sans-serif'
    fontSize: "12px"
  label:
    fontFamily: '"Plus Jakarta Sans", sans-serif'
    fontSize: "10px"
    fontWeight: 700
    letterSpacing: "0.07em"
rounded:
  field: "4px"
  panel: "6px"
  requester-nav: "7px"
  requester-stat: "12px"
  dashboard-button: "8px"
spacing:
  icon-gap: "8px"
  action-gap: "10px"
  grid-gap: "16px"
  panel-gap: "20px"
  section-gap: "24px"
components:
  button-primary:
    backgroundColor: "{colors.portal}"
    textColor: "{colors.white}"
    rounded: "{rounded.panel}"
    padding: "11px 15px"
  button-admin-primary:
    backgroundColor: "{colors.green}"
    textColor: "{colors.white}"
    rounded: "{rounded.panel}"
    padding: "11px 15px"
  button-secondary:
    backgroundColor: "{colors.white}"
    textColor: "{colors.portal}"
    rounded: "{rounded.panel}"
    padding: "11px 15px"
  input:
    backgroundColor: "{colors.white}"
    textColor: "{colors.ink}"
    rounded: "{rounded.field}"
    padding: "11px 12px"
  panel:
    backgroundColor: "{colors.white}"
    rounded: "{rounded.panel}"
  requester-dashboard-panel:
    backgroundColor: "{colors.white}"
    rounded: "{rounded.requester-stat}"
  requester-dashboard-button:
    backgroundColor: "{colors.portal}"
    textColor: "{colors.white}"
    rounded: "{rounded.dashboard-button}"
    padding: "0 16px"
    height: "44px"
---

# Design System: CLSU Feedback Management System and Service Tracker

## Overview

Preserve the supplied [CLSU Figma file](https://www.figma.com/design/PeQN6nVBQbPNXWoTU32Mhm/FEEDBACK-MANAGEMENT). The inherited system has a flat green and gold administrative workspace, a forest-green service portal, white panels, and compact operational tables. No new visual identity or named metaphor is introduced.

This document records the current implementation, with `frontend/src/styles.css`, `components.tsx`, `App.tsx`, and `main.tsx` as implementation truth. `design-reference/admin.tsx.txt` and `portal.tsx.txt` preserve exported source evidence. Values can differ slightly from those exports: the implemented campus background, for example, is the `campus` token. This inventory does not certify pixel equivalence or backend readiness.

The requester home at `/app/requester` specifically follows the middle **Your dashboard** frame `26:22477` in that Figma file. `design-reference/requester.tsx.txt` is its exported visual authority; `frontend/src/RequesterDashboard.tsx` and `requester-dashboard.css` hold its scoped implementation. Its panel shadow, spacing, and icon variants are intentional exceptions to the broader flat admin system.

**Key Characteristics:**

- Original exported CLSU seal, campus architecture, sun, and icons.
- Self-hosted Plus Jakarta Sans, green navigation, restrained gold separators.
- Compact admin density and more spacious requester panels.
- Real record counts and states replace illustrative Figma content; demo records remain identified as demo data.

## Colors

The frontmatter captures shared CSS primitives. Green marks admin actions and selected navigation; deep green supports identity and headings. Portal green fills requester navigation and primary actions; campus green anchors the welcome artwork. Gold marks the admin header divider, selected sidebar edge, and small accents. White panels sit on the surface background with line borders; ink and muted distinguish primary and supporting text. Preserve these separate green roles.

Status colors are contextual CSS variants, not additional brand accents. Critical uses red; High, Pending, and On Hold use amber; Medium and Completed use green; Low and In Progress use blue; Assigned uses purple; For Clarification uses warm orange. Always retain the visible status text and dot. Exact variant declarations remain in `.badge` rules in the stylesheet.

## Typography

All roles use Plus Jakarta Sans with sans-serif fallback. `main.tsx` imports self-hosted Fontsource weights 400, 500, 600, and 700. Preserve those imports rather than adding a remote font dependency.

The frontmatter headline is the base h1. Admin page titles override it to 28px; requester titles use 32px/600 with -0.025em tracking. The welcome display uses the display role, while the choice-area heading is 34px/600. Panel headings use 15px; base h3 uses 13px/600. Paragraphs use 1.75 line-height; small text uses 10px/1.7. Eyebrows are compact, bold, and tracked; navigation and table text primarily use 10–12px. Do not replace this observed density with a larger generic dashboard type scale.

Requester dashboard overrides use a 32px/600 heading with 40px line-height, 14px supporting introduction at 1.5 line-height, 18px/600 panel headings at 1.3 line-height, and 32px/600 tabular metric numbers at 40px line-height. Rating-card heading uses 20px/400. Table titles use 13px/500; service descriptions use 12px, job numbers 10px/600, and column labels 10px/500. Its desktop navigation and actions use 13px text.

## Layout

Admin desktop layout uses a fixed 247px white sidebar, a 76px header, and content offset by the sidebar. The header has a gold bottom border. Main content uses 33px 35px 60px padding and max-width 1947px. Four equal statistic columns use 16px gaps. Panels use 20px separation, 21px body padding, and divided headers and footers. Tables remain inside horizontally scrollable wrappers.

Requester desktop layout uses a horizontal white header, 64px side padding, and centered main content capped at 1540px. Welcome uses shrinkable 45fr campus / 55fr choice-area columns and a minimum height of `calc(100svh - 100px)`, without a fixed 1024px campus minimum. Campus content is centered with a 528px maximum width. The right choice area is centered within its column, spans its available width, and is capped at 792px; its default inline padding is `clamp(35px, 7.2vw, 104px)`. The body supports a minimum width of 320px.

Welcome vertical spacing follows viewport height: campus block padding uses `clamp(36px, 4.65vh, 60px)`, message top spacing uses `clamp(32px, 5.34vh, 60px)`, artwork top spacing uses `clamp(32px, 4.98vh, 65px)`, location top spacing uses `clamp(32px, 5.16vh, 58px)`, and choice block padding uses `clamp(32px, 5vh, 70px)`. `CampusArtwork` observes its wrapper with `ResizeObserver` and scales the original fixed 528×250 scene by `min(1, wrapper width / 528)`. Its wrapper retains the 528/250 aspect ratio; the original SVG root geometry and sun/building positions remain intact.

The scoped requester dashboard caps main content at 1440px with 32px 64px 40px padding. Its 100px header uses `1fr auto 1fr` columns to center navigation, followed by a 48px context strip. Intro, metrics, clarification, content columns, and full-width help use a 24px vertical rhythm. Four metric columns use 16px gaps and 24px card padding. Main content columns use `minmax(0, 936fr) minmax(0, 352fr)` with a 24px gap. The right column holds the optional rating prompt and updates, with 20px separation. The dashboard footer is hidden. Panel headings use 24px 24px 20px padding; recent-request rows use 22px 24px padding and a horizontally scrollable table.

Responsive behavior is implementation-specific:

- At 1600px and wider, welcome choice inline padding is 104px and campus inline padding is 60px.
- At 1250px and below, requester gutters become 35px, tools wrap, and personnel cards use two columns. Campus artwork scales continuously from wrapper width rather than a breakpoint-specific scale.
- At 1000px and below, content gutters become 24px, statistics become two columns, charts stack, and admin details collapse to one main column. The admin sidebar becomes a toggled 247px drawer capped at `calc(100vw - 44px)`, below a 65px header; admin content loses its sidebar offset. Requester headers become flexible rows with at least 85px height; desktop navigation hides and the wrapping mobile navigation and toggles become available.
- At 760px and below, the welcome campus panel hides; the choice area becomes a single centered column without a forced content minimum height. The welcome layout retains `calc(100svh - 85px)` minimum height. Requester detail, dashboard, and form layouts stack; supplementary form aside content hides. Context strips wrap with automatic height, at least 44px, and 12px block padding. Inputs use 14px text and at least 44px height. Table overflow remains available.
- At 420px and below, public gutters become 24px, requester sidebar panels stack, and personnel cards become one column. Statistic cards retain two columns.
- At 380px and below, public gutters become 20px and requester main gutters become 16px. Session controls and filters wrap; search occupies a full filter row. Identity text can wrap, breadcrumbs can break long text, and metrics retain two shrinkable columns.

For the scoped requester dashboard, 1250px reduces gutters to 35px and compacts navigation; 1000px reduces gutters to 24px, wraps title actions, changes metrics to two columns, and changes main columns to `minmax(0, 1.7fr) minmax(230px, 1fr)`. Shared final overrides also collapse its header navigation at 1000px. At 760px gutters become 20px, heading becomes 27px, metrics retain two columns with 12px gaps, and content columns stack; the context strip can grow beyond its 44px minimum. Clarification actions wrap below their text; recent-table first cells retain a 220px minimum and overflow scrolls. At 420px metric padding reduces to 15px and captions to 9px; at 380px main gutters become 16px. These are responsive adaptations of frame `26:22477`, not claims that every width has a separate supplied Figma frame.

`frontend/src/responsive.css` is imported last, after shared and requester-dashboard styles, and owns final reflow overrides. Shared shrinkable grid children, wrapping action groups, and long-text wrapping prevent content from forcing the page wider; dense tables keep intentional internal scrolling. Breakpoints follow the effective CSS viewport, including browser zoom. Reflow checks use zoom-equivalent CSS viewport dimensions rather than browser zoom commands; that method does not independently verify browser zoom behavior. Preserve normal zoom and font scaling instead of counteracting them.

## Elevation & Depth

Shared desktop panels and controls are flat, separated by borders and background tones. The mobile sidebar adds `10px 0 25px #163b2b14`. The scoped requester dashboard panels preserve the supplied subtle `0 3px 6px #123e2a08` shadow; metric cards remain border-defined. Do not extend this local shadow to the admin workspace or introduce gradients as a new depth system. Focus uses a 3px gold outline with 3px offset, alongside green field borders.

Links and buttons transition background and text color over 160ms ease-out. Main content enters with a 250ms opacity animation. Mobile drawer transform uses 180ms ease-out; loading uses a 0.8s linear spinner. The reduced-motion query removes animation and transitions.

## Shapes

Fields, badges, and admin navigation use 4px corners; shared buttons and panels use the panel radius. Requester desktop navigation uses 7px, and requester statistic cards use 12px. Avatars and status dots are circular. Preserve thin borders and the selected sidebar's 3px gold left edge. Do not apply pill styling to rectangular status badges.

Scoped requester dashboard panels, metrics, clarification, rating prompt, and help use 12px corners; its 44px actions use 8px corners and its icon-bearing status labels use 6px corners.

## Components

### Buttons

Shared `.button` is inline-flex with an 8px icon gap, 12px/600 text, 40px minimum height, and a 1px border. Default buttons are white with portal text; hover uses soft green and deep text. Portal primary buttons use portal green and darken on hover. Admin primary buttons use admin green; the admin selector also retains that fill on hover. Disabled buttons have 0.55 opacity and a not-allowed cursor. Preserve visible focus outlines.

### Inputs and fields

White controls have thin line borders, field corners, and 11px 12px padding. Focus changes the border to green. Disabled inputs use a pale background and muted text. `Field` associates labels and hints with native inputs; `ErrorNotice` renders errors as alerts. Keep field errors and help text adjacent to their controls.

### Panels and tables

`Panel` provides an optional divided heading with title, subtitle, and action. Its clipped white surface has a thin border and no shadow. Table toolbars combine tabs, search, and filters; mobile layouts wrap controls and preserve horizontal table scrolling. Empty and loading states occupy the panel instead of invented records.

On the scoped requester dashboard, `Panel` uses the dashboard line color, 12px corners, the source shadow, and an undivided heading. The green rating prompt appears only for an unrated completed request. The amber clarification banner appears only when clarification is needed. Summary cards show all-time authenticated-owner counts: **My requests**, **Open requests**, **Completed**, and **Awaiting my rating**; they are independent of the limited recent-request list. Empty/loading/error states replace missing content rather than reproducing sample Figma records. Request actions adapt to actual state, including clarification, tracking, and rating. Dates and update times use the application formatting, with update times in Asia/Manila.

### Navigation and identity

Role homes are distinct: `/app/admin` uses the MISO sidebar workspace, `/app/requester` uses the supplied requester dashboard, and `/app/work` is the assigned-personnel adaptation of the existing portal components. Personnel navigation contains assignments and notifications, without requester submission or evaluation prompts. Do not treat the Figma admin personnel directory as a personnel work dashboard.

Admin active navigation has a solid green background, white text and icons, and gold left edge. Inactive hover uses soft green. Requester active desktop navigation uses portal green; mobile active links use a soft background and portal text. Reuse `Identity`, `Asset`, and `Icon` so the original local Figma assets remain consistent. Base icons are 18px; sidebar icons are 20px.

For requester dashboard controls, use `RequesterIcon` with the exact exported names under `/assets/figma/requester/`, through `Asset screen="requester"`. Preserve original colored variants rather than substituting shared admin icons or applying the shared primary-button inversion filter. Metric exports are `imgFiles1`, `imgClock3`, `imgCircleCheck`, and `imgStar`; status exports include `imgClock4`, `imgMessageSquareText1`, `imgLoaderCircle`, and `imgCircleCheck1`. Preserve exported navigation, notification, lock, rating, clarification, and help icons as well. Notification indicators reflect actual unread counts.

### Badges and operational exceptions

`Badge` derives its CSS class from the record value and falls back to Unreviewed. Keep color, text, and dot together. Figma's sample people, numbers, and dates are content examples, not fixed design tokens. The header links to admin setup and reports actual server readiness (MCP disabled/ready), without claiming a live client connection. The assistant and settings tabs explain the local-only mode and disabled provider calls and separate ChatGPT OAuth setup. Do not restore the exported “MCP connected” label without evidence.

## Do's and Don'ts

- **Do** preserve the supplied Figma composition and original local identity and illustration assets.
- **Do** use the existing CSS layers, shared components, green roles, and self-hosted font weights.
- **Do** preserve responsive navigation, keyboard focus, reduced motion, and scrollable tables.
- **Do** distinguish live records, empty states, and clearly labeled demo content.
- **Don't** invent a new visual world, metaphor, brand claim, illustration, icon family, or font pairing.
- **Don't** add floating shadow cards, gradients, or pill badges to the inherited flat workspace; retain the supplied subtle shadow only in the scoped requester dashboard panels.
- **Don't** present disabled MCP capabilities or illustrative Figma values as operational facts.
