<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($homeUrls as $url)
    <url>
        <loc>{{ $url }}</loc>
@foreach ($homeUrls as $code => $alternateUrl)
        <xhtml:link rel="alternate" hreflang="{{ $code }}" href="{{ $alternateUrl }}"/>
@endforeach
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ $defaultHomeUrl }}"/>
    </url>
@endforeach
</urlset>
