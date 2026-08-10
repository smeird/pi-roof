(function () {
  const STORAGE_KEY = 'theme';
  const VALID_THEMES = new Set(['light', 'dark', 'system']);
  const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

  function getPreference() {
    const saved = localStorage.getItem(STORAGE_KEY);
    return VALID_THEMES.has(saved) ? saved : 'system';
  }

  function resolveTheme(preference = getPreference()) {
    return preference === 'system' ? (mediaQuery.matches ? 'dark' : 'light') : preference;
  }

  function apply(preference = getPreference(), notify = true) {
    const resolved = resolveTheme(preference);
    document.documentElement.classList.toggle('dark', resolved === 'dark');
    document.documentElement.dataset.theme = resolved;
    document.documentElement.dataset.themePreference = preference;
    document.documentElement.style.colorScheme = resolved;
    if (notify) {
      window.dispatchEvent(new CustomEvent('observatory-theme-change', {
        detail: { preference, resolved }
      }));
    }
    return resolved;
  }

  function setPreference(preference) {
    if (!VALID_THEMES.has(preference)) return;
    localStorage.setItem(STORAGE_KEY, preference);
    apply(preference);
  }

  function bindSelect(select) {
    if (!select) return;
    select.value = getPreference();
    select.addEventListener('change', () => setPreference(select.value));
    window.addEventListener('observatory-theme-change', event => {
      select.value = event.detail.preference;
    });
  }

  mediaQuery.addEventListener('change', () => {
    if (getPreference() === 'system') apply('system');
  });

  window.addEventListener('storage', event => {
    if (event.key === STORAGE_KEY) apply(getPreference());
  });

  window.ObservatoryTheme = {
    apply,
    bindSelect,
    getPreference,
    resolveTheme,
    setPreference
  };

  apply(getPreference(), false);
})();
