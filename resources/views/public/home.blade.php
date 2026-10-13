@extends('layouts.public', ['module' => 'home'])

@section('title', 'LaraBai Baristro · Tarlac City')
@section('description', 'LaraBai Baristro in Tarlac City: espresso drinks, milk tea, silog meals and fresh pastries. Open daily 7 AM to 10 PM.')

@section('content')
    <nav class="nav" aria-label="Main">
        <div class="container">
            <a class="brand" href="{{ route('home') }}"><span class="brand-mark">LB</span><span class="brand-name">{{ config('cafe.name') }}</span></a>
            <ul>
                <li><a href="#about">About</a></li>
                <li><a href="#favorites">Favorites</a></li>
                <li><a href="#how">Order at your table</a></li>
                <li><a href="#visit">Visit</a></li>
            </ul>
            <x-theme-toggle />
        </div>
    </nav>

    <header class="hero">
        <div class="container">
            <div>
                <div class="eyebrow">Tarlac City · Since 2022</div>
                <h1>Good coffee.<br>Honest food.<br><span>Right here in Tarlac.</span></h1>
                <p class="lead">Espresso pulled to order, brown sugar milk tea, all-day silog plates and pastries baked every morning. Come for a quick latte or stay the whole afternoon.</p>
                <div class="hero-actions">
                    <a class="btn btn-primary" href="#visit">Find us</a>
                    <a class="btn btn-ghost" href="#favorites">See our favorites</a>
                </div>
                <div class="hero-facts">
                    <span><b>Open daily</b>7 AM – 10 PM</span>
                    <span><b>Free Wi-Fi</b>and power outlets</span>
                    <span><b>GCash &amp; Maya</b>or cash</span>
                </div>
            </div>

            {{-- What's in the glass: the first cup is drawn here; the tabs switch it (resources/js/modules/public.js) --}}
            @php($first = $cups[0])
            <figure class="cup-card" aria-labelledby="cupName" style="margin:0" data-cups="{{ json_encode($cups) }}">
                <div class="cup-tabs" role="tablist" aria-label="Best sellers">
                    @foreach ($cups as $i => $cup)
                        <button type="button" role="tab" id="tab-{{ $cup['code'] }}" aria-selected="{{ $i === 0 ? 'true' : 'false' }}" tabindex="{{ $i === 0 ? 0 : -1 }}" data-i="{{ $i }}">{{ $cup['tab'] }}</button>
                    @endforeach
                </div>
                <div class="cup-stage">
                    <div class="glass" aria-hidden="true">
                        @foreach (range(0, 3) as $k)
                            @php($layer = $first['layers'][$k] ?? null)
                            <span @class(['layer', $layer[4] ?? '' => $layer && $layer[4]]) style="height:{{ $layer ? $layer[2].'%' : '0' }};background-color:{{ $layer[3] ?? 'transparent' }}"></span>
                        @endforeach
                    </div>
                    <ul class="legend" aria-label="What's in the glass">
                        @foreach ($first['layers'] as $layer)
                            <li><i style="background:{{ $layer[3] }}"></i><span>{{ $layer[0] }}</span><span>{{ $layer[1] }}</span></li>
                        @endforeach
                    </ul>
                </div>
                <figcaption class="cup-meta">
                    <div><h2 id="cupName">{{ $first['name'] }}</h2><p id="cupNote">{{ $first['note'] }}</p></div>
                    <span class="price" id="cupPrice">{{ peso($first['price'], false) }}</span>
                </figcaption>
            </figure>
        </div>
    </header>

    <main>
        <section class="block" id="about">
            <div class="container about">
                <div>
                    <div class="block-head">
                        <div class="eyebrow">Our story</div>
                        <h2>A neighborhood café for students, workers and barkadas</h2>
                    </div>
                    <p>LaraBai Baristro started in 2022 as a small coffee counter near the city center. Today we have {{ $stats['tables'] }} tables, a full kitchen and a team of nine who know most of our regulars by name and by order.</p>
                    <p>We use Benguet arabica for our espresso, Davao cacao for our chocolate drinks and fresh milk delivered every morning. Our rice meals are cooked the way we like them at home: generous, garlicky and served with atchara on the side.</p>
                </div>
                <div class="about-stats">
                    <div><strong>{{ $stats['items'] }}</strong><span>items on the menu</span></div>
                    <div><strong>{{ $stats['tables'] }}</strong><span>tables, {{ $stats['seats'] }} seats</span></div>
                    <div><strong>7 AM</strong><span>first shot pulled every day</span></div>
                    <div><strong>4.8 ★</strong><span>average Facebook rating</span></div>
                </div>
            </div>
        </section>

        <section class="block favs-band" id="favorites">
            <div class="container">
                <div class="block-head">
                    <div class="eyebrow">Customer favorites</div>
                    <h2>What our regulars keep ordering</h2>
                    <p>Our best sellers and the newest additions to the menu.</p>
                </div>
                <div class="favs">
                    @foreach ($favorites as $item)
                        @php([$dark, $light] = $item['tone'])
                        <article class="fav">
                            <div class="fav-art">
                                <span class="tags">
                                    @if ($item['is_best_seller'])<span class="chip chip-gold">Best seller</span>@endif
                                    @if ($item['is_new'])<span class="chip chip-green">New</span>@endif
                                </span>
                                @if (! empty($item['image_url']))
                                    <img class="art-photo" src="{{ $item['image_url'] }}" alt="{{ $item['name'] }}" loading="lazy">
                                @elseif ($item['is_drink'])
                                    <span class="mini-glass" aria-hidden="true"><i style="height:24%;background:{{ $light }}"></i><i style="height:46%;background:linear-gradient(to top, {{ $light }}, {{ $dark }})"></i><i style="height:12%;background:{{ $light }};opacity:.55"></i></span>
                                @else
                                    <span class="plate" aria-hidden="true"><i style="background:radial-gradient(circle at 35% 35%, {{ $light }}, {{ $dark }})"></i></span>
                                @endif
                            </div>
                            <div class="fav-body">
                                <h3>{{ $item['name'] }}</h3>
                                <p>{{ $item['description'] }}</p>
                                <span class="price">{{ peso($item['price'], false) }}</span>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="block" id="how">
            <div class="container">
                <div class="block-head">
                    <div class="eyebrow">Order at your table</div>
                    <h2>No more waiting in line to order</h2>
                    <p>Every table has its own QR code. Order and pay from your phone, and we bring everything to you.</p>
                </div>
                <div class="steps">
                    <div><h3>Grab a table</h3><p>Find a seat. Each table has a QR code standee with its table number.</p></div>
                    <div><h3>Scan and order</h3><p>Scan the code with your phone camera. Choose your size, sugar level and add-ons like oat milk or an extra shot.</p></div>
                    <div><h3>Pay your way</h3><p>Pay on your phone or at the counter, then watch your order go from Preparing to Ready.</p>
                        <div class="pay-row"><span class="chip chip-gcash">GCash</span><span class="chip chip-green">Maya</span><span class="chip chip-gold">Cash</span></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="block" id="visit" style="padding-top:0">
            <div class="container visit">
                <div>
                    <div class="block-head">
                        <div class="eyebrow">Visit</div>
                        <h2>Drop by anytime</h2>
                    </div>
                    <dl>
                        <dt>Address</dt><dd>{{ config('cafe.address') }}</dd>
                        <dt>Hours</dt><dd>{{ config('cafe.hours') }}</dd>
                        <dt>Phone</dt><dd>{{ config('cafe.phone') }}</dd>
                        <dt>Facebook</dt><dd>{{ config('cafe.facebook') }}</dd>
                        <dt>Discounts</dt><dd>{{ config('cafe.senior_pwd.percent') }}% off for senior citizens and PWDs. Show your ID at the counter.</dd>
                    </dl>
                </div>
                <div class="map" role="img" aria-label="Map: LaraBai Baristro on F. Tañedo Street, San Nicolas, Tarlac City">
                    <span class="street v">F. Tañedo St.</span>
                    <span class="street h">Zamora St.</span>
                    <span class="street d">MacArthur Hwy</span>
                    <span class="pin"><b>LaraBai Baristro</b><i></i><span class="dot"></span></span>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <span class="brand"><span class="brand-mark">LB</span><span class="brand-name">{{ config('cafe.name') }}</span></span>
            <small>© {{ now()->year }} {{ config('cafe.name') }} · A fictional café for a case study</small>
        </div>
    </footer>
@endsection
