@extends('layouts.app')

@section('title', 'Post')
@section('kicker', 'Studio')
@section('heading', 'Post')

@section('content')
    <section class="panel">
        <p><a class="post-back" href="{{ route('posts.index') }}">All posts</a></p>
        <p class="meta">{{ $generation->finishedLabel() }}@if ($generation->durationLabel()) · took {{ $generation->durationLabel() }}@endif</p>
        <pre class="post">{{ $generation->caption() }}</pre>
        @if ($generation->imagePrompt())
            <h2>Image</h2>
            <p class="meta">Paste this into an image generator. Text-free shop-floor artwork, with empty space at the top and bottom.</p>
            <pre class="post">{{ $generation->imagePrompt() }}</pre>
        @endif
    </section>
@endsection
