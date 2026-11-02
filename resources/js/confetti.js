/**
 * A short burst of falling confetti on a full-screen canvas. Returns a function that
 * stops the animation early. Does nothing when the viewer prefers reduced motion.
 */
export function launchConfetti(canvas, colors, count = 160, duration = 4000) {
    const context = canvas?.getContext('2d');

    if (!context || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return () => {};
    }

    const ratio = window.devicePixelRatio || 1;
    const width = window.innerWidth;
    const height = window.innerHeight;

    canvas.width = width * ratio;
    canvas.height = height * ratio;
    context.setTransform(ratio, 0, 0, ratio, 0, 0);

    const pieces = Array.from({ length: count }, () => ({
        x: Math.random() * width,
        y: -20 - Math.random() * height * 0.6,
        w: 6 + Math.random() * 6,
        h: 8 + Math.random() * 8,
        color: colors[Math.floor(Math.random() * colors.length)],
        drift: (Math.random() - 0.5) * 1.5,
        speed: 2.5 + Math.random() * 3,
        rotation: Math.random() * Math.PI,
        spin: (Math.random() - 0.5) * 0.2,
        phase: Math.random() * Math.PI * 2,
    }));

    const start = performance.now();
    let frame = 0;

    const draw = (now) => {
        const elapsed = now - start;

        context.clearRect(0, 0, width, height);
        context.globalAlpha = elapsed > duration - 800 ? Math.max(0, (duration - elapsed) / 800) : 1;

        for (const piece of pieces) {
            piece.x += piece.drift + Math.sin(now / 400 + piece.phase) * 0.6;
            piece.y += piece.speed;
            piece.rotation += piece.spin;

            if (piece.y > height + 20) {
                piece.y = -20;
                piece.x = Math.random() * width;
            }

            context.save();
            context.translate(piece.x, piece.y);
            context.rotate(piece.rotation);
            context.fillStyle = piece.color;
            context.fillRect(-piece.w / 2, -piece.h / 2, piece.w, piece.h);
            context.restore();
        }

        if (elapsed < duration) {
            frame = requestAnimationFrame(draw);
        } else {
            context.clearRect(0, 0, width, height);
        }
    };

    frame = requestAnimationFrame(draw);

    return () => cancelAnimationFrame(frame);
}
