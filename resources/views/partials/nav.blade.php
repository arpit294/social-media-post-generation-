<nav class="side-nav">
    <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])>
        <span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                <rect x="14" y="14" width="7" height="7" rx="1"></rect>
            </svg>
        </span>
        Dashboard
    </a>
    <a href="{{ route('knowledge.edit') }}" @class(['active' => request()->routeIs('knowledge.edit')])>
        <span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5z"></path>
                <path d="M4 5.5A2.5 2.5 0 0 1 6.5 8H20"></path>
            </svg>
        </span>
        Knowledge base
    </a>
    <div class="nav-row">
        <a href="{{ route('posts.index') }}" @class(['active' => request()->routeIs('posts.*')])>
            <span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path d="M7 3.5h7.5L19 8v12.5H7z"></path>
                    <path d="M14.5 3.5V8H19"></path>
                    <path d="M9.5 12.5h5"></path>
                    <path d="M9.5 16h5"></path>
                </svg>
            </span>
            Posts
        </a>
        <button class="nav-toggle" type="button" aria-expanded="{{ request()->routeIs('posts.*') ? 'true' : 'false' }}" aria-controls="recent-posts" aria-label="Recent posts">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <path d="M6 9l6 6 6-6"></path>
            </svg>
        </button>
    </div>
    <div id="recent-posts" class="nav-sub posts-list" @unless (request()->routeIs('posts.*')) hidden @endunless>
        @forelse ($sidebarPosts as $post)
            <a href="{{ route('posts.show', $post) }}" @class(['active' => request()->routeIs('posts.show') && (int) optional(request()->route('generation'))->id === $post->id]) title="{{ $post->listTitle() }}">
                <span class="post-title">{{ $post->listTitle() }}</span>
                <span class="post-meta">{{ $post->finishedLabel() }}@if ($post->durationLabel()) · took {{ $post->durationLabel() }}@endif</span>
            </a>
        @empty
            <p class="recents-empty">No posts yet</p>
        @endforelse
    </div>
    <div class="nav-row">
        <a href="{{ route('chat.show') }}" @class(['active' => request()->routeIs('chat.*')])>
            <span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path d="M5 6.5A2.5 2.5 0 0 1 7.5 4h9A2.5 2.5 0 0 1 19 6.5v7A2.5 2.5 0 0 1 16.5 16H9l-4 3.5V6.5z"></path>
                </svg>
            </span>
            Chat
        </a>
        <button class="nav-toggle" type="button" aria-expanded="{{ request()->routeIs('chat.*') ? 'true' : 'false' }}" aria-controls="recent-chats" aria-label="Recent chats">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <path d="M6 9l6 6 6-6"></path>
            </svg>
        </button>
    </div>
    <div id="recent-chats" class="nav-sub" @unless (request()->routeIs('chat.*')) hidden @endunless>
        <form method="POST" action="{{ route('chat.sessions.store') }}">
            @csrf
            <button class="sidebar-new" type="submit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path d="M12 20h9"></path>
                    <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4z"></path>
                </svg>
                New chat
            </button>
        </form>
        @forelse ($sidebarChats as $item)
            <a href="{{ route('chat.show', $item) }}" @class(['active' => $openChatId === $item->id]) title="{{ $item->title }}">{{ $item->title }}</a>
        @empty
            <p class="recents-empty">No chats yet</p>
        @endforelse
    </div>
    <a href="{{ route('settings.edit') }}" @class(['active' => request()->routeIs('settings.*')])>
        <span>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <circle cx="12" cy="12" r="3"></circle>
                <path d="M12 3v2.2M12 18.8V21M3 12h2.2M18.8 12H21M5.6 5.6l1.6 1.6M16.8 16.8l1.6 1.6M18.4 5.6l-1.6 1.6M7.2 16.8l-1.6 1.6"></path>
            </svg>
        </span>
        Settings
    </a>
</nav>
<script>
    document.querySelectorAll('.nav-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            const panel = document.getElementById(button.getAttribute('aria-controls'));
            const open = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', open ? 'false' : 'true');
            if (panel) {
                panel.hidden = open;
            }
        });
    });
</script>
