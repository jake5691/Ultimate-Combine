    const toggles = document.querySelectorAll(".js-toggle");
    const toggleTargets = ["edit-team", "create-player", "create-combine", "create-discipline"];
    const closeTarget = (targetId) => {
      const target = document.getElementById(targetId);
      if (!target || target.classList.contains("is-hidden")) return;
      target.classList.add("is-hidden");
      const toggle = document.querySelector(`[data-target="${targetId}"]`);
      if (!toggle) return;
      toggle.setAttribute("aria-expanded", "false");
      if (targetId === "edit-team") {
        const saveButton = document.querySelector(".js-edit-save");
        const editLabel = toggle.dataset.labelEdit || "Bearbeiten";
        toggle.textContent = editLabel;
        toggle.classList.remove("is-muted");
        if (saveButton) {
          saveButton.classList.add("is-hidden");
        }
      }
    };
    const closeOtherTargets = (exceptId) => {
      toggleTargets.forEach((id) => {
        if (id === exceptId) return;
        closeTarget(id);
      });
    };

    toggles.forEach((btn) => {
      btn.addEventListener("click", () => {
        const targetId = btn.dataset.target;
        const target = document.getElementById(targetId);
        if (!target) return;
        if (targetId && targetId.startsWith("create-")) {
          closeTarget("edit-team");
        }
        const isHidden = target.classList.toggle("is-hidden");
        btn.setAttribute("aria-expanded", String(!isHidden));
        if (!isHidden && targetId && toggleTargets.includes(targetId)) {
          closeOtherTargets(targetId);
        }
        if (targetId === "edit-team") {
          const saveButton = document.querySelector(".js-edit-save");
          const editLabel = btn.dataset.labelEdit || "Bearbeiten";
          const cancelLabel = btn.dataset.labelCancel || "Abbrechen";
          btn.textContent = isHidden ? editLabel : cancelLabel;
          btn.classList.toggle("is-muted", !isHidden);
          if (saveButton) {
            saveButton.classList.toggle("is-hidden", isHidden);
          }
        }
        if (!isHidden) {
          target.scrollIntoView({ behavior: "smooth", block: "start" });
        }
      });
    });

    const closeButtons = document.querySelectorAll(".js-close");
    closeButtons.forEach((btn) => {
      btn.addEventListener("click", () => {
        const targetId = btn.dataset.target;
        const target = targetId ? document.getElementById(targetId) : null;
        if (!target) return;
        target.classList.add("is-hidden");
        const toggle = document.querySelector(`[data-target="${targetId}"]`);
        if (toggle) {
          toggle.setAttribute("aria-expanded", "false");
        }
      });
    });

    const infoButtons = document.querySelectorAll(".js-info");
    const closeAllInfos = (except) => {
      infoButtons.forEach((btn) => {
        if (btn === except) return;
        btn.classList.remove("is-open");
        btn.setAttribute("aria-expanded", "false");
      });
    };

    infoButtons.forEach((btn) => {
      btn.addEventListener("click", (event) => {
        event.stopPropagation();
        const isOpen = btn.classList.toggle("is-open");
        btn.setAttribute("aria-expanded", String(isOpen));
        if (isOpen) {
          closeAllInfos(btn);
        }
      });
    });

    document.addEventListener("click", () => {
      closeAllInfos();
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        closeAllInfos();
      }
    });

    const globalToggle = document.querySelector(".js-toggle-global-disciplines");
    const disciplinesList = document.getElementById("disciplines-list");
    if (globalToggle && disciplinesList) {
      const updateCategoryVisibility = (hideGlobal) => {
        const blocks = disciplinesList.querySelectorAll("[data-category-block]");
        blocks.forEach((block) => {
          const items = block.querySelectorAll("[data-discipline-scope]");
          const hasVisible = Array.from(items).some((item) => {
            if (!hideGlobal) return true;
            return item.dataset.disciplineScope !== "global";
          });
          block.classList.toggle("is-hidden", !hasVisible);
          block.hidden = !hasVisible;
        });
      };

      const setGlobalHidden = (hideGlobal) => {
        disciplinesList.classList.toggle("is-hide-global", hideGlobal);
        globalToggle.setAttribute("aria-pressed", String(hideGlobal));
        globalToggle.classList.toggle("is-muted", hideGlobal);
        const label = hideGlobal ? globalToggle.dataset.labelShow : globalToggle.dataset.labelHide;
        if (label) {
          globalToggle.setAttribute("aria-label", label);
        }
        updateCategoryVisibility(hideGlobal);
      };

      globalToggle.addEventListener("click", () => {
        const hideGlobal = !disciplinesList.classList.contains("is-hide-global");
        setGlobalHidden(hideGlobal);
      });

      setGlobalHidden(false);
    }

    const flashMessage = document.querySelector(".js-flash");
    if (flashMessage) {
      window.setTimeout(() => {
        flashMessage.classList.add("is-hidden");
      }, 10000);
    }

    const keyToggle = document.querySelector(".js-toggle-key");
    if (keyToggle) {
      const keyFields = document.querySelector(".key-fields");
      const keyInputs = keyFields ? keyFields.querySelectorAll("input") : [];
      const changeKeyInput = document.querySelector("input[name='change_key']");

      keyToggle.addEventListener("click", () => {
        if (!keyFields || !changeKeyInput) return;
        const isHidden = keyFields.classList.toggle("is-hidden");
        const isOpen = !isHidden;
        keyToggle.setAttribute("aria-expanded", String(isOpen));
        changeKeyInput.value = isOpen ? "1" : "0";
        keyInputs.forEach((input) => {
          if (isOpen) {
            input.setAttribute("required", "required");
          } else {
            input.removeAttribute("required");
            input.value = "";
          }
        });
      });
    }

    const unitOptions = document.getElementById("unit-options");
    const unitNameInputs = document.querySelectorAll("input[data-unit-name]");
    unitNameInputs.forEach((input) => {
      const form = input.closest("form");
      const abbrInput = form ? form.querySelector("input[data-unit-abbr]") : null;
      if (!abbrInput) return;
      input.addEventListener("input", () => {
        if (!unitOptions) return;
        const match = Array.from(unitOptions.options).find((opt) => opt.value === input.value);
        if (match) {
          const abbr = match.getAttribute("data-abbr") || "";
          if (abbr !== "") {
            abbrInput.value = abbr;
          }
        }
      });
    });

  
