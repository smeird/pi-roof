const iconNames = new Set([
  'fa-mountain-sun', 'fa-chart-line', 'fa-cloud-sun', 'fa-earth-europe',
  'fa-droplet', 'fa-moon', 'fa-camera', 'fa-link', 'fa-house', 'fa-server',
  'fa-gauge-high', 'fa-sliders'
]);

function makeRailLink({ href, label, icon, external = false, current = false }) {
  const link = document.createElement('a');
  link.className = 'ops-rail-link';
  link.href = href;
  link.setAttribute('aria-label', label);
  link.title = label;
  if (external) {
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
  }
  if (current) link.setAttribute('aria-current', 'page');
  const glyph = document.createElement('i');
  glyph.className = `fa-solid ${iconNames.has(icon) ? icon : 'fa-link'}`;
  glyph.setAttribute('aria-hidden', 'true');
  const tooltip = document.createElement('span');
  tooltip.className = 'ops-tooltip';
  tooltip.textContent = label;
  link.append(glyph, tooltip);
  return link;
}

function setupThemeMenu(container) {
  const wrap = document.createElement('div');
  wrap.style.position = 'relative';
  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'ops-icon-button';
  button.setAttribute('aria-label', 'Choose theme');
  button.setAttribute('aria-haspopup', 'menu');
  button.setAttribute('aria-expanded', 'false');
  button.innerHTML = '<i class="fa-solid fa-circle-half-stroke" aria-hidden="true"></i><span class="ops-tooltip">Theme</span>';

  const menu = document.createElement('div');
  menu.className = 'theme-menu';
  menu.hidden = true;
  menu.setAttribute('role', 'menu');
  const options = [
    ['light', 'fa-sun', 'Light'],
    ['dark', 'fa-moon', 'Dark'],
    ['system', 'fa-desktop', 'System']
  ];
  options.forEach(([value, icon, label]) => {
    const option = document.createElement('button');
    option.type = 'button';
    option.className = 'theme-option';
    option.dataset.theme = value;
    option.setAttribute('role', 'menuitemradio');
    option.innerHTML = `<i class="fa-solid ${icon}" aria-hidden="true"></i><span>${label}</span>`;
    option.addEventListener('click', () => {
      window.ObservatoryTheme.setPreference(value);
      menu.hidden = true;
      button.setAttribute('aria-expanded', 'false');
      button.focus();
    });
    menu.appendChild(option);
  });

  const sync = () => {
    const current = window.ObservatoryTheme.getPreference();
    menu.querySelectorAll('[data-theme]').forEach(option => {
      option.setAttribute('aria-checked', option.dataset.theme === current ? 'true' : 'false');
    });
  };
  sync();
  window.addEventListener('observatory-theme-change', sync);

  button.addEventListener('click', () => {
    menu.hidden = !menu.hidden;
    button.setAttribute('aria-expanded', menu.hidden ? 'false' : 'true');
  });
  wrap.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      menu.hidden = true;
      button.setAttribute('aria-expanded', 'false');
      button.focus();
    }
    if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
      event.preventDefault();
      menu.hidden = false;
      button.setAttribute('aria-expanded', 'true');
      const items = [...menu.querySelectorAll('button')];
      const current = items.indexOf(document.activeElement);
      const next = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1 : current < 0 ? (event.key === 'ArrowUp' ? items.length - 1 : 0) : (current + (event.key === 'ArrowUp' ? -1 : 1) + items.length) % items.length;
      items[next].focus();
    }
  });
  document.addEventListener('click', event => {
    if (!wrap.contains(event.target)) {
      menu.hidden = true;
      button.setAttribute('aria-expanded', 'false');
    }
  });
  wrap.append(button, menu);
  container.appendChild(wrap);
}

