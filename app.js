
const cards = {
    'first': './plalist-section/calm-playlist.html',
    'second-card': './plalist-section/joyful-playlist.html',
    'third-card': './plalist-section/romantic-playlist.html',
    'four-card': './plalist-section/energy-playlist.html',
    'fifth-card': './plalist-section/melancholy-playlist.html',
    'six-card': './plalist-section/focus-playlist.html'
};

document.querySelectorAll('.card').forEach(card => {
    card.addEventListener('click', () => {
        const link = cards[card.id];
        if (link) window.open(link, '_blank');
    });
});

