/* CoreCMS navigation: admin sidebar + public mobile burger. */
(function () {
  'use strict';
  function initNavigation() {
    var isAdmin = document.body.classList.contains('admin-body');
    var navs = document.querySelectorAll(isAdmin ? 'nav.admin-nav' : 'header nav');
    navs.forEach(function (nav, index) {
      if (nav.dataset.corecmsBurgerReady === '1') return;
      nav.dataset.corecmsBurgerReady = '1';
      if (!nav.id) nav.id = 'corecms-mobile-nav-' + (index + 1);
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'corecms-nav-toggle';
      button.setAttribute('aria-controls', nav.id);
      button.setAttribute('aria-expanded', 'false');
      button.setAttribute('aria-label', 'Yönetim menüsünü aç');
      button.innerHTML = '<span class="corecms-burger-lines" aria-hidden="true"><span></span><span></span><span></span></span><span class="corecms-nav-toggle-label">Menü</span>';
      nav.parentNode.insertBefore(button, nav);
      function closeMenu() {
        nav.classList.remove('corecms-nav-open');
        button.setAttribute('aria-expanded', 'false');
        button.setAttribute('aria-label', 'Yönetim menüsünü aç');
      }
      button.addEventListener('click', function () {
        var open = button.getAttribute('aria-expanded') !== 'true';
        nav.classList.toggle('corecms-nav-open', open);
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
        button.setAttribute('aria-label', open ? 'Yönetim menüsünü kapat' : 'Yönetim menüsünü aç');
      });
      nav.addEventListener('click', function (event) {
        if (event.target.closest('a')) closeMenu();
      });
      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeMenu();
      });
      var desktop = window.matchMedia('(min-width: 769px)');
      var resetForDesktop = function (event) { if (event.matches) closeMenu(); };
      if (desktop.addEventListener) desktop.addEventListener('change', resetForDesktop);
      else if (desktop.addListener) desktop.addListener(resetForDesktop);
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initNavigation);
  else initNavigation();
})();
