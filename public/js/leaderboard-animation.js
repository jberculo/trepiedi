(function () {
    'use strict';

    var dataEl = document.getElementById('anim-data');
    var board = document.getElementById('anim-board');
    var playBtn = document.getElementById('anim-play');
    var restartBtn = document.getElementById('anim-restart');
    var statusEl = document.getElementById('anim-status');
    if (!dataEl || !board) {
        return;
    }

    var data = JSON.parse(dataEl.textContent);
    var T = board.dataset; // vertaalde teksten (data-t-*)
    var players = data.players || [];
    var steps = data.steps || [];
    var n = players.length;
    var ROW_H = 48;
    var STEP_MS = 1000;

    var metric = 'points';
    var cumulative = new Array(n).fill(0); // doelwaarde
    var shown = new Array(n).fill(0);      // getweende weergave
    var maxSoFar = 0;                      // maximaal haalbaar tot nu (balk-basis)
    var stepIndex = 0;
    var timer = null;
    var raf = null;

    board.style.height = (n * ROW_H) + 'px';

    var avatarBase = T.avatarBase || '';
    var rows = players.map(function (p) {
        var row = document.createElement('div');
        row.className = 'anim-row';
        row.innerHTML =
            '<span class="anim-rank"></span>' +
            '<span class="anim-name"></span>' +
            '<span class="anim-bar"><span class="anim-bar-fill"></span></span>' +
            '<span class="anim-score"></span>';
        row.querySelector('.anim-name').textContent = p.name;

        var avatar;
        if (p.avatar) {
            avatar = document.createElement('img');
            avatar.className = 'avatar-sm';
            avatar.src = avatarBase + p.avatar + '-sm.jpg';
            avatar.alt = '';
        } else {
            avatar = document.createElement('span');
            avatar.className = 'avatar-sm avatar-empty';
            avatar.textContent = (p.name[0] || '').toUpperCase();
        }
        row.insertBefore(avatar, row.querySelector('.anim-name'));

        board.appendChild(row);
        return row;
    });

    function setStatus(text) {
        if (statusEl) {
            statusEl.textContent = text;
        }
    }

    function ease(t) {
        return t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;
    }

    function order() {
        return players.map(function (_, i) { return i; }).sort(function (a, b) {
            return (cumulative[b] - cumulative[a]) || players[a].name.localeCompare(players[b].name);
        });
    }

    // Posities (één keer per stap; CSS-transition laat de rij soepel verschuiven).
    // Rijen krijgen een eigen plek, maar gelijke standen delen hetzelfde rangnummer.
    function placeRows() {
        var prev = null;
        var rank = 0;
        order().forEach(function (idx, pos) {
            rows[idx].style.transform = 'translateY(' + (pos * ROW_H) + 'px)';
            if (prev === null || Math.abs(cumulative[idx] - prev) > 1e-9) {
                rank = pos + 1;
                prev = cumulative[idx];
            }
            rows[idx].querySelector('.anim-rank').textContent = rank;
        });
    }

    // Balken en cijfers (elke frame; rAF zorgt voor vloeiend groeien/tellen).
    // De balk is relatief aan de huidige hoogste score (koploper = volle balk);
    // rechts staat "huidige score / maximaal haalbaar tot deze wedstrijd".
    function paint() {
        var highest = 0;
        for (var i = 0; i < n; i++) {
            if (shown[i] > highest) {
                highest = shown[i];
            }
        }
        var basis = highest > 0 ? highest : 1;
        var max = Math.round(maxSoFar);
        for (var i = 0; i < n; i++) {
            rows[i].querySelector('.anim-bar-fill').style.width = (shown[i] / basis * 100) + '%';
            rows[i].querySelector('.anim-score').textContent = Math.round(shown[i]) + '/' + max;
        }
    }

    function tween(targets, maxTarget) {
        if (raf) {
            cancelAnimationFrame(raf);
        }
        var from = shown.slice();
        var fromMax = maxSoFar;
        var start = performance.now();
        function frame(now) {
            var t = Math.min(1, (now - start) / (STEP_MS * 0.9));
            var e = ease(t);
            for (var i = 0; i < n; i++) {
                shown[i] = from[i] + (targets[i] - from[i]) * e;
            }
            maxSoFar = fromMax + (maxTarget - fromMax) * e;
            paint();
            if (t < 1) {
                raf = requestAnimationFrame(frame);
            }
        }
        raf = requestAnimationFrame(frame);
    }

    function applyStep() {
        var step = steps[stepIndex];
        var gained = step[metric];
        for (var i = 0; i < n; i++) {
            cumulative[i] += gained[i];
        }
        placeRows();
        tween(cumulative.slice(), maxSoFar + step[metric + 'Max']);
        var label = step.label + (step.round ? ' (' + step.round + ')' : '');
        setStatus(T.tMatch.replace('%n%', stepIndex + 1).replace('%total%', steps.length).replace('%label%', label));
        stepIndex++;
    }

    function tick() {
        if (stepIndex >= steps.length) {
            stop();
            setStatus(T.tDone);
            return;
        }
        applyStep();
    }

    function stop() {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
        if (playBtn) {
            playBtn.textContent = T.tPlay;
        }
    }

    function play() {
        if (timer || steps.length === 0) {
            return;
        }
        if (stepIndex >= steps.length) {
            reset();
        }
        if (playBtn) {
            playBtn.textContent = T.tPause;
        }
        tick();
        timer = setInterval(tick, STEP_MS);
    }

    function reset() {
        if (raf) {
            cancelAnimationFrame(raf);
        }
        for (var i = 0; i < n; i++) {
            cumulative[i] = 0;
            shown[i] = 0;
        }
        maxSoFar = 0;
        stepIndex = 0;
        placeRows();
        paint();
        setStatus(steps.length ? T.tReady.replace('%count%', steps.length) : T.tNone);
    }

    if (playBtn) {
        playBtn.addEventListener('click', function () {
            timer ? stop() : play();
        });
    }
    if (restartBtn) {
        restartBtn.addEventListener('click', function () {
            stop();
            reset();
        });
    }

    var metricSelect = document.getElementById('anim-metric-select');

    function selectMetric(next) {
        stop();
        metric = next;
        document.querySelectorAll('[data-metric]').forEach(function (b) {
            b.classList.toggle('active', b.getAttribute('data-metric') === metric);
        });
        if (metricSelect && metricSelect.value !== metric) {
            metricSelect.value = metric;
        }
        reset();
    }

    document.querySelectorAll('[data-metric]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            selectMetric(btn.getAttribute('data-metric'));
        });
    });

    if (metricSelect) {
        metricSelect.addEventListener('change', function () {
            selectMetric(this.value);
        });
    }

    reset();

    if (new URLSearchParams(window.location.search).has('autoplay')) {
        play();
    }
})();
