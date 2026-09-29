# Iconors Brand Palette — Extracted, Not Invented

Every value below was pulled from the live site on 2026-07-18. Nothing here is a guess.

## Extraction Evidence

| Source | Finding |
|---|---|
| `<meta name="theme-color">` on iconors.com | `#3cf7a2` |
| Theme CSS custom property `--primary-color` | `#3cf7a2` |
| Theme CSS custom property `--primary-text-color` | `#040408` |
| WP preset `--wp--preset--color--purple` | `#5a00f0` (used in CSS as `#5a01f0`) |
| WP preset `--wp--preset--color--dark-blue` | `#28375a` |
| Live gradient declaration | `linear-gradient(-135deg, #1e0046, #1e0064)` |
| Logo SVG (`Iconors-logo-with-name.svg`) | 100% `#FFFFFF` — a knockout/reversed mark |
| Frequency rank in homepage CSS | `#3CF7A2` (31), `#28375A` (30), `#27D385` (21), `#040408` (17) |

### Two findings that shape the whole design system

**1. Iconors is mint-green-led, not purple/blue.** The `theme-color` meta tag and
`--primary-color` agree exactly. The purple family is *secondary*. This is why the brief's
warning against a "generic purple or blue SaaS palette" matters — the real brand is
distinctly greener than the category norm.

**2. The logo is a white knockout, so it can never sit on the mint.** Measured contrast:

| Pairing | Ratio | Verdict |
|---|---|---|
| White logo on `#1E0046` (deep purple) | **18.4:1** | Correct usage |
| White logo on `#3CF7A2` (mint) | **1.4:1** | Invisible — never do this |
| `#040408` text on `#3CF7A2` | **14.6:1** | Correct usage |
| `#3CF7A2` text on white | **1.4:1** | Fails WCAG — never as text on light |

The mint is a **surface/CTA-fill colour carrying near-black text**, not a text colour.
A primary button is therefore mint background + `#040408` label. Encoding this as a rule
now prevents an accessibility failure that would otherwise be repeated across every page,
and keeps us inside the WCAG 2.2 AA requirement in §36 of the brief.

## Token Table

| Token | HEX | RGB | HSL |
|---|---|---|---|
| Primary | `#3CF7A2` | `60, 247, 162` | `153, 92%, 60%` |
| Primary Light | `#2DFFB3` | `45, 255, 179` | `158, 100%, 59%` |
| Primary Dark | `#27D385` | `39, 211, 133` | `153, 69%, 49%` |
| Primary Darker | `#27AB81` | `39, 171, 129` | `161, 63%, 41%` |
| Secondary | `#28375A` | `40, 55, 90` | `222, 38%, 25%` |
| Secondary Dark | `#1B2440` | `27, 36, 64` | `225, 41%, 18%` |
| Secondary Light | `#3C465A` | `60, 70, 90` | `220, 20%, 29%` |
| Accent (purple) | `#5A01F0` | `90, 1, 240` | `262, 99%, 47%` |
| Accent Deep | `#1E0046` | `30, 0, 70` | `266, 100%, 14%` |
| Accent Deep Alt | `#1E0064` | `30, 0, 100` | `258, 100%, 20%` |
| Heading | `#040408` | `4, 4, 8` | `240, 33%, 2%` |
| Body Text | `#28375A` | `40, 55, 90` | `222, 38%, 25%` |
| Muted Text | `#717179` | `113, 113, 121` | `240, 3%, 46%` |
| Border | `#E9EDF7` | `233, 237, 247` | `223, 44%, 94%` |
| Surface | `#FFFFFF` | `255, 255, 255` | `0, 0%, 100%` |
| Background | `#FAF9FB` | `250, 249, 251` | `270, 20%, 98%` |
| Dark Background | `#070518` | `7, 5, 24` | `246, 66%, 6%` |
| Dark Surface | `#150E20` | `21, 14, 32` | `263, 39%, 9%` |
| Success | `#27AB81` | `39, 171, 129` | `161, 63%, 41%` |
| Warning | `#ECCA2E` | `236, 202, 46` | `49, 84%, 55%` |
| Error | `#E6174B` | `230, 23, 75` | `345, 82%, 50%` |
| Info | `#5A01F0` | `90, 1, 240` | `262, 99%, 47%` |

**Signature gradient** (lifted verbatim from the live site):
`linear-gradient(-135deg, #1E0046, #1E0064)`

**Brand gradient** (derived, mint→purple, for CTAs and hero accents):
`linear-gradient(135deg, #3CF7A2 0%, #5A01F0 100%)`

## Usage Rules

- Mint = fills, CTAs, badges, highlights. **Always** with `#040408` text on top.
- Never render mint as text on a light background.
- White logo goes on deep purple, near-black, or the signature gradient.
- Purple carries dark surfaces and the signature gradient; it is the depth colour.
- `#E6174B` is reserved for destructive/error states, not decoration.

## Dynamic Override

These are *defaults only*. Per §2 of the brief, every token is seeded into the `settings`
table and emitted at runtime as CSS custom properties, so an administrator can restyle the
platform without a source edit. See `docs/ARCHITECTURE.md` § Dynamic Branding.
