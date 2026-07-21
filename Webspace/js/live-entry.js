const playerForm = document.querySelector("[data-live-player-form]");

if (playerForm) {
  const checkboxes = Array.from(playerForm.querySelectorAll("[data-live-player-checkbox]"));
  const options = Array.from(playerForm.querySelectorAll("[data-live-player-option]"));
  const status = playerForm.querySelector("[data-live-selection-status]");
  const submit = playerForm.querySelector("[data-live-player-submit]");
  const clear = playerForm.querySelector("[data-live-clear-selection]");
  const search = playerForm.querySelector("[data-live-player-search]");
  const empty = playerForm.querySelector("[data-live-player-empty]");

  const updateSelection = () => {
    const count = checkboxes.filter((checkbox) => checkbox.checked).length;
    if (status) {
      status.textContent = (status.dataset.statusTemplate || "%d").replace("%d", String(count));
    }
    if (submit) {
      submit.disabled = count === 0;
    }
    if (clear) {
      clear.disabled = count === 0;
    }
    options.forEach((option) => {
      const checkbox = option.querySelector("[data-live-player-checkbox]");
      option.classList.toggle("is-selected", Boolean(checkbox && checkbox.checked));
    });
  };

  checkboxes.forEach((checkbox) => checkbox.addEventListener("change", updateSelection));

  if (clear) {
    clear.addEventListener("click", () => {
      checkboxes.forEach((checkbox) => {
        checkbox.checked = false;
      });
      updateSelection();
    });
  }

  if (search) {
    search.addEventListener("input", () => {
      const query = search.value.trim().toLocaleLowerCase();
      let visibleCount = 0;
      options.forEach((option) => {
        const matches = query === "" || option.textContent.toLocaleLowerCase().includes(query);
        option.classList.toggle("is-hidden", !matches);
        if (matches) {
          visibleCount += 1;
        }
      });
      if (empty) {
        empty.classList.toggle("is-hidden", visibleCount !== 0);
      }
    });
  }

  updateSelection();
}

const disciplineNav = document.querySelector("[data-live-discipline-nav]");
if (disciplineNav) {
  const activeDiscipline = disciplineNav.querySelector('[aria-current="step"]');
  if (activeDiscipline) {
    disciplineNav.scrollLeft = activeDiscipline.offsetLeft
      - (disciplineNav.clientWidth - activeDiscipline.offsetWidth) / 2;
  }
}

const resultsForm = document.querySelector("[data-live-results-form]");
if (resultsForm) {
  const resultInputs = Array.from(resultsForm.querySelectorAll("[data-live-result-input]"));
  let isDirty = false;

  resultInputs.forEach((input, index) => {
    input.addEventListener("input", () => {
      isDirty = true;
    });
    input.addEventListener("focus", () => input.select());
    input.addEventListener("keydown", (event) => {
      if (event.key !== "Enter") {
        return;
      }
      event.preventDefault();
      const nextInput = resultInputs[index + 1];
      if (nextInput) {
        nextInput.focus();
      } else {
        input.blur();
      }
    });
  });

  resultsForm.addEventListener("submit", () => {
    isDirty = false;
  });

  document.querySelectorAll("[data-live-leave]").forEach((link) => {
    link.addEventListener("click", (event) => {
      if (!isDirty) {
        return;
      }
      const message = resultsForm.dataset.unsavedMessage || "";
      if (message && !window.confirm(message)) {
        event.preventDefault();
      }
    });
  });
}
