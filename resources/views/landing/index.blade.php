<!doctype html>
<html lang="en" class="venue-site-root" style="{{ \App\Services\VenueTheme::style($venue->brand_color) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDescription }}">
    <link rel="canonical" href="{{ \App\Services\VenueContext::url('landing') }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ \App\Services\VenueContext::url('landing') }}">
    @if($media->has('cover'))
        <meta property="og:image" content="{{ \App\Services\VenueContext::url('book.image',['kind'=>'cover','version'=>$media['cover']->version]) }}">
    @endif
    <link rel="icon" href="{{ $media->has('logo') ? \App\Services\VenueContext::url('book.image',['kind'=>'logo','version'=>$media['logo']->version]) : asset('favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('app.css') }}?v=theme1">
    <link rel="stylesheet" href="{{ asset('landing.css') }}?v=theme1">
</head>
<body class="venue-site">
    <a class="site-skip" href="#main-content">Skip to content</a>
    <header class="venue-site-header">
        <div class="site-container site-navigation">
            <a class="site-logo" href="{{ \App\Services\VenueContext::url('landing') }}">
                @if($media->has('logo'))
                    <img src="{{ \App\Services\VenueContext::url('book.image',['kind'=>'logo','version'=>$media['logo']->version]) }}" alt="">
                @else
                    <span class="site-mark"><x-booking-icon name="layout-grid" /></span>
                @endif
                <strong>{{ $venue->name }}</strong>
            </a>
            <nav aria-label="Venue navigation">
                <a href="#schedule">Schedule</a><a href="#rates">Rates</a><a href="#open-play">Open Play</a><a href="#facilities">Facilities</a>@if(count($galleryPhotos))<a href="#gallery">Gallery</a>@endif<a href="#faq">FAQ</a><a href="#contact">Contact</a>
            </nav>
            <a class="site-button site-primary" href="{{ \App\Services\VenueContext::url('book') }}">Book now <x-booking-icon name="chevron-right" /></a>
        </div>
    </header>
    <main id="main-content">
        <section class="site-container site-hero {{ $media->has('cover') ? 'has-cover' : 'no-cover' }}">
            <div class="site-hero-copy">
                <p class="site-eyebrow">{{ $venue->name }}</p>
                <h1>{{ $headline }}</h1>
                <p class="site-lead">{{ $description }}</p>
                <div class="site-actions">
                    <a class="site-button site-primary" href="{{ \App\Services\VenueContext::url('book') }}">Book your next game <x-booking-icon name="chevron-right" /></a>
                    <a class="site-button site-outline" href="{{ \App\Services\VenueContext::url('open-play') }}">Join Open Play</a>
                </div>
                @if($venue->address || $venue->map_url)
                    <p class="site-hero-location"><x-booking-icon name="map-pin" />
                        @if($venue->map_url)<a href="{{ $venue->map_url }}" target="_blank" rel="noopener noreferrer">{{ $venue->address ?: 'Get directions' }}</a>
                        @else{{ $venue->address }}@endif
                    </p>
                @endif
            </div>
            @if($media->has('cover'))
                <div class="site-hero-photo"><img src="{{ \App\Services\VenueContext::url('book.image',['kind'=>'cover','version'=>$media['cover']->version]) }}" alt="{{ $venue->name }} venue cover" fetchpriority="high"></div>
            @endif
            <div class="site-hero-facts">
                <div><x-booking-icon name="layout-grid" /><span><strong>{{ $courts->count() }}</strong> active {{ $courts->count() === 1 ? 'court' : 'courts' }}</span></div>
                @if($rates['minimum'] !== null)<div><x-booking-icon name="clock" /><span>Rates from <strong>₱{{ number_format($rates['minimum']/100,2) }}/hr</strong></span></div>@endif
                <div><x-booking-icon name="calendar-days" /><span>Book online up to <strong>{{ $venue->advance_days }} {{ $venue->advance_days === 1 ? 'day' : 'days' }}</strong> ahead</span></div>
            </div>
        </section>

        <section class="site-container site-section" id="play">
            <div class="site-section-heading"><p class="site-eyebrow">Choose how to play</p><h2>Find your next game</h2><p>Reserve a court for your group or join a scheduled Open Play session.</p></div>
            <div class="site-option-grid">
                <article class="site-option">
                    <span class="site-icon"><x-booking-icon name="layout-grid" /></span>
                    <h3>Court reservations</h3>
                    <p>Your court. Your friends. Your time. Choose a date and available court that fits your plans.</p>
                    @if($rates['minimum'] !== null)<strong class="site-price">From ₱{{ number_format($rates['minimum']/100,2) }} <small>/ hour</small></strong>@endif
                    <a class="site-button site-primary" href="{{ \App\Services\VenueContext::url('book',['step'=>1]) }}">Book a court</a>
                </article>
                <article class="site-option">
                    <span class="site-icon"><x-booking-icon name="users" /></span>
                    <h3>Open Play</h3>
                    <p>Come alone or with friends. Register for a player spot and meet others who enjoy the game.</p>
                    @if($sessions->isNotEmpty())<strong class="site-price">From ₱{{ number_format($sessions->min('price')/100,2) }} <small>/ player</small></strong>@endif
                    <a class="site-button site-outline" href="{{ \App\Services\VenueContext::url('open-play') }}">View Open Play</a>
                </article>
            </div>
        </section>

        <section class="site-band" id="schedule">
            <div class="site-container site-section site-two-column">
                <div class="site-section-heading"><p class="site-eyebrow">Plan your visit</p><h2>Opening hours</h2><p>Pick a day that works for you. Check the booking page for live court availability.</p><a href="{{ \App\Services\VenueContext::url('book') }}" class="site-text-link">Check available times <x-booking-icon name="chevron-right" /></a></div>
                <div class="site-data-panel">
                    @foreach($openingHours as $time => $days)
                        <div class="site-data-row"><strong>{{ \App\Services\VenueWebsite::dayLabel($days) }}</strong><span>{{ $time }}</span></div>
                    @endforeach
                    <p class="site-footnote">All times are local to the venue.</p>
                    @if($closures->isNotEmpty())
                        <h3>Upcoming closures</h3>
                        @foreach($closures as $closure)
                            <div class="site-data-row"><strong>{{ \Carbon\CarbonImmutable::parse($closure->date)->format('M j, Y') }}</strong><span>{{ $closure->reason }}</span></div>
                        @endforeach
                    @endif
                </div>
            </div>
        </section>

        <section class="site-container site-section" id="rates">
            <div class="site-section-heading"><p class="site-eyebrow">Court pricing</p><h2>Know your rates before you play</h2><p>Choose the time that fits your budget. The total for your selected duration will be shown before you confirm.</p></div>
            <div class="site-rates-grid">
                @forelse($rates['groups'] as $group)
                    <article class="site-data-panel site-rate-card">
                        <h3>{{ implode(', ', $group['courts']) }}</h3>
                        @forelse($group['windows'] as $window)
                            <div class="site-rate-row"><div><strong>{{ \App\Services\VenueWebsite::dayLabel($window['days']) }}</strong><small>{{ \App\Services\VenueWebsite::timeLabel(\App\Services\VenueWebsite::clock($window['start'])) }} – {{ \App\Services\VenueWebsite::timeLabel(\App\Services\VenueWebsite::clock($window['end'])) }}</small></div><strong>₱{{ number_format($window['rate']/100,2) }}<small>/hr</small></strong></div>
                        @empty<p class="site-footnote">No regular opening hours configured.</p>@endforelse
                    </article>
                @empty
                    <div class="site-data-panel">Court rates are not available yet.</div>
                @endforelse
            </div>
            <p class="site-footnote site-rates-note">Rates are for one court. Holiday closures and actual availability are checked at booking.</p>
            <a class="site-button site-primary" href="{{ \App\Services\VenueContext::url('book') }}">Book now <x-booking-icon name="chevron-right" /></a>
        </section>

        <section class="site-band" id="open-play">
            <div class="site-container site-section">
                <div class="site-section-heading"><p class="site-eyebrow">Play together</p><h2>Upcoming Open Play</h2><p>Find a session that suits your schedule and skill level.</p></div>
                <div class="site-session-grid">
                    @forelse($sessions as $session)
                        @php($localStart = $session->starts_at->setTimezone($venue->timezone))
                        <article class="site-data-panel site-session">
                            <div class="site-session-header"><h3>{{ $session->name }}</h3><span class="site-availability">{{ $session->spotsLeft() ? $session->spotsLeft().' '.($session->spotsLeft() === 1 ? 'spot' : 'spots').' left' : 'Full' }}</span></div>
                            <p><x-booking-icon name="calendar-days" /> {{ $localStart->format('D, M j, Y') }}</p>
                            <p><x-booking-icon name="clock" /> {{ $localStart->format('g:i A') }} – {{ $session->ends_at->setTimezone($venue->timezone)->format('g:i A') }}</p>
                            <p><x-booking-icon name="users" /> {{ $session->levelLabel() }} · {{ $session->courts_count }} {{ $session->courts_count === 1 ? 'court' : 'courts' }}</p>
                            <progress value="{{ $session->capacity-$session->spotsLeft() }}" max="{{ $session->capacity }}" aria-label="Player spots taken"></progress>
                            <small>{{ $session->capacity-$session->spotsLeft() }} of {{ $session->capacity }} spots taken</small>
                            <div class="site-session-footer"><strong>₱{{ number_format($session->price/100,2) }}<small>/player</small></strong>
                                @if($canJoin && $session->spotsLeft())
                                    <a class="site-button site-primary" href="{{ \App\Services\VenueContext::url('open-play',['date'=>$localStart->toDateString(),'session'=>$session->id]) }}">Join session</a>
                                @else<span class="site-footnote">{{ $session->spotsLeft() ? 'Contact venue to join' : 'Session full' }}</span>@endif
                            </div>
                        </article>
                    @empty
                        <div class="site-data-panel site-empty"><x-booking-icon name="calendar-days" /><h3>More games coming soon</h3><p>No upcoming sessions are listed. Check back for new Open Play dates.</p><a class="site-button site-outline" href="{{ \App\Services\VenueContext::url('open-play') }}">View Open Play</a></div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="site-container site-section" id="facilities">
            <div class="site-section-heading"><p class="site-eyebrow">Our venue</p><h2>Enjoy your time on the court</h2>
                @if(!empty($content['introduction']))<p class="site-preline">{{ $content['introduction'] }}</p>@else<p>Get together for your next pickleball game at {{ $venue->name }}.</p>@endif
            </div>
            <div class="site-feature-grid">
                @if($courts->isNotEmpty())<div class="site-feature"><x-booking-icon name="layout-grid" /><strong>{{ $courts->count() }} pickleball {{ $courts->count() === 1 ? 'court' : 'courts' }}</strong></div>@endif
                @foreach(['indoor'=>'Indoor','outdoor'=>'Outdoor'] as $type => $label)
                    @php($count = $courts->where('surface_type',$type)->count())
                    @if($count)<div class="site-feature"><x-booking-icon name="layout-grid" /><strong>{{ $count }} {{ strtolower($label) }} {{ $count === 1 ? 'court' : 'courts' }}</strong></div>@endif
                @endforeach
                @foreach($facilities as $feature)
                    <div class="site-feature"><x-booking-icon name="circle-check" /><strong>{{ $feature }}</strong></div>
                @endforeach
            </div>
        </section>

        @if(count($galleryPhotos))
            <section class="site-band" id="gallery">
                <div class="site-container site-section">
                    <div class="site-section-heading">
                        <p class="site-eyebrow">Photo gallery</p>
                        <h2>Good games. Great memories.</h2>
                        <p>Enjoy the game, meet new friends and make every match count.</p>
                        @if($isDemoGallery)
                            <p class="site-gallery-note">AI-generated demo images for illustration only. These are not photos of this venue.</p>
                        @endif
                    </div>
                    <div class="site-gallery {{ count($galleryPhotos) === 5 ? 'site-gallery-five' : '' }}">
                        @foreach($galleryPhotos as $photo)
                            <a class="site-gallery-tile" href="{{ $photo['url'] }}" data-gallery-image aria-label="Enlarge: {{ $photo['alt'] }}">
                                <img src="{{ $photo['url'] }}" alt="{{ $photo['alt'] }}" loading="lazy" decoding="async" width="1200" height="900">
                                <span class="site-gallery-zoom" aria-hidden="true">+</span>
                            </a>
                        @endforeach
                    </div>
                    <a class="site-button site-primary site-gallery-book" href="{{ \App\Services\VenueContext::url('book') }}">Book your next game <x-booking-icon name="chevron-right" /></a>
                </div>
                <dialog class="site-gallery-dialog" id="gallery-dialog" aria-labelledby="gallery-dialog-title">
                    <div class="site-gallery-dialog-top">
                        <h2 id="gallery-dialog-title">{{ $isDemoGallery ? 'Illustrative demo gallery' : 'Venue gallery' }}</h2>
                        <button type="button" data-gallery-close aria-label="Close gallery"><x-booking-icon name="x" /></button>
                    </div>
                    <figure>
                        <img data-gallery-large alt="">
                        <figcaption data-gallery-caption aria-live="polite"></figcaption>
                    </figure>
                    <div class="site-gallery-controls">
                        <button type="button" data-gallery-prev aria-label="Previous photo"><x-booking-icon name="chevron-left" /></button>
                        <span data-gallery-count></span>
                        <button type="button" data-gallery-next aria-label="Next photo"><x-booking-icon name="chevron-right" /></button>
                    </div>
                </dialog>
            </section>
        @endif

        <section class="site-band" id="how-to-book">
            <div class="site-container site-section">
                <div class="site-section-heading"><p class="site-eyebrow">Simple online booking</p><h2>Book in a few easy steps</h2></div>
                <div class="site-step-grid">
                    <div><span>01</span><h3>Pick your day</h3><p>Choose a date, time and duration for your game.</p></div>
                    <div><span>02</span><h3>Choose your court</h3><p>See available courts and enter your booking details.</p></div>
                    <div><span>03</span><h3>{{ $venue->qr_payments_enabled ? 'Pay and confirm' : 'Confirm your booking' }}</h3>
                        <p>{{ $venue->qr_payments_enabled ? 'Pay using the QR code and send your receipt in Messenger. The venue confirms your payment.' : 'Review your booking and pay at the venue.' }}</p></div>
                </div>
                <a class="site-button site-primary" href="{{ \App\Services\VenueContext::url('book') }}">Start booking</a>
            </div>
        </section>

        <section class="site-container site-section" id="faq">
            <div class="site-section-heading"><p class="site-eyebrow">Good to know</p><h2>Frequently asked questions</h2><p>Here is what you need to know before your next game.</p></div>
            <div class="site-faq">
                <details><summary>How do I book a court?</summary><p>Click <a href="{{ \App\Services\VenueContext::url('book') }}">Book Now</a> and choose Book a court. Pick your date, how long you want to play, your start time and your court. Enter your details and follow the payment steps.</p></details>
                <details><summary>Can I join Open Play alone?</summary><p>Yes. Each registration is for one player. Choose an <a href="{{ \App\Services\VenueContext::url('open-play') }}">Open Play session</a> and meet other players. @if(!$canJoin) Contact the venue to ask about joining while online registration is unavailable.@endif</p></details>
                <details><summary>Can beginners join Open Play?</summary><p>Look for sessions marked Beginner or All skill levels. Check the session details to find the right game for you.</p></details>
                <details><summary>How do I know my booking is confirmed?</summary>
                    @if($canJoin)
                        <p>Pay using the QR code and send your receipt in Messenger. Wait for the venue to check your payment. Your confirmation page will show when you are booked.</p>
                    @elseif($venue->qr_payments_enabled)
                        <p>Contact the venue before booking. Online QR payment setup is not ready yet.</p>
                    @else
                        <p>For a court booking, check your confirmation page after you submit your details. Pay at the venue. Contact the venue to ask about Open Play.</p>
                    @endif
                </details>
                <details><summary>What if my preferred time or session is full?</summary><p>Choose another available court, time or Open Play session. You can check live availability on the <a href="{{ \App\Services\VenueContext::url('book') }}">booking page</a>.</p></details>
                <details><summary>How much does it cost to play?</summary><p>Check our <a href="#rates">court rates</a> and <a href="#open-play">Open Play schedule</a> for prices. You will see your total before you confirm your booking.</p></details>
                <details><summary>How long can I book a court?</summary><p>Available durations: {{ collect($venue->durations())->map(fn ($minutes) => \App\Models\Venue::durationLabel($minutes))->join(', ') }}. Choose your duration when booking.</p></details>
                <details><summary>How far ahead can I book?</summary><p>{{ $venue->advance_days === 0 ? 'You can book for today.' : 'You can book up to '.$venue->advance_days.' days ahead.' }} @if($venue->minimum_notice) Please book at least {{ $venue->minimum_notice }} minutes before play.@endif</p></details>
                <details><summary>Can I change or cancel my booking?</summary>
                    @if($venue->cancellation_policy)<p class="site-preline">{{ $venue->cancellation_policy }}</p>@else<p>Contact the venue to ask about changing or cancelling your booking.</p>@endif
                </details>
                @foreach($content['faqs'] ?? [] as $faq)
                    <details><summary>{{ $faq['question'] }}</summary><p class="site-preline">{{ $faq['answer'] }}</p></details>
                @endforeach
            </div>
        </section>

        <section class="site-band" id="contact">
            <div class="site-container site-section site-two-column">
                <div class="site-section-heading"><p class="site-eyebrow">Come visit us</p><h2>{{ $venue->name }}</h2><p>{{ $venue->address ?: 'See you on the court.' }}</p></div>
                <div class="site-contact">
                    @if($venue->map_url)<a class="site-button site-outline" href="{{ $venue->map_url }}" target="_blank" rel="noopener noreferrer"><x-booking-icon name="map-pin" /> Get directions</a>@endif
                    @if($venue->phone)<a class="site-button site-outline" href="tel:{{ preg_replace('/[^+0-9]/','',$venue->phone) }}"><x-booking-icon name="phone" /> {{ $venue->phone }}</a>@endif
                    @if($venue->messenger_url || $venue->payment_messenger_url)<a class="site-button site-outline" href="{{ $venue->messenger_url ?: $venue->payment_messenger_url }}" target="_blank" rel="noopener noreferrer"><x-booking-icon name="message-circle" /> Message us</a>@endif
                </div>
            </div>
        </section>

        <section class="site-container site-section site-final">
            <div><h2>Ready to play?</h2><p>Make time for your next game.</p></div>
            <a class="site-button site-primary" href="{{ \App\Services\VenueContext::url('book') }}">Book your next game <x-booking-icon name="chevron-right" /></a>
        </section>
    </main>
    <footer class="site-footer"><div class="site-container"><span>{{ $venue->name }}</span><span>Powered by Pickle Lite · <a href="{{ \App\Services\VenueContext::onBooking() || \App\Services\VenueContext::onApp() ? route('app.home') : (auth()->check() ? route('staff.calendar') : route('login')) }}">Staff sign in</a></span></div></footer>
    <div class="site-mobile-cta"><a class="site-button site-primary" href="{{ \App\Services\VenueContext::url('book') }}">Book now <x-booking-icon name="chevron-right" /></a></div>
    <script src="{{ asset('landing-navigation.js') }}?v=2" defer></script>
</body>
</html>
