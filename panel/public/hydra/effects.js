/*
 * Hydra: seasonal effects (Design > Effects). Works the same in the client area
 * (React) and the admin area (Blade): everything lives outside of the page content
 * so that page changes do not touch it. Only loaded while an event runs.
 *
 * - Decoration: elements in #hydra-fx-ground and #hydra-fx-top, styled by effects.css.
 * - Falling particles: a canvas above the page that lets clicks through.
 * - Fireworks: a canvas behind the page content.
 */
(function () {
    'use strict';

    var config = window.HydraEffectsConfig;
    if (!config) return;

    var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var values = Object.assign({}, config.values);

    // Elements of the decoration per event, positions are in effects.css.
    var GROUND = {
        halloween: ['web web--top', 'web web--bottom', 'spider', 'pumpkin pumpkin--big', 'pumpkin pumpkin--small'],
        christmas: ['snowbank'],
        newyear: ['snowbank'],
    };
    var TOP = {
        halloween: [],
        christmas: ['lights lights--a', 'lights lights--b'],
        newyear: [],
    };

    function layer(id, classes) {
        var box = document.getElementById(id);
        if (!box) {
            box = document.createElement('div');
            box.id = id;
            box.setAttribute('aria-hidden', 'true');
            document.body.appendChild(box);
        }
        box.innerHTML = '';
        classes.forEach(function (name) {
            var element = document.createElement('div');
            element.className = name
                .split(' ')
                .map(function (part) {
                    return 'hydra-fx-' + part;
                })
                .join(' ');
            box.appendChild(element);
        });
    }

    function drawDecoration() {
        document.documentElement.setAttribute('data-hydra-decor', values.decor ? 'on' : 'off');
        layer('hydra-fx-ground', values.decor ? GROUND[config.event] || [] : []);
        layer('hydra-fx-top', values.decor ? TOP[config.event] || [] : []);
    }

    /* ------------------------------------------------------------ Canvas */

    function createCanvas(id) {
        var canvas = document.createElement('canvas');
        canvas.id = id;
        canvas.setAttribute('aria-hidden', 'true');
        document.body.appendChild(canvas);
        return canvas;
    }

    function fit(canvas) {
        // Sharp enough on high resolution screens without costing too much.
        var ratio = Math.min(window.devicePixelRatio || 1, 1.5);
        canvas.width = window.innerWidth * ratio;
        canvas.height = window.innerHeight * ratio;
        canvas.getContext('2d').setTransform(ratio, 0, 0, ratio, 0, 0);
    }

    function random(min, max) {
        return min + Math.random() * (max - min);
    }

    /* ------------------------------------------------------------ Snow and leaves */

    var LEAF_COLORS = ['#c2571a', '#d98324', '#a63d12', '#8a5a1c', '#b8860b'];

    function particleCount() {
        var area = (window.innerWidth * window.innerHeight) / 1000000;
        // Leaves only now and then, snow a bit thicker.
        return config.event === 'halloween' ? Math.round(Math.min(5 + area * 5, 14)) : Math.round(Math.min(30 + area * 40, 110));
    }

    function newParticle(anywhere) {
        var leaf = config.event === 'halloween';
        return {
            x: random(0, window.innerWidth),
            y: anywhere ? random(0, window.innerHeight) : random(-40, -10),
            size: leaf ? random(7, 13) : random(1, 3.2),
            speed: leaf ? random(22, 48) : random(16, 52),
            drift: random(0, Math.PI * 2),
            sway: leaf ? random(18, 42) : random(6, 20),
            rotation: random(0, Math.PI * 2),
            spin: random(-1.6, 1.6),
            color: LEAF_COLORS[Math.floor(Math.random() * LEAF_COLORS.length)],
            alpha: leaf ? random(0.65, 0.95) : random(0.45, 0.95),
        };
    }

    function drawLeaf(context, p) {
        context.save();
        context.translate(p.x + Math.sin(p.drift) * p.sway, p.y);
        context.rotate(p.rotation);
        // Turning in the wind.
        context.scale(1, 0.45 + Math.abs(Math.sin(p.rotation)) * 0.55);
        context.globalAlpha = p.alpha;
        context.fillStyle = p.color;
        context.beginPath();
        context.moveTo(-p.size, 0);
        context.quadraticCurveTo(0, -p.size * 0.8, p.size, 0);
        context.quadraticCurveTo(0, p.size * 0.8, -p.size, 0);
        context.fill();
        context.globalAlpha = p.alpha * 0.5;
        context.strokeStyle = '#3f1d05';
        context.lineWidth = 0.7;
        context.beginPath();
        context.moveTo(-p.size, 0);
        context.lineTo(p.size, 0);
        context.stroke();
        context.restore();
    }

    function drawFlake(context, p) {
        context.globalAlpha = p.alpha;
        context.fillStyle = '#ffffff';
        context.beginPath();
        context.arc(p.x + Math.sin(p.drift) * p.sway, p.y, p.size, 0, Math.PI * 2);
        context.fill();
    }

    var particles = {
        canvas: null,
        items: [],
        start: function () {
            if (!this.canvas) {
                this.canvas = createCanvas('hydra-fx-particles');
                fit(this.canvas);
            }
            this.canvas.style.display = '';
            this.items = [];
            for (var i = particleCount(); i > 0; i--) this.items.push(newParticle(true));
        },
        stop: function () {
            this.items = [];
            if (this.canvas) this.canvas.style.display = 'none';
        },
        frame: function (seconds) {
            if (!this.items.length) return;

            var context = this.canvas.getContext('2d');
            var draw = config.event === 'halloween' ? drawLeaf : drawFlake;
            context.clearRect(0, 0, window.innerWidth, window.innerHeight);
            for (var i = 0; i < this.items.length; i++) {
                var p = this.items[i];
                p.y += p.speed * seconds;
                p.drift += seconds * 0.8;
                p.rotation += p.spin * seconds;
                if (p.y > window.innerHeight + 20) this.items[i] = p = newParticle(false);
                draw(context, p);
            }
            context.globalAlpha = 1;
        },
    };

    /* ------------------------------------------------------------ Fireworks */

    var ROCKET_COLORS = ['#ff4d4d', '#ffd24d', '#4dff88', '#4dc3ff', '#c77dff', '#ff8ad8', '#ffffff'];

    var fireworks = {
        canvas: null,
        rockets: [],
        sparks: [],
        wait: 0,
        active: false,
        start: function () {
            if (!this.canvas) {
                this.canvas = createCanvas('hydra-fx-fireworks');
                fit(this.canvas);
            }
            this.canvas.style.display = '';
            this.active = true;
            this.wait = 0.4;
        },
        stop: function () {
            this.active = false;
            this.rockets = [];
            this.sparks = [];
            if (this.canvas) this.canvas.style.display = 'none';
        },
        launch: function () {
            this.rockets.push({
                x: random(window.innerWidth * 0.08, window.innerWidth * 0.92),
                y: window.innerHeight + 6,
                // Explodes at a different height every time.
                top: random(window.innerHeight * 0.1, window.innerHeight * 0.8),
                speed: random(320, 520),
                drift: random(-22, 22),
                color: ROCKET_COLORS[Math.floor(Math.random() * ROCKET_COLORS.length)],
            });
        },
        explode: function (rocket) {
            var count = Math.round(random(60, 110));
            var power = random(110, 230);
            for (var i = 0; i < count; i++) {
                var angle = (Math.PI * 2 * i) / count + random(-0.08, 0.08);
                var speed = power * random(0.45, 1);
                this.sparks.push({
                    x: rocket.x,
                    y: rocket.y,
                    vx: Math.cos(angle) * speed,
                    vy: Math.sin(angle) * speed,
                    life: 1,
                    fade: random(0.5, 0.95),
                    color: Math.random() < 0.85 ? rocket.color : '#ffffff',
                });
            }
        },
        frame: function (seconds) {
            if (!this.active) return;

            var context = this.canvas.getContext('2d');
            context.clearRect(0, 0, window.innerWidth, window.innerHeight);

            this.wait -= seconds;
            if (this.wait <= 0) {
                this.launch();
                // Now and then two at once.
                if (Math.random() < 0.25) this.launch();
                this.wait = random(0.7, 2.6);
            }

            for (var r = this.rockets.length - 1; r >= 0; r--) {
                var rocket = this.rockets[r];
                rocket.y -= rocket.speed * seconds;
                rocket.x += rocket.drift * seconds;

                context.globalAlpha = 0.9;
                context.strokeStyle = rocket.color;
                context.lineWidth = 2;
                context.beginPath();
                context.moveTo(rocket.x, rocket.y);
                context.lineTo(rocket.x - rocket.drift * 0.05, rocket.y + 16);
                context.stroke();

                if (rocket.y <= rocket.top) {
                    this.explode(rocket);
                    this.rockets.splice(r, 1);
                }
            }

            for (var s = this.sparks.length - 1; s >= 0; s--) {
                var spark = this.sparks[s];
                spark.vy += 55 * seconds;
                spark.vx *= 1 - 0.9 * seconds;
                spark.vy *= 1 - 0.9 * seconds;
                spark.x += spark.vx * seconds;
                spark.y += spark.vy * seconds;
                spark.life -= spark.fade * seconds;
                if (spark.life <= 0) {
                    this.sparks.splice(s, 1);
                    continue;
                }

                context.globalAlpha = Math.min(1, spark.life * 1.3);
                context.fillStyle = spark.color;
                context.beginPath();
                context.arc(spark.x, spark.y, 2.1, 0, Math.PI * 2);
                context.fill();
            }
            context.globalAlpha = 1;
        },
    };

    /* ------------------------------------------------------------ Loop */

    var last = 0;
    function loop(time) {
        // A long pause, e.g. a tab in the background, must not make everything jump.
        var seconds = Math.min((time - last) / 1000, 0.05);
        last = time;
        particles.frame(seconds);
        fireworks.frame(seconds);
        window.requestAnimationFrame(loop);
    }

    function apply(next) {
        values = Object.assign(values, next);
        drawDecoration();

        // Without motion for people who asked their system for that.
        if (values.particles && !reducedMotion) particles.start();
        else particles.stop();

        if (values.fireworks && !reducedMotion) fireworks.start();
        else fireworks.stop();
    }

    window.addEventListener('resize', function () {
        if (particles.canvas) fit(particles.canvas);
        if (fireworks.canvas) fit(fireworks.canvas);
    });

    // Used by the settings in the account to take effect at once.
    window.HydraEffects = { apply: apply };

    apply({});
    window.requestAnimationFrame(function (time) {
        last = time;
        loop(time);
    });
})();
