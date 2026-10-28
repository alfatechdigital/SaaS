<?php

namespace App\Support;

use App\Models\CompanyProfile;
use App\Models\PortfolioItem;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Search and social metadata for a tenant's public page (Fase 3, tugas 3.5.2–3.5.6).
 *
 * Two rules shape this class:
 *
 * 1. **Nothing is hardcoded.** Every value comes from the tenant's own profile,
 *    or from a neutral fallback that names the tenant. A shared marketing string
 *    would be wrong for every tenant but one.
 * 2. **The tenant is passed in, not read from the active context.** The controller
 *    activates the tenant before calling this, but keeping the dependency explicit
 *    means the class can be used to render a preview or a sitemap entry for a
 *    tenant that is not the active one.
 *
 * No SEO columns were added to `company_profiles` (see phase-03 "Database
 * Changes"): the fields that exist already carry name, description, phone, email,
 * and address. Manual meta overrides only become justified once a tenant actually
 * asks for wording that differs from its profile.
 *
 * @see \docs\implementation\phase-03-domain-dan-seo.md tugas 3.5.2 s/d 3.5.6
 */
final class PublicPageSeo
{
    /**
     * Google truncates around 160 characters; anything longer is wasted in the
     * payload and truncated by the engine with an ellipsis of its own choosing.
     */
    private const DESCRIPTION_LIMIT = 160;

    /**
     * @param  Collection<int, PortfolioItem>  $portfolio  Published items only.
     */
    public function __construct(
        private readonly Team $team,
        private readonly ?CompanyProfile $profile,
        private readonly Collection $portfolio,
    ) {}

    /**
     * @param  Collection<int, PortfolioItem>  $portfolio
     */
    public static function for(Team $team, ?CompanyProfile $profile, Collection $portfolio): self
    {
        return new self($team, $profile, $portfolio);
    }

    /**
     * `<title>` and `og:title`. The company name is the tenant's own, never a
     * marketing line owned by another tenant.
     */
    public function title(): string
    {
        $name = trim((string) $this->profile?->company_name);

        return $name !== '' ? $name : $this->team->name;
    }

    /**
     * Meta description: plain text, bounded, and never the raw HTML of `about`.
     */
    public function description(): string
    {
        $text = trim(strip_tags((string) ($this->profile?->description ?: $this->profile?->about)));

        return $text !== ''
            ? Str::limit($text, self::DESCRIPTION_LIMIT)
            : __('Profil :name: layanan, portofolio, dan formulir konsultasi.', ['name' => $this->team->name]);
    }

    /**
     * The single canonical URL of the public page.
     *
     * Domain-aware by construction: today the URL is the slug path. When primary
     * domains land (ADR-04, wave 2) this method is the only place that changes —
     * canonical, `og:url`, JSON-LD `url`, and the sitemap all read it from here.
     */
    public function canonical(): string
    {
        return route('public.company-profile', ['team' => $this->team->slug]);
    }

    /**
     * Social preview image, taken from the tenant's own published work.
     *
     * There is no logo column, and inventing one is a product decision. A
     * relative path would be useless to a crawler, so only absolute `http(s)`
     * URLs qualify.
     */
    public function image(): ?string
    {
        foreach ($this->portfolio as $item) {
            $url = (string) $item->image_url;

            if (Str::startsWith($url, ['http://', 'https://'])) {
                return $url;
            }
        }

        return null;
    }

    /**
     * Everything the page needs for `<head>`, plus the JSON-LD payload.
     *
     * @return array{
     *     title: string,
     *     description: string,
     *     canonical: string,
     *     og: array{type: string, title: string, description: string, url: string, siteName: string, image: string|null},
     *     twitter: array{card: string, title: string, description: string, image: string|null},
     *     jsonLd: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        $image = $this->image();

        return [
            'title' => $this->title(),
            'description' => $this->description(),
            'canonical' => $this->canonical(),
            'og' => [
                'type' => 'website',
                'title' => $this->title(),
                'description' => $this->description(),
                'url' => $this->canonical(),
                'siteName' => $this->team->name,
                'image' => $image,
            ],
            'twitter' => [
                // A large card without an image renders as a blank banner, so the
                // card type follows the data instead of the other way round.
                'card' => $image === null ? 'summary' : 'summary_large_image',
                'title' => $this->title(),
                'description' => $this->description(),
                'image' => $image,
            ],
            'jsonLd' => $this->jsonLd(),
        ];
    }

    /**
     * Raw `<head>` markup that cannot be expressed as a Vue `<Head>` child.
     *
     * Vue's template compiler rejects a literal `<script>` ("tags with side effect
     * are ignored"), so Inertia's `serverHead` option is used instead: the server
     * hands over finished HTML and Inertia merges it with the `<Head>` output of
     * the page component. See `resources/js/app.ts`.
     *
     * @return list<string>
     */
    public function head(): array
    {
        return [sprintf(
            '<script type="application/ld+json">%s</script>',
            $this->jsonLdJson(),
        )];
    }

    /**
     * Schema.org `LocalBusiness` for the tenant.
     *
     * Google reads JSON-LD from `<head>` or `<body>`, so this ships as a script
     * tag rather than as microdata attributes scattered through the markup.
     *
     * @return array<string, mixed>
     */
    public function jsonLd(): array
    {
        $address = trim((string) $this->profile?->address);

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $this->title(),
            'description' => $this->description(),
            'url' => $this->canonical(),
            'telephone' => $this->profile?->phone,
            'email' => $this->profile?->email,
            'image' => $this->image(),
            // The profile stores the address as one free-text line, so it becomes
            // `streetAddress` rather than being split into guessed components.
            'address' => $address === '' ? null : [
                '@type' => 'PostalAddress',
                'streetAddress' => $address,
            ],
        ];

        return array_filter(
            $data,
            static fn (mixed $value): bool => $value !== null && $value !== '',
        );
    }

    /**
     * JSON-LD as an embeddable string.
     *
     * `JSON_HEX_TAG` is the important flag: without it a tenant whose company name
     * contains `</script>` would close the tag and inject markup into the page.
     */
    private function jsonLdJson(): string
    {
        return (string) json_encode(
            $this->jsonLd(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
        );
    }
}
