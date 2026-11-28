<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $definition->title }} · Pickle Lite</title><x-platform-favicon/><link rel="stylesheet" href="/app.css?v=legal1"><link rel="stylesheet" href="/legal.css?v=1"><link rel="stylesheet" href="/brand.css?v=court1"></head>
<body>
<header class="site-header"><div class="header-inner"><a class="brand" href="{{ route('product.home') }}"><x-platform-logo/></a><nav><a href="{{ route('product.home') }}">Home</a><a href="{{ route('app.login') }}">Sign in</a></nav></div></header>
<main class="page legal-public">
    <a href="{{ route('product.home') }}">← Pickle Lite</a>
    <div class="legal-heading"><p class="muted">Legal information</p><h1>{{ $definition->title }}</h1>
        @if($record)<p class="muted">Version {{ $record->version }} · Published {{ \Carbon\CarbonImmutable::parse($record->published_at)->setTimezone('Asia/Manila')->format('F j, Y') }}</p>@endif
    </div>
    <section class="panel">
        @if($record)
            @if($record->id !== $definition->current_version_id)<p class="notice">This is an archived version. <a href="{{ route('product.legal.show',['document'=>$definition->slug]) }}">Read the current policy</a>.</p>@endif
            <div class="legal-prose">{{ $record->body }}</div>
        @else
            <h2>Policy not yet published</h2>
            <p>The platform operator is preparing this policy for review. No draft is being represented as an approved or effective agreement.</p>
            <p>For reservations or payment questions, contact the venue directly. For Pickle Lite enquiries, use the <a href="{{ route('product.home') }}#get-started">product enquiry form</a>.</p>
        @endif
    </section>
    <footer class="legal-footer"><x-legal-links :subscription="true"/></footer>
</main>
</body></html>
