@if (! empty($running))
    @push('scripts')
        <script>
            (function () {
                const value = document.getElementById('timer-value');
                const started = Date.parse(@json(optional($running->started_at)->toIso8601String()));
                if (value && ! Number.isNaN(started)) {
                    const tick = function () {
                        const seconds = Math.max(0, Math.floor((Date.now() - started) / 1000));
                        value.textContent = Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0');
                    };
                    tick();
                    window.setInterval(tick, 1000);
                }
                window.setInterval(async function () {
                    try {
                        const response = await fetch(@json(route('generations.show', $running)), {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });
                        if (! response.ok) {
                            return;
                        }
                        const body = await response.json();
                        if (body.status !== 'running') {
                            window.location.reload();
                        }
                    } catch (error) {
                    }
                }, 2000);
            })();
        </script>
    @endpush
@endif
