(() => {
  const storageKey = "uc-theme";
  const root = document.documentElement;
  const media = window.matchMedia ? window.matchMedia("(prefers-color-scheme: dark)") : null;

  const getPreferredTheme = () => (media && media.matches ? "dark" : "light");

  const updateToggles = () => {
    const current = root.getAttribute("data-theme") || getPreferredTheme();
    document.querySelectorAll("[data-theme-toggle]").forEach((button) => {
      button.textContent = current === "dark" ? "Hell" : "Dunkel";
      button.setAttribute("aria-pressed", current === "dark" ? "true" : "false");
    });
  };

  const stored = window.localStorage ? localStorage.getItem(storageKey) : null;
  const initial = stored === "dark" || stored === "light" ? stored : getPreferredTheme();
  root.setAttribute("data-theme", initial);
  updateToggles();

  document.addEventListener("click", (event) => {
    const button = event.target.closest("[data-theme-toggle]");
    if (!button) return;
    const current = root.getAttribute("data-theme") || getPreferredTheme();
    const next = current === "dark" ? "light" : "dark";
    root.setAttribute("data-theme", next);
    if (window.localStorage) {
      localStorage.setItem(storageKey, next);
    }
    updateToggles();
  });

  if (media && media.addEventListener) {
    media.addEventListener("change", (event) => {
      if (window.localStorage && localStorage.getItem(storageKey)) {
        return;
      }
      root.setAttribute("data-theme", event.matches ? "dark" : "light");
      updateToggles();
    });
  }
})();