export function initShell({ activePage = '', quickLinks = [] } = {}) {
  const rail = document.getElementById('appRail');
  if (!rail) return;
  rail.replaceChildren();
  if (!document.querySelector('.ops-skip-link')) {
    const skip = document.createElement('a');
    skip.className = 'ops-skip-link';
    skip.href = '#mainContent';
    skip.textContent = 'Skip to main content';
    document.body.prepend(skip);
  }
  document.querySelectorAll('section > .ops-card-header .ops-card-title').forEach((title, index) => {
    if (title.querySelector('.ops-section-index')) return;
    const number = document.createElement('span');
    number.className = 'ops-section-index';
    number.setAttribute('aria-hidden', 'true');
    const instrumentNumbers = { roofTitle: '01', devicesTitle: '02', tapoTitle: '03', skyCamTitle: '04', sensorTitle: '05', chartTitle: '06', keogramTitle: '07' };
    number.textContent = activePage === 'dashboard' ? instrumentNumbers[title.id] : String(index + 1).padStart(2, '0');
    title.prepend(number);
  });

  const brand = makeRailLink({ href: '/', label: 'Observatory dashboard', icon: 'fa-gauge-high', current: activePage === 'dashboard' });
  brand.classList.replace('ops-rail-link', 'ops-rail-brand');
  brand.querySelector('i').outerHTML = '<svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 12 17-7 3 7-17 7zM4 14l3-1 2 4-3 1M22 4l5 9M16 17v5m0 0-7 7m7-7 7 7m-7-7v7"/></svg>';
  rail.appendChild(brand);

  const links = document.createElement('nav');
  links.className = 'ops-link-stack';
  links.setAttribute('aria-label', 'Observatory links');
  quickLinks.filter(link => link.visible !== false).forEach(link => {
    links.appendChild(makeRailLink({ href: link.url, label: link.label, icon: link.icon, external: true }));
  });
  links.id = 'observatoryNavigation';
  const navigationButton = document.createElement('button');
  navigationButton.type = 'button';
  navigationButton.hidden = links.childElementCount === 0;
  navigationButton.className = 'ops-icon-button ops-mobile-nav-toggle';
  navigationButton.setAttribute('aria-label', 'Observatory links');
  navigationButton.setAttribute('aria-controls', links.id);
  navigationButton.setAttribute('aria-expanded', 'false');
  navigationButton.innerHTML = '<i class="fa-solid fa-bars" aria-hidden="true"></i>';
  const closeNavigation = () => {
    links.classList.remove('is-open');
    navigationButton.setAttribute('aria-expanded', 'false');
  };
  navigationButton.addEventListener('click', () => {
    const open = links.classList.toggle('is-open');
    navigationButton.setAttribute('aria-expanded', String(open));
  });
  rail.addEventListener('keydown', event => {
    if (event.key === 'Escape' && links.classList.contains('is-open')) {
      closeNavigation();
      navigationButton.focus();
    }
  });
  document.addEventListener('click', event => {
    if (!links.contains(event.target) && !navigationButton.contains(event.target)) closeNavigation();
  });
  rail.append(navigationButton, links);

  const spacer = document.createElement('div');
  spacer.className = 'ops-rail-spacer';
  rail.appendChild(spacer);
  setupThemeMenu(rail);
  rail.appendChild(makeRailLink({ href: '/settings.html', label: 'Settings', icon: 'fa-sliders', current: activePage === 'settings' }));
}

export function createPager(container, pageSize, onPage) {
  let page = 0;
  let count = 0;
  const previous = container.querySelector('[data-page="previous"]');
  const next = container.querySelector('[data-page="next"]');
  const label = container.querySelector('[data-page-label]');

  function render() {
    const totalPages = Math.max(1, Math.ceil(count / pageSize));
    page = Math.min(page, totalPages - 1);
    container.hidden = count <= pageSize;
    if (label) label.textContent = `${page + 1}/${totalPages}`;
    if (previous) previous.disabled = page === 0;
    if (next) next.disabled = page >= totalPages - 1;
    onPage(page, pageSize);
  }

  previous?.addEventListener('click', () => { page = Math.max(0, page - 1); render(); });
  next?.addEventListener('click', () => { page += 1; render(); });
  return {
    setCount(value) { count = value; render(); },
    setPageSize(value) {
      if (value === pageSize) return;
      pageSize = Math.max(1, value); page = 0; render();
    },
    refresh: render
  };
}
