<?php
/**
 * Google and Meta: what loads, when, and only after being allowed to.
 *
 * There are two quite different things in here and they must not be confused.
 *
 * VERIFICATION TAGS — the Search Console meta and the Meta domain meta — are
 * inert strings. They set no cookie, load no script and identify nobody, so
 * they go in the head of every page unconditionally. Gating those behind a
 * banner would mean Search Console never verifies.
 *
 * MEASUREMENT TAGS — Analytics, Tag Manager, Google Ads, the Meta Pixel — set
 * cookies and send a visitor's behaviour to a third party. This shop sells
 * into the UK, where PECR requires consent BEFORE a non-essential cookie is
 * set, and the ICO is explicit that analytics is not exempt. So none of them
 * is fetched until somebody has said yes. Not fetched-but-throttled, not
 * loaded-with-consent-denied: not fetched.
 *
 * That is a deliberate choice over Google's Consent Mode, which loads gtag.js
 * first and withholds the cookies. Consent Mode is defensible and Google
 * prefers it because it keeps their modelling working, but it means a request
 * to a Google server carrying an IP address before anybody agreed to
 * anything. The stricter reading is easier to defend and easier to check —
 * open the network panel, and before consent there is nothing there.
 *
 * Events are queued by the page and only flushed once the tags exist. Nothing
 * that happened before consent is replayed afterwards: consent is permission
 * from that moment, not a licence to hand over the browsing that preceded it.
 */
declare(strict_types=1);

/** Every id the shop has been given, trimmed. */
function tracking_ids(): array
{
    static $ids = null;
    if ($ids !== null) return $ids;

    $get = fn(string $k) => trim((string) setting($k));

    return $ids = [
        'ga4'        => $get('ga4_id'),           // G-XXXXXXXXXX
        'gtm'        => $get('gtm_id'),           // GTM-XXXXXXX
        'ads'        => $get('google_ads_id'),    // AW-XXXXXXXXX
        'ads_label'  => $get('google_ads_label'), // the conversion label
        'pixel'      => $get('meta_pixel_id'),    // 15-16 digits
        'gsc'        => $get('google_verify'),    // Search Console token
        'meta_verify'=> $get('meta_verify'),      // Meta domain verification
    ];
}

/** True when anything at all would be loaded, so the banner has a reason to exist. */
function tracking_wanted(): bool
{
    $ids = tracking_ids();
    return $ids['ga4'] !== '' || $ids['gtm'] !== '' || $ids['ads'] !== '' || $ids['pixel'] !== '';
}

/** Consent may be switched off where it is genuinely not required. */
function consent_required(): bool
{
    return tracking_wanted() && (bool) setting('consent_required');
}

/**
 * The verification metas. Inert, cookie-free, and needed before Search Console
 * or Meta will confirm the domain — so never behind the banner.
 */
function tracking_head(): string
{
    $ids = tracking_ids();
    $out = '';

    if ($ids['gsc'] !== '') {
        $out .= '<meta name="google-site-verification" content="' . e($ids['gsc']) . '">' . "\n";
    }
    if ($ids['meta_verify'] !== '') {
        $out .= '<meta name="facebook-domain-verification" content="' . e($ids['meta_verify']) . '">' . "\n";
    }
    return $out;
}

/**
 * What the browser needs to load the tags itself, once it is allowed to.
 *
 * The ids go to the page; the loading does not happen here. assets/js/track.js
 * decides, because the decision depends on a choice stored in the browser and
 * the page is not rendered per-visitor.
 */
function tracking_config(): array
{
    $ids = tracking_ids();
    return [
        'ga4'       => $ids['ga4'],
        'gtm'       => $ids['gtm'],
        'ads'       => $ids['ads'],
        'adsLabel'  => $ids['ads_label'],
        'pixel'     => $ids['pixel'],
        'needed'    => tracking_wanted(),
        'ask'       => consent_required(),
        'currency'  => (string) setting('currency'),
    ];
}

/* ------------------------------------------------------------------ events */

/**
 * Events the current page wants sent, once there is somewhere to send them.
 *
 * Named the Google way; track.js translates each to its Meta equivalent, so a
 * page describes what HAPPENED and neither vendor's vocabulary leaks into the
 * templates.
 */
function track_event(string $name, array $params = []): void
{
    $GLOBALS['track_events'][] = ['name' => $name, 'params' => $params];
}

function tracking_events(): array
{
    return (array) ($GLOBALS['track_events'] ?? []);
}

/** One product in the shape both vendors want. */
function track_item(array $p, string $option = '', int $qty = 1, ?int $price = null): array
{
    $p = product_defaults($p);
    return [
        'item_id'   => (string) ($p['sku'] !== '' ? $p['sku'] : $p['slug']),
        'item_name' => (string) $p['name'],
        'item_variant' => $option,
        'item_category' => (string) product_cat_label($p),
        'price'     => round((($price ?? (int) effective_min($p)) / 100), 2),
        'quantity'  => max(1, $qty),
    ];
}
