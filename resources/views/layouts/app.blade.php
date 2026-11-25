<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Webexpert')</title>
    <style>
        :root {
            color-scheme: light;
            --navy: #0B2545;
            --navy-2: #12325c;
            --teal: #13A89E;
            --teal-deep: #0e7f77;
            --paper: #f3f1ec;
            --card: #fffcf8;
            --line: #ddd6cb;
            --ink: #142033;
            --muted: #5e6a78;
            --danger-bg: #fff4f2;
            --danger-line: #e7b2a8;
            --danger: #7a2618;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", Candara, sans-serif;
            background: var(--paper);
            color: var(--ink);
        }
        .shell { display: grid; grid-template-columns: 272px 1fr; min-height: 100vh; }
        aside {
            background: var(--navy);
            color: white;
            padding: 22px 12px 16px;
            display: flex;
            flex-direction: column;
            gap: 18px;
            height: 100vh;
            position: sticky;
            top: 0;
            overflow-x: hidden;
            overflow-y: auto;
        }
        .brand { display: flex; gap: 12px; align-items: center; padding: 0 8px; }
        .mark {
            width: 36px;
            height: 36px;
            border: 1px solid rgba(255,255,255,.35);
            display: grid;
            place-items: center;
            font-family: Georgia, "Palatino Linotype", serif;
            color: var(--teal);
        }
        .brand strong { display: block; font-weight: 600; letter-spacing: .02em; }
        .brand small { color: #b7c3d1; font-size: 12px; }
        .side-nav { display: flex; flex-direction: column; gap: 4px; min-height: 0; }
        .nav-row { display: flex; align-items: center; }
        .nav-row > a { flex: 1; min-width: 0; }
        .nav-sub { display: flex; flex-direction: column; gap: 2px; max-height: 220px; margin: 0 0 6px 18px; overflow: auto; }
        .nav-sub[hidden] { display: none; }
        .nav-sub a { padding: 6px 10px; font-size: 13px; border-left-color: transparent; }
        .side-nav form { margin: 0 0 4px 18px; max-width: calc(100% - 18px); }
        .nav-sub form { margin: 0 0 4px; max-width: none; }
        .side-nav a {
            color: #d5deea;
            text-decoration: none;
            padding: 10px 12px;
            display: flex;
            gap: 10px;
            align-items: center;
            border-left: 2px solid transparent;
        }
        .side-nav > a > span:first-child { color: #8ea0b5; width: 18px; height: 18px; display: grid; place-items: center; flex: none; }
        .side-nav a svg { width: 18px; height: 18px; }
        .side-nav a.active, .side-nav a.active span { color: white; }
        .side-nav a.active { background: var(--navy-2); border-left-color: var(--teal); }
        .recents { flex: 0 0 auto; min-height: 0; display: flex; flex-direction: column; }
        .chats-pane { flex: 1 0 auto; }
        button.sidebar-new {
            width: 100%;
            background: transparent;
            color: #d5deea;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            text-align: left;
        }
        button.sidebar-new svg { width: 18px; height: 18px; flex: none; }
        button.sidebar-new:hover { background: rgba(255,255,255,.08); color: white; }
        .recents-label { margin: 16px 12px 6px; color: #8ea0b5; font-size: 12px; }
        a.posts-heading { display: block; text-decoration: none; letter-spacing: .08em; text-transform: uppercase; }
        a.posts-heading:hover, a.posts-heading.active { color: white; }
        .post-catalog { display: grid; gap: 12px; }
        .post-catalog a {
            display: block;
            background: var(--card);
            border: 1px solid var(--line);
            padding: 16px 18px;
            text-decoration: none;
            color: inherit;
        }
        .post-catalog a:hover { border-color: var(--teal); }
        .post-catalog strong { display: block; margin-bottom: 4px; font-size: 16px; }
        .post-catalog .excerpt { margin: 10px 0 0; line-height: 1.5; }
        .post-back { color: var(--teal-deep); font-size: 13px; text-decoration: none; }
        .recents-list { overflow: auto; min-height: 0; display: flex; flex-direction: column; gap: 2px; }
        .posts-list { max-height: 210px; }
        .recents-list a {
            display: block;
            padding: 8px 12px;
            color: #d5deea;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.35;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .posts-list a {
            flex: none;
            flex-direction: column;
            align-items: stretch;
            gap: 2px;
            white-space: normal;
            text-overflow: unset;
            padding: 7px 10px;
        }
        .post-title {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: white;
            font-size: 13px;
        }
        .post-meta {
            display: block;
            margin-top: 2px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #8ea0b5;
            font-size: 11px;
            line-height: 1.3;
        }
        .posts-list a.active .post-meta { color: #d5deea; }
        .recents-list a:hover { background: rgba(255,255,255,.06); color: white; }
        .recents-list a.active { background: rgba(255,255,255,.12); color: white; }
        .recents-empty { margin: 0 12px; color: #8ea0b5; font-size: 13px; }
        .side-foot { margin-top: auto; padding: 0 8px; flex: none; }
        .who { margin: 0 0 12px; color: #d5deea; font-size: 14px; }
        .who strong { color: white; font-weight: 600; }
        button, .btn {
            font: inherit;
            cursor: pointer;
            border: 0;
            background: var(--teal);
            color: white;
            padding: 11px 16px;
        }
        button:hover, .btn:hover { background: var(--teal-deep); }
        button:disabled { opacity: .7; cursor: wait; }
        .side-nav button.nav-toggle {
            width: 34px;
            height: 34px;
            margin-right: 4px;
            padding: 0;
            background: transparent;
            color: #d5deea;
            display: grid;
            place-items: center;
            flex: none;
        }
        .side-nav button.nav-toggle svg { width: 16px; height: 16px; transition: transform .15s ease; }
        .side-nav button.nav-toggle[aria-expanded="true"] svg { transform: rotate(180deg); }
        .side-nav button.nav-toggle:hover { background: rgba(255,255,255,.08); color: white; }
        button.ghost, .btn.ghost {
            background: transparent;
            color: white;
            border: 1px solid rgba(255,255,255,.35);
            padding: 8px 12px;
        }
        button.ghost:hover { background: rgba(255,255,255,.08); }
        .stage { min-width: 0; }
        .top {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: end;
            padding: 28px 32px 0;
        }
        .kicker {
            margin: 0 0 4px;
            color: var(--teal-deep);
            font-size: 12px;
            letter-spacing: .14em;
            text-transform: uppercase;
        }
        h1 {
            margin: 0;
            font-family: Georgia, "Palatino Linotype", serif;
            font-size: 40px;
            font-weight: 560;
            letter-spacing: -.02em;
        }
        .signed { margin: 0; color: var(--muted); font-size: 14px; }
        main { padding: 22px 32px 36px; }
        h2 { margin: 0 0 8px; font-size: 16px; font-weight: 600; }
        .meta, .lede { color: var(--muted); font-size: 14px; line-height: 1.5; }
        .stats { display: grid; grid-template-columns: 1.3fr 1fr 1fr; gap: 14px; margin-bottom: 18px; }
        .stat {
            background: var(--card);
            border: 1px solid var(--line);
            padding: 16px 18px;
        }
        .stat span { display: block; color: var(--muted); font-size: 12px; letter-spacing: .08em; text-transform: uppercase; margin-bottom: 8px; }
        .stat strong { font-size: 18px; }
        .work { display: grid; grid-template-columns: minmax(280px, 400px) 1fr; gap: 16px; align-items: start; }
        .panel { background: var(--card); border: 1px solid var(--line); padding: 20px; }
        label { display: block; font-size: 13px; margin: 14px 0 6px; color: var(--navy); }
        input, select, textarea {
            width: 100%;
            border: 1px solid var(--line);
            background: white;
            color: var(--ink);
            padding: 10px 12px;
            font: inherit;
        }
        textarea { min-height: 96px; resize: vertical; }
        textarea.prompt { min-height: 148px; }
        .panel .row { margin-bottom: 4px; }
        .panel .row label { margin-top: 0; }
        input:focus, select:focus, textarea:focus { outline: 2px solid var(--teal); border-color: transparent; }
        .error { background: var(--danger-bg); border: 1px solid var(--danger-line); color: var(--danger); padding: 10px 12px; margin-bottom: 12px; }
        .status { background: #e7f6f4; border: 1px solid #b7e0dc; color: var(--navy); padding: 10px 12px; margin-bottom: 12px; }
        .timer-value { margin: 4px 0 8px; font-family: Georgia, "Palatino Linotype", serif; font-size: 56px; font-variant-numeric: tabular-nums; color: var(--navy); }
        .post, .prompt { white-space: pre-wrap; line-height: 1.55; }
        .prompt { background: var(--paper); padding: 12px; margin-top: 8px; }
        details { margin-top: 14px; }
        .editor { min-height: 62vh; font-family: "Cascadia Mono", Consolas, monospace; font-size: 13px; line-height: 1.55; }
        .savebar { display: flex; justify-content: space-between; gap: 16px; align-items: center; margin-top: 14px; }
        .settings-grid { display: grid; gap: 16px; }
        .add-key { display: grid; grid-template-columns: 1fr 1.4fr 1fr auto; gap: 12px; align-items: end; }
        .add-key button { margin-bottom: 0; }
        .key-list { display: grid; gap: 8px; margin-top: 16px; }
        .key-list form { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding-top: 8px; border-top: 1px solid var(--line); }
        .key-list button.ghost { color: var(--navy); border-color: var(--line); background: transparent; }
        .chat-frame { display: grid; grid-template-rows: auto minmax(0, 1fr) auto; min-height: 0; padding: 0; }
        .chat-head, .composer { padding: 14px 18px; }
        .chat-head { border-bottom: 1px solid var(--line); }
        .chat-head h2 { margin-bottom: 4px; }
        .chat-head .meta { margin: 0; }
        .thread {
            min-height: 0;
            overflow: auto;
            padding: 16px 18px;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            justify-content: flex-start;
            gap: 10px;
        }
        .bubble {
            flex: none;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: flex-start;
            width: fit-content;
            max-width: min(640px, 78%);
            height: auto;
            margin: 0;
            padding: 8px 12px;
            line-height: 1.45;
            text-align: left;
        }
        .bubble p { margin: 0; max-width: 100%; text-align: left; }
        .bubble .who { font-size: 12px; line-height: 1.3; margin-bottom: 4px; }
        .bubble .body { white-space: pre-wrap; overflow-wrap: anywhere; }
        .bubble .image-brief { margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--line); display: flex; flex-direction: column; gap: 4px; }
        .bubble .image-brief .meta { color: var(--muted); }
        .bubble.user { align-self: flex-end; background: var(--navy); color: white; }
        .bubble.user .who { color: #b7c3d1; }
        .bubble.assistant { align-self: flex-start; background: #f7f4ee; border: 1px solid var(--line); color: var(--ink); }
        .bubble.assistant .who { color: var(--muted); }
        .composer { border-top: 1px solid var(--line); display: grid; gap: 8px; }
        .composer label { margin-top: 0; }
        .send-row { display: grid; grid-template-columns: 1fr auto; gap: 10px; align-items: end; }
        .composer textarea, .send-row textarea { min-height: 52px; }
        .stage.chat-page {
            height: 100vh;
            max-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .stage.chat-page .top { flex: none; padding-top: 16px; }
        .stage.chat-page h1 { font-size: 32px; }
        .stage.chat-page main {
            flex: 1 1 auto;
            min-height: 0;
            display: flex;
            flex-direction: column;
            padding-top: 12px;
            padding-bottom: 12px;
        }
        .stage.chat-page .chat-frame { flex: 1 1 auto; min-height: 0; height: auto; }
        .row { display: flex; gap: 12px; align-items: end; flex-wrap: wrap; }
        .composer .row label { margin: 0 0 4px; }
        .row select { width: auto; min-width: 160px; }
        .row button { margin: 0; }
        .running-banner { margin: 18px 32px 0; padding: 10px 12px; background: #e7f6f4; border: 1px solid #b7e0dc; color: var(--navy); }
        @media (max-width: 900px) {
            .shell { grid-template-columns: 1fr; }
            aside { gap: 16px; height: auto; position: static; }
            .side-nav { flex-direction: column; }
            .recents-list { max-height: 160px; }
            .side-foot { margin-top: 0; }
            .stats, .work, .add-key { grid-template-columns: 1fr; }
            h1 { font-size: 32px; }
            .top, main { padding-left: 16px; padding-right: 16px; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <aside>
            <div class="brand">
                <div class="mark">W</div>
                <div>
                    <strong>Webexpert</strong>
                    <small>Manufacturing posts</small>
                </div>
            </div>
            @include('partials.nav')
            <div class="side-foot">
                <p class="who">Signed in as {{ auth()->user()->name }}</p>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="ghost" type="submit">Log out</button>
                </form>
            </div>
        </aside>
        <div class="stage @yield('stage_class')">
            @if (! empty($runningGeneration))
                <p class="running-banner">Writing is still going. This page loaded on its own, and the other screens stay available.</p>
            @endif
            <header class="top">
                <div>
                    <p class="kicker">@yield('kicker')</p>
                    <h1>@yield('heading')</h1>
                </div>
            </header>
            <main>
                @yield('content')
            </main>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
