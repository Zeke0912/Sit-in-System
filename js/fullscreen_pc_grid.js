
document.addEventListener('DOMContentLoaded', function() {
    // Apply fullscreen styling
    const formContainer = document.querySelector('.form-container');
    if (formContainer) { formContainer.style.maxWidth = '100%'; formContainer.style.width = '100%'; }
    setInterval(function() {
        const pcGrid = document.querySelector('.pc-grid');
        if (pcGrid) { pcGrid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(150px, 1fr))'; pcGrid.style.gap = '10px'; }
