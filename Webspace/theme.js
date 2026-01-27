(() => {
  const storageKey = "uc-theme-mode";
  const root = document.documentElement;
  const media = window.matchMedia ? window.matchMedia("(prefers-color-scheme: dark)") : null;

  const getPreferredTheme = () => (media && media.matches ? "dark" : "light");

  const setTheme = (mode) => {
    const resolved = mode === "system" ? getPreferredTheme() : mode;
    root.setAttribute("data-theme", resolved);
    root.setAttribute("data-theme-mode", mode);
  };

  const updateToggles = () => {
    const mode = root.getAttribute("data-theme-mode") || "system";
    document.querySelectorAll("[data-theme-toggle]").forEach((button) => {
      const label = mode === "system"
        ? (button.dataset.themeLabelSystem || "Auto")
        : (mode === "dark"
          ? (button.dataset.themeLabelDark || "Dunkel")
          : (button.dataset.themeLabelLight || "Hell"));
      button.textContent = label;
      button.setAttribute("aria-pressed", mode === "dark" ? "true" : "false");
    });
  };

  const stored = window.localStorage ? localStorage.getItem(storageKey) : null;
  const initialMode = stored === "dark" || stored === "light" || stored === "system" ? stored : "system";
  setTheme(initialMode);
  updateToggles();

  document.addEventListener("click", (event) => {
    const button = event.target.closest("[data-theme-toggle]");
    if (!button) return;
    const currentMode = root.getAttribute("data-theme-mode") || "system";
    const next = currentMode === "system" ? "dark" : (currentMode === "dark" ? "light" : "system");
    setTheme(next);
    if (window.localStorage) {
      localStorage.setItem(storageKey, next);
    }
    updateToggles();
  });

  document.addEventListener("click", (event) => {
    document.querySelectorAll("details.header-menu[open]").forEach((menu) => {
      if (!menu.contains(event.target)) {
        menu.removeAttribute("open");
      }
    });
  });

  if (media && media.addEventListener) {
    media.addEventListener("change", (event) => {
      const mode = root.getAttribute("data-theme-mode") || "system";
      if (mode !== "system") {
        return;
      }
      root.setAttribute("data-theme", event.matches ? "dark" : "light");
      updateToggles();
    });
  }
})();
