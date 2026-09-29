<?php

namespace Tests\Feature;

use App\Services\Asset\SvgSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * Brief §12 / §32: uploaded SVG must be sanitised before storage.
 *
 * Each case below is a real, published SVG-XSS vector. An SVG served from
 * your own origin executes with your origin's privileges, so a miss here
 * is same-origin script execution on every page that embeds the asset.
 */
class SvgSanitizerTest extends TestCase
{
    private SvgSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new SvgSanitizer();
    }

    public function test_it_keeps_a_legitimate_icon_intact(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">'
             .'<path d="M12 2L2 7l10 5 10-5z" fill="#3CF7A2" stroke="#040408" stroke-width="1.5"/>'
             .'</svg>';

        $result = $this->sanitizer->sanitize($svg);

        $this->assertSame([], $result['removals'], 'A clean icon must pass untouched.');
        $this->assertStringContainsString('<path', $result['svg']);
        $this->assertStringContainsString('#3CF7A2', $result['svg']);
    }

    public function test_it_strips_script_elements(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.cookie)</script>'
             .'<rect width="10" height="10"/></svg>';

        $result = $this->sanitizer->sanitize($svg);

        $this->assertStringNotContainsString('script', $result['svg']);
        $this->assertStringNotContainsString('alert', $result['svg']);
        $this->assertStringContainsString('<rect', $result['svg']);
    }

    /** The most common vector — an event handler on an ordinary shape. */
    public function test_it_strips_event_handlers(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg">'
             .'<rect width="10" height="10" onload="alert(1)" onclick="steal()" onmouseover="x()"/>'
             .'</svg>';

        $result = $this->sanitizer->sanitize($svg);

        $this->assertStringNotContainsString('onload', $result['svg']);
        $this->assertStringNotContainsString('onclick', $result['svg']);
        $this->assertStringNotContainsString('onmouseover', $result['svg']);
        $this->assertStringContainsString('<rect', $result['svg']);
    }

    public function test_it_strips_javascript_uris_in_href(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg">'
             .'<a href="javascript:alert(1)"><text>click</text></a></svg>';

        $result = $this->sanitizer->sanitize($svg);

        $this->assertStringNotContainsString('javascript:', $result['svg']);
    }

    /**
     * Whitespace and control characters inside the scheme still execute in
     * browsers, so normalisation has to happen before matching.
     */
    public function test_it_catches_obfuscated_javascript_uris(): void
    {
        $svg = "<svg xmlns=\"http://www.w3.org/2000/svg\">"
             ."<use xlink:href=\"java\nscript:alert(1)\"/></svg>";

        $result = $this->sanitizer->sanitize($svg);

        $this->assertStringNotContainsString('alert', $result['svg']);
    }

    public function test_it_strips_foreign_object_which_can_embed_html(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject>'
             .'<body xmlns="http://www.w3.org/1999/xhtml"><img src=x onerror="alert(1)"></body>'
             .'</foreignObject></svg>';

        $result = $this->sanitizer->sanitize($svg);

        $this->assertStringNotContainsString('foreignObject', $result['svg']);
        $this->assertStringNotContainsString('onerror', $result['svg']);
    }

    /** XXE — reads server files and exfiltrates them through the rendered output. */
    public function test_it_neutralises_xxe_entity_declarations(): void
    {
        $svg = '<?xml version="1.0"?>'
             .'<!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'
             .'<svg xmlns="http://www.w3.org/2000/svg"><text>&xxe;</text></svg>';

        $result = $this->sanitizer->sanitize($svg);

        $this->assertStringNotContainsString('/etc/passwd', $result['svg']);
        $this->assertStringNotContainsString('ENTITY', $result['svg']);
        $this->assertNotEmpty($result['removals']);
    }

    /** Billion laughs — exponential entity expansion, a memory-exhaustion DoS. */
    public function test_it_neutralises_entity_expansion_bomb(): void
    {
        $svg = '<?xml version="1.0"?>'
             .'<!DOCTYPE lolz [<!ENTITY lol "lol">'
             .'<!ENTITY lol2 "&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;">'
             .'<!ENTITY lol3 "&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;">]>'
             .'<svg xmlns="http://www.w3.org/2000/svg"><text>&lol3;</text></svg>';

        $result = $this->sanitizer->sanitize($svg);

        $this->assertStringNotContainsString('ENTITY', $result['svg']);
    }

    public function test_it_strips_external_image_references(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg">'
             .'<image href="https://attacker.example/track.png" width="1" height="1"/></svg>';

        $result = $this->sanitizer->sanitize($svg);

        $this->assertStringNotContainsString('attacker.example', $result['svg']);
    }

    public function test_it_allows_internal_fragment_references(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg">'
             .'<defs><path id="star" d="M0 0L5 5z"/></defs>'
             .'<use href="#star" fill="#3CF7A2"/></svg>';

        $result = $this->sanitizer->sanitize($svg);

        // <use href="#id"> is legitimate and extremely common in icon sets;
        // stripping it would break a large share of real uploads.
        $this->assertStringContainsString('href="#star"', $result['svg']);
        $this->assertSame([], $result['removals']);
    }

    public function test_it_strips_style_blocks_containing_remote_fetches(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg">'
             .'<style>@import url("https://attacker.example/x.css");</style>'
             .'<rect width="10" height="10"/></svg>';

        $result = $this->sanitizer->sanitize($svg);

        $this->assertStringNotContainsString('attacker.example', $result['svg']);
        $this->assertStringContainsString('<rect', $result['svg']);
    }

    public function test_it_rejects_a_file_that_is_not_svg_at_all(): void
    {
        $result = $this->sanitizer->sanitize('<html><body>not an svg</body></html>');

        $this->assertSame('', $result['svg']);
        $this->assertNotEmpty($result['removals']);
    }

    public function test_it_rejects_unparseable_input(): void
    {
        $result = $this->sanitizer->sanitize('<svg><rect ');

        $this->assertSame('', $result['svg']);
    }

    public function test_is_clean_reports_false_for_hostile_input(): void
    {
        $this->assertFalse($this->sanitizer->isClean(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect onload="alert(1)"/></svg>'
        ));

        $this->assertTrue($this->sanitizer->isClean(
            '<svg xmlns="http://www.w3.org/2000/svg"><rect width="4" height="4"/></svg>'
        ));
    }
}
