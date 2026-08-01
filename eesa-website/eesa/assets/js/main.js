// ---------------------------------------------------------------
// Hamburger -> cross, right-side slide menu
// ---------------------------------------------------------------
document.addEventListener('DOMContentLoaded', function () {
  const burger = document.getElementById('burger');
  const menu = document.getElementById('sideMenu');
  const overlay = document.getElementById('navOverlay');

  function openMenu() {
    burger.classList.add('open');
    menu.classList.add('open');
    overlay.classList.add('open');
    burger.setAttribute('aria-expanded', 'true');
  }
  function closeMenu() {
    burger.classList.remove('open');
    menu.classList.remove('open');
    overlay.classList.remove('open');
    burger.setAttribute('aria-expanded', 'false');
  }
  if (burger) {
    burger.addEventListener('click', function () {
      menu.classList.contains('open') ? closeMenu() : openMenu();
    });
  }
  if (overlay) overlay.addEventListener('click', closeMenu);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeMenu();
  });

// Disable double-submits — deferred via setTimeout so the browser finishes
  // building the form's POST data (which includes this button's name/value,
  // e.g. name="login") BEFORE we disable it. Disabling synchronously inside
  // the submit handler silently drops the button from the submitted data,
  // since disabled form elements are excluded when the entry list is built.
  document.addEventListener('submit', function (e) {
    const btn = e.target.querySelector('button[type=submit]');
    if (btn) {
      setTimeout(() => {
        btn.disabled = true;
        setTimeout(() => (btn.disabled = false), 4000);
      }, 0);
    }
  });

  // Activities category filter chips (client-side show/hide, no reload)
  const chips = document.querySelectorAll('.chip[data-filter]');
  const cards = document.querySelectorAll('[data-category]');
  chips.forEach(chip => {
    chip.addEventListener('click', () => {
      chips.forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      const filter = chip.dataset.filter;
      cards.forEach(card => {
        card.style.display = (filter === 'all' || card.dataset.category === filter) ? '' : 'none';
      });
    });
  });

  // Lightbox for gallery photo grids
  const lightboxImgs = document.querySelectorAll('.lightbox-grid img');
  if (lightboxImgs.length) {
    const overlayBox = document.createElement('div');
    overlayBox.style.cssText = 'position:fixed;inset:0;background:rgba(5,8,14,.9);display:none;align-items:center;justify-content:center;z-index:99;cursor:zoom-out;';
    const bigImg = document.createElement('img');
    bigImg.style.cssText = 'max-width:90vw;max-height:90vh;border-radius:8px;';
    overlayBox.appendChild(bigImg);
    document.body.appendChild(overlayBox);
    overlayBox.addEventListener('click', () => (overlayBox.style.display = 'none'));
    lightboxImgs.forEach(img => {
      img.addEventListener('click', () => {
        bigImg.src = img.src;
        overlayBox.style.display = 'flex';
      });
    });
  }
});

// ---------------------------------------------------------------
// Download the shareable "poster" card as a PNG (for LinkedIn/Instagram).
// Requires html2canvas (loaded via CDN on the activity view page).
// ---------------------------------------------------------------
function downloadPoster(elId, filename) {
  const node = document.getElementById(elId);
  if (!node || typeof html2canvas === 'undefined') return;
  html2canvas(node, { backgroundColor: null, scale: 2 }).then(canvas => {
    const link = document.createElement('a');
    link.download = (filename || 'eesa-poster') + '.png';
    link.href = canvas.toDataURL('image/png');
    link.click();
  });
}
