<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in</title>
    <style>
        :root {
            color-scheme: light;
            --navy: #0B2545;
            --navy-2: #12325c;
            --teal: #13A89E;
            --teal-bright: #7ee0d8;
            --paper: #f4f0e8;
            --card: #fffdf9;
            --line: #e3dacd;
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
            color: var(--ink);
            background: var(--navy);
        }
        .login {
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(340px, .72fr);
        }
        .story {
            position: relative;
            overflow: hidden;
            min-height: 100vh;
            padding: 36px 64px 32px;
            display: flex;
            flex-direction: column;
            color: white;
            background-color: var(--navy);
            background-image:
                linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px);
            background-size: 64px 64px;
            background-position: center;
        }
        .story::after {
            content: "W";
            position: absolute;
            right: -.04em;
            top: 12%;
            font-family: Georgia, "Palatino Linotype", serif;
            font-size: 26rem;
            line-height: .75;
            color: rgba(255,255,255,.07);
            pointer-events: none;
        }
        .brand, .hero, .facts { position: relative; z-index: 1; }
        .brand { display: flex; gap: 12px; align-items: center; }
        .mark {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border: 1px solid rgba(255,255,255,.4);
            color: var(--teal-bright);
            font-family: Georgia, "Palatino Linotype", serif;
            font-size: 22px;
        }
        .brand strong { display: block; font-weight: 600; letter-spacing: .04em; }
        .brand small { color: #b7c6d6; font-size: 12px; letter-spacing: .12em; text-transform: uppercase; }
        .hero { margin: auto 0; max-width: 640px; padding: 28px 0 36px; }
        .kicker {
            margin: 0 0 16px;
            color: var(--teal-bright);
            font-size: 12px;
            letter-spacing: .18em;
            text-transform: uppercase;
        }
        h1 {
            margin: 0;
            max-width: 8.5ch;
            font-family: Georgia, "Palatino Linotype", serif;
            font-size: clamp(52px, 5.6vw, 84px);
            font-weight: 500;
            letter-spacing: -.035em;
            line-height: .94;
        }
        h1 em {
            font-style: italic;
            color: var(--teal-bright);
        }
        .lede {
            margin: 22px 0 0;
            max-width: 36ch;
            color: #d5deea;
            font-size: 17px;
            line-height: 1.55;
        }
        .facts {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 22px;
            margin: 0 -64px -32px;
            padding: 22px 64px 32px;
            border-top: 1px solid rgba(255,255,255,.16);
            background: var(--navy);
        }
        .facts strong {
            display: block;
            margin-bottom: 4px;
            color: white;
            font-size: 15px;
            font-weight: 600;
        }
        .facts span { color: #9aafc4; font-size: 13px; line-height: 1.45; }
        .gate {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 36px;
            background:
                linear-gradient(180deg, rgba(19,168,158,.14), transparent 120px),
                var(--paper);
            box-shadow: -24px 0 40px rgba(0,0,0,.12);
        }
        form { width: min(380px, 100%); }
        h2 {
            margin: 0;
            font-family: Georgia, "Palatino Linotype", serif;
            font-size: 44px;
            font-weight: 500;
            letter-spacing: -.03em;
        }
        .intro { margin: 8px 0 0; color: var(--muted); font-size: 15px; line-height: 1.5; }
        label { display: block; margin: 18px 0 7px; color: var(--navy); font-size: 13px; font-weight: 600; }
        input[type="email"], input[type="password"] {
            width: 100%;
            height: 50px;
            border: 1px solid var(--line);
            background: var(--card);
            color: var(--ink);
            padding: 0 14px;
            font: inherit;
            font-size: 15px;
        }
        input:focus {
            outline: none;
            border-color: var(--teal);
            box-shadow: 0 0 0 4px rgba(19,168,158,.16);
        }
        .remember {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 16px;
            color: var(--ink);
            font-size: 14px;
            font-weight: 500;
        }
        .remember input { width: 16px; height: 16px; margin: 0; accent-color: var(--teal); }
        button {
            width: 100%;
            height: 52px;
            margin-top: 20px;
            border: 0;
            background: var(--teal);
            color: white;
            font: inherit;
            font-size: 16px;
            font-weight: 600;
            letter-spacing: .01em;
            cursor: pointer;
        }
        button:hover { background: #0e7f77; }
        .error {
            margin-top: 16px;
            padding: 10px 12px;
            background: var(--danger-bg);
            border: 1px solid var(--danger-line);
            color: var(--danger);
        }
        .local {
            margin: 22px 0 0;
            padding: 12px 14px;
            background: rgba(255,255,255,.72);
            border-left: 3px solid var(--teal);
            color: var(--muted);
            font-size: 13px;
            line-height: 1.55;
        }
        .local strong { color: var(--navy); font-weight: 600; }
        @media (max-width: 900px) {
            .login { grid-template-columns: 1fr; }
            .story { min-height: auto; padding: 24px 22px 8px; }
            .story::after { font-size: 16rem; }
            .hero { margin: 28px 0 22px; padding: 0; }
            h1 { font-size: 46px; }
            .facts { display: none; }
            .gate { min-height: auto; padding: 8px 22px 32px; box-shadow: none; }
        }
    </style>
</head>
<body>
    <main class="login">
        <section class="story">
            <div class="brand">
                <div class="mark">W</div>
                <div>
                    <strong>Webexpert</strong>
                    <small>Studio</small>
                </div>
            </div>
            <div class="hero">
                <p class="kicker">Manufacturing posts</p>
                <h1>Posts that sound like the <em>shop floor.</em></h1>
                <p class="lede">One factory moment at a time, then the smallest custom software that would stop the chase.</p>
            </div>
            <div class="facts">
                <div>
                    <strong>Moment</strong>
                    <span>One verified detail from the floor.</span>
                </div>
                <div>
                    <strong>Caption</strong>
                    <span>A post ready to publish.</span>
                </div>
                <div>
                    <strong>History</strong>
                    <span>Chats you can come back to.</span>
                </div>
            </div>
        </section>
        <section class="gate">
            <form method="POST" action="{{ route('login.store') }}">
                @csrf
                <p class="kicker">Welcome back</p>
                <h2>Sign in</h2>
                <p class="intro">The dashboard opens after you sign in.</p>
                @if ($errors->any())
                    <div class="error">{{ $errors->first() }}</div>
                @endif
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', 'mahendra@webexpertinfotech.com') }}" autocomplete="username" required autofocus>
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                <label class="remember">
                    <input type="checkbox" name="remember" value="1"> Keep me signed in
                </label>
                <button type="submit">Sign in</button>
                <p class="local"><strong>Local account</strong><br>mahendra@webexpertinfotech.com<br>Password: password</p>
            </form>
        </section>
    </main>
</body>
</html>
