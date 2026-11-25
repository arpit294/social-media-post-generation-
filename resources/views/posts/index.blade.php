@extends('layouts.app')

@section('title', 'Posts')
@section('kicker', 'Studio')
@section('heading', 'Posts')

@section('content')
    @if ($posts->isEmpty())
        <p class="meta">No posts yet. Write one from the dashboard or from a chat.</p>
    @else
        <div class="post-catalog">
            @foreach ($posts as $post)
                <a href="{{ route('posts.show', $post) }}">
                    <strong>{{ $post->listTitle() }}</strong>
                    <span class="meta">{{ $post->finishedLabel() }}@if ($post->durationLabel()) · took {{ $post->durationLabel() }}@endif</span>
                    <p class="excerpt">{{ $post->preview() }}</p>
                </a>
            @endforeach
        </div>
    @endif
@endsection
