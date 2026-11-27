@extends('layout')
@section('title','Website settings')
@section('content')
    <header class="section-heading page-heading">
        <div><p class="venue-name">{{ $venue->name }}</p><h1>Website settings</h1><p class="muted">Customize the public venue website. Rates and schedules update automatically.</p></div>
        <div class="actions"><a class="button secondary" href="{{ \App\Services\VenueContext::url('settings.customization') }}">Booking setup</a><a class="button" href="{{ \App\Services\VenueContext::url('settings.website.preview') }}" target="_blank" rel="noopener">Preview website</a></div>
    </header>
    <div class="website-settings-grid">
        <section class="panel">
            <h2>Website content</h2>
            <p class="muted">Leave the headline and description blank to use the template copy.</p>
            <form class="form-stack settings-row" method="post" action="{{ \App\Services\VenueContext::url('settings.website.save') }}">
                @csrf @method('PATCH')
                <label>Hero headline<input name="headline" maxlength="120" value="{{ old('headline',$content['headline'] ?? '') }}" placeholder="Your next game starts here."></label>
                <label>Short description<textarea name="description" rows="3" maxlength="350" placeholder="Play with friends, meet new players and enjoy your next game.">{{ old('description',$content['description'] ?? '') }}</textarea></label>
                <label>Venue introduction<textarea name="introduction" rows="4" maxlength="1500">{{ old('introduction',$content['introduction'] ?? '') }}</textarea></label>
                <h2>Venue facilities</h2>
                <p class="muted">Only select facilities that your venue actually offers. Court count and indoor/outdoor types come from court settings.</p>
                <input type="hidden" name="facilities_present" value="1">
                <div class="website-facility-grid">
                    @foreach(\App\Services\VenueWebsite::FACILITIES as $key => $label)
                        <label class="checkbox"><input name="facilities[]" type="checkbox" value="{{ $key }}" @checked(in_array($key,(array)old('facilities',old('facilities_present') ? [] : ($content['facilities'] ?? [])),true))> {{ $label }}</label>
                    @endforeach
                </div>
                <label>Other facilities (one per line)<textarea name="custom_facilities" rows="4" maxlength="700" placeholder="Up to 10 additional facilities">{{ old('custom_facilities',implode("\n",$content['custom_facilities'] ?? [])) }}</textarea></label>
                <h2>Demo gallery</h2>
                <input type="hidden" name="show_demo_gallery" value="0">
                <label class="checkbox"><input type="checkbox" name="show_demo_gallery" value="1" @checked(old('show_demo_gallery', $content['show_demo_gallery'] ?? true))> Show illustrative demo images when no venue photos are uploaded</label>
                <p class="muted">Demo photos are clearly labeled. Uploading any real venue photo replaces the demo gallery.</p>
                <h2>Frequently asked questions</h2>
                <p class="muted">Optional additional questions. The default FAQ covers booking, Open Play, prices, payment confirmation, booking durations, lead time and cancellation.</p>
                @for($i=0;$i<5;$i++)
                    <details class="settings-row" @if(!empty(old('faq_questions.'.$i,$content['faqs'][$i]['question'] ?? ''))) open @endif>
                        <summary>FAQ {{ $i+1 }}</summary>
                        <div class="form-stack">
                            <label>Question<input name="faq_questions[]" maxlength="120" value="{{ old('faq_questions.'.$i,$content['faqs'][$i]['question'] ?? '') }}"></label>
                            <label>Answer<textarea name="faq_answers[]" rows="3" maxlength="500">{{ old('faq_answers.'.$i,$content['faqs'][$i]['answer'] ?? '') }}</textarea></label>
                        </div>
                    </details>
                @endfor
                <h2>Search and social sharing</h2>
                <label>SEO page title<input name="seo_title" maxlength="100" value="{{ old('seo_title',$content['seo_title'] ?? '') }}" placeholder="{{ $venue->name }} | Pickleball courts & Open Play"></label>
                <label>SEO description<textarea name="seo_description" rows="3" maxlength="200">{{ old('seo_description',$content['seo_description'] ?? '') }}</textarea></label>
                <button class="button">Save website content</button>
            </form>
        </section>
        <div class="form-stack">
            <section class="panel">
                <h2>Share your website</h2>
                <div class="form-stack settings-row">
                    <label>Website link<input type="url" value="{{ \App\Services\VenueContext::url('landing') }}" readonly></label>
                    <label>Direct booking link<input type="url" value="{{ \App\Services\VenueContext::url('book') }}" readonly></label>
                    <a href="{{ \App\Services\VenueContext::url('settings.customization') }}#branding">Update logo, cover, map and Messenger links</a>
                    <a href="{{ \App\Services\VenueContext::url('settings') }}">Update venue address, phone, courts and opening hours</a>
                    <a href="{{ \App\Services\VenueContext::url('settings.customization') }}#rates">Update court pricing</a>
                </div>
            </section>
            <section class="panel" id="gallery">
                <h2>Venue photo gallery</h2>
                <p class="muted">Upload up to six actual venue photos. JPG, PNG or WebP, up to 2 MB each. Real photos take priority over the illustrative demo gallery.</p>
                @foreach(\App\Services\VenueWebsite::GALLERY as $kind)
                    <form method="post" action="{{ \App\Services\VenueContext::url('settings.website.gallery',$kind) }}" enctype="multipart/form-data" class="form-stack settings-row">
                        @csrf @method('PATCH')
                        @if($media->has($kind))
                            <img class="website-gallery-preview" src="{{ \App\Services\VenueContext::url('book.image',['kind'=>$kind,'version'=>$media[$kind]->version]) }}" alt="Gallery photo {{ $loop->iteration }}">
                        @endif
                        <label>Photo {{ $loop->iteration }}<input type="file" name="photo" accept="image/jpeg,image/png,image/webp"></label>
                        <div class="actions">
                            <button class="button small">{{ $media->has($kind) ? 'Replace photo' : 'Upload photo' }}</button>
                            @if($media->has($kind))<button class="button secondary small" name="remove" value="1">Remove photo</button>@endif
                        </div>
                    </form>
                @endforeach
            </section>
        </div>
    </div>
@endsection
