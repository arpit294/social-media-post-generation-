@extends('layouts.app')

@section('title', 'Dashboard')
@section('kicker', 'Studio')
@section('heading', 'Dashboard')

@section('content')
    <section class="stats">
        <article class="stat">
            <span>Account</span>
            <strong>{{ auth()->user()->name }}</strong>
            <p class="meta">{{ auth()->user()->email }}</p>
        </article>
        <article class="stat">
            <span>{{ auth()->user()->writerName() }}</span>
            <strong>{{ $model }}</strong>
            <p class="meta">Writes posts from the manufacturing knowledge base.</p>
        </article>
        <article class="stat">
            <span>Company</span>
            <strong>Webexpert Infotech</strong>
            <p class="meta">Custom software for manufacturers.</p>
        </article>
    </section>
    <div class="work">
        <form id="post-form" class="panel" method="POST" action="{{ route('posts.store') }}">
            @csrf
            <h2>New post</h2>
            <p class="meta">One prompt. The knowledge base writes the caption and the image brief.</p>
            @if ($errors->any())
                <div class="error">{{ $errors->first() }}</div>
            @endif
            <div class="row">
                <div>
                    <label for="platform">Platform</label>
                    <select id="platform" name="platform">
                        @foreach (['LinkedIn', 'Facebook', 'Instagram'] as $platform)
                            <option value="{{ $platform }}" @selected(old('platform', 'LinkedIn') === $platform)>{{ $platform }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="language">Language</label>
                    <select id="language" name="language">
                        @foreach (['English', 'Gujarati-mix'] as $language)
                            <option value="{{ $language }}" @selected(old('language', 'English') === $language)>{{ $language }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <label for="prompt">Prompt</label>
            <textarea id="prompt" class="prompt" name="prompt" placeholder="Party changed the size on WhatsApp, but the operator still has the old job card. Or type: generate post" required>{{ old('prompt') }}</textarea>
            <button type="submit" @disabled($running)>Write post</button>
            <p id="wait-note" class="meta">On this laptop a post often takes 8 to 15 minutes. Other pages still open while it writes.</p>
        </form>
        <section class="panel result">
            <h2>Post</h2>
            <div id="timer" @if (! $running) hidden @endif>
                <p class="meta">Time so far</p>
                <p id="timer-value" class="timer-value">{{ $running ? $running->clock() : '0:00' }}</p>
                <p class="meta">This clock keeps the time. Knowledge base and chat still open while the post is written.</p>
            </div>
            @if ($running)
            @elseif ($failure)
                <div class="error">{{ $failure }}</div>
            @elseif (is_array($generated) && filled(data_get($generated, 'data.post_text') ?? data_get($generated, 'raw')))
                <p class="meta">@if (! empty($generated['model'])){{ $generated['model'] }}@endif @if (! empty($generated['elapsed_seconds']))@if (! empty($generated['model'])) · @endif created in {{ sprintf('%d:%02d', intdiv((int) $generated['elapsed_seconds'], 60), ((int) $generated['elapsed_seconds']) % 60) }}@endif</p>
                <pre class="post">{{ data_get($generated, 'data.post_text') ?? data_get($generated, 'raw') }}</pre>
                @if (filled(data_get($generated, 'data.image_prompt')))
                    <h2>Image</h2>
                    <p class="meta">Paste this into an image generator. Text-free shop-floor artwork, with empty space at the top and bottom.</p>
                    <pre class="post">{{ data_get($generated, 'data.image_prompt') }}</pre>
                @endif
            @else
                <p id="post-idle" class="meta">Enter one prompt. A shop-floor moment becomes that post. “generate post” picks the next topic from the knowledge base.</p>
            @endif
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('post-form').addEventListener('submit', function () {
            const button = this.querySelector('button[type="submit"]');
            button.disabled = true;
            button.textContent = 'Writing the post…';
            const timer = document.getElementById('timer');
            const value = document.getElementById('timer-value');
            const idle = document.getElementById('post-idle');
                const note = document.getElementById('wait-note');
                if (timer) timer.hidden = false;
                if (idle) idle.hidden = true;
                if (note) note.hidden = true;
            const started = Date.now();
            const tick = function () {
                const seconds = Math.floor((Date.now() - started) / 1000);
                value.textContent = Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
            };
            tick();
            window.setInterval(tick, 1000);
        });
    </script>
@endpush
@include('partials.running-clock')
