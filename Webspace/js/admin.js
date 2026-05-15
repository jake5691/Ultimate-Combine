    const toggles = document.querySelectorAll(".js-toggle");
    toggles.forEach((btn) => {
      btn.addEventListener("click", () => {
        const targetId = btn.dataset.target;
        const target = document.getElementById(targetId);
        if (!target) return;
        const isHidden = target.classList.toggle("is-hidden");
        target.hidden = isHidden;
        btn.setAttribute("aria-expanded", String(!isHidden));
        if (btn.hasAttribute("data-toggle-label")) {
          const label = !isHidden ? btn.getAttribute("data-label-open") : btn.getAttribute("data-label-closed");
          if (label) {
            btn.textContent = label;
          }
        }
        if (!isHidden) {
          target.scrollIntoView({ behavior: "smooth", block: "start" });
        }
      });
    });

    const editButton = document.querySelector("[data-edit-units]");
    const cancelButton = document.querySelector("[data-edit-units-cancel]");
    const editPanel = document.getElementById("edit-units");
    const overviewList = document.getElementById("units-overview");
    const addUnitPanel = document.getElementById("add-unit");
    const viewActions = document.getElementById("units-actions-view");
    const editActions = document.getElementById("units-actions-edit");
    let unitsOverviewWasHidden = overviewList ? overviewList.classList.contains("is-hidden") : true;

    const setEditMode = (isEdit) => {
      const show = isEdit ? "true" : "false";
      if (isEdit && overviewList) {
        unitsOverviewWasHidden = overviewList.classList.contains("is-hidden");
      }
      if (editPanel) {
        editPanel.classList.toggle("is-hidden", !isEdit);
        editPanel.hidden = !isEdit;
      }
      if (overviewList) {
        const hideOverview = isEdit || unitsOverviewWasHidden;
        overviewList.classList.toggle("is-hidden", hideOverview);
        overviewList.hidden = hideOverview;
      }
      if (addUnitPanel) {
        addUnitPanel.classList.toggle("is-hidden", true);
        addUnitPanel.hidden = true;
      }
      if (viewActions) viewActions.classList.toggle("is-hidden", isEdit);
      if (editActions) {
        editActions.classList.toggle("is-hidden", !isEdit);
        editActions.hidden = !isEdit;
      }
      if (editButton) editButton.setAttribute("aria-expanded", show);
      if (cancelButton) cancelButton.setAttribute("aria-expanded", show);
    };

    if (editButton) {
      editButton.addEventListener("click", () => setEditMode(true));
    }
    if (cancelButton) {
      cancelButton.addEventListener("click", () => setEditMode(false));
    }

    const editDisciplinesButton = document.querySelector("[data-edit-disciplines]");
    const cancelDisciplinesButton = document.querySelector("[data-edit-disciplines-cancel]");
    const editDisciplinesPanel = document.getElementById("edit-disciplines");
    const disciplinesOverview = document.getElementById("disciplines-overview");
    const addDisciplinePanel = document.getElementById("add-global-discipline");
    const disciplinesViewActions = document.getElementById("disciplines-actions-view");
    const disciplinesEditActions = document.getElementById("disciplines-actions-edit");
    let disciplinesOverviewWasHidden = disciplinesOverview ? disciplinesOverview.classList.contains("is-hidden") : true;

    const setDisciplinesEditMode = (isEdit) => {
      const show = isEdit ? "true" : "false";
      if (isEdit && disciplinesOverview) {
        disciplinesOverviewWasHidden = disciplinesOverview.classList.contains("is-hidden");
      }
      if (editDisciplinesPanel) {
        editDisciplinesPanel.classList.toggle("is-hidden", !isEdit);
        editDisciplinesPanel.hidden = !isEdit;
      }
      if (disciplinesOverview) {
        const hideOverview = isEdit || disciplinesOverviewWasHidden;
        disciplinesOverview.classList.toggle("is-hidden", hideOverview);
        disciplinesOverview.hidden = hideOverview;
      }
      if (addDisciplinePanel) {
        addDisciplinePanel.classList.toggle("is-hidden", true);
        addDisciplinePanel.hidden = true;
      }
      if (disciplinesViewActions) disciplinesViewActions.classList.toggle("is-hidden", isEdit);
      if (disciplinesEditActions) {
        disciplinesEditActions.classList.toggle("is-hidden", !isEdit);
        disciplinesEditActions.hidden = !isEdit;
      }
      if (editDisciplinesButton) editDisciplinesButton.setAttribute("aria-expanded", show);
      if (cancelDisciplinesButton) cancelDisciplinesButton.setAttribute("aria-expanded", show);
    };

    if (editDisciplinesButton) {
      editDisciplinesButton.addEventListener("click", () => setDisciplinesEditMode(true));
    }
    if (cancelDisciplinesButton) {
      cancelDisciplinesButton.addEventListener("click", () => setDisciplinesEditMode(false));
    }

    const unitOptions = document.getElementById("admin-unit-options");
    const unitNameInputs = document.querySelectorAll("input[data-unit-name]");
    unitNameInputs.forEach((input) => {
      const row = input.closest(".list-item") || input.closest("form");
      const abbrInput = row ? row.querySelector("input[data-unit-abbr]") : null;
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
  
