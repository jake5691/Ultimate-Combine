    const tabs = document.querySelectorAll(".segmented-button");
    const panels = document.querySelectorAll(".form[data-panel]");
    const tabLinks = document.querySelectorAll("[data-tab].js-tab-link");

    const setActiveTab = (tabName) => {
      tabs.forEach((t) => t.classList.toggle("is-active", t.dataset.tab === tabName));
      panels.forEach((panel) => {
        panel.classList.toggle("is-hidden", panel.dataset.panel !== tabName);
      });
    };

    tabs.forEach((tab) => {
      tab.addEventListener("click", () => setActiveTab(tab.dataset.tab));
    });

    tabLinks.forEach((link) => {
      link.addEventListener("click", (event) => {
        event.preventDefault();
        setActiveTab(link.dataset.tab);
        if (typeof closeDrawer === "function") {
          closeDrawer();
        }
        history.replaceState(null, "", link.getAttribute("href"));
      });
    });

    if (window.location.hash === "#register") {
      setActiveTab("register");
    }
  
