(function () {
  function applyTableFilters() {
    var tables = document.querySelectorAll(".opentt-admin table.widefat");
    tables.forEach(function (table) {
      if (table.classList.contains("opentt-live-search-table")) {
        return;
      }
      if (table.dataset.openttFilterReady === "1") {
        return;
      }
      table.dataset.openttFilterReady = "1";
      var wrapper = document.createElement("div");
      wrapper.className = "opentt-table-filter";
      var input = document.createElement("input");
      input.type = "search";
      input.placeholder = "Quick table search...";
      input.setAttribute("aria-label", "Quick table search");
      wrapper.appendChild(input);
      table.parentNode.insertBefore(wrapper, table);

      input.addEventListener("input", function () {
        var q = input.value.toLowerCase().trim();
        var rows = table.querySelectorAll("tbody tr");
        rows.forEach(function (row) {
          var text = row.textContent.toLowerCase();
          row.style.display = !q || text.indexOf(q) !== -1 ? "" : "none";
        });
      });
    });
  }

  function initLiveAdminSearch() {
    function bind(input, table) {
      if (!input || !table || input.dataset.openttLiveBound === "1") {
        return;
      }
      var tbody = table.querySelector("tbody");
      if (!tbody) {
        return;
      }
      input.dataset.openttLiveBound = "1";

      var apply = function () {
        var q = String(input.value || "").toLowerCase().trim();
        var rows = tbody.querySelectorAll("tr");
        rows.forEach(function (row) {
          var text = String(row.textContent || "").toLowerCase();
          var match = !q || text.indexOf(q) !== -1;
          row.style.display = match ? "" : "none";
          row.classList.toggle("opentt-live-hit", !!q && match);
        });
      };

      ["input", "keyup", "search", "change"].forEach(function (evt) {
        input.addEventListener(evt, apply);
      });
      apply();
    }

    var directInputs = document.querySelectorAll(".opentt-live-search-input[data-opentt-live-target]");
    directInputs.forEach(function (input) {
      var targetId = input.getAttribute("data-opentt-live-target");
      if (!targetId) {
        return;
      }
      bind(input, document.getElementById(targetId));
    });

    var fallbackInputs = document.querySelectorAll('input[type="search"][name="club_search"], input[type="search"][name="player_search"], input[type="search"][name="competition_search"]');
    fallbackInputs.forEach(function (input) {
      if (input.dataset.openttLiveBound === "1") {
        return;
      }
      var wrap = input.closest(".wrap.opentt-admin");
      if (!wrap) {
        return;
      }
      var table = wrap.querySelector("table.widefat");
      bind(input, table);
    });
  }

  function initWizards() {
    var forms = document.querySelectorAll(".opentt-wizard-form");
    forms.forEach(function (form) {
      var stepsCount = parseInt(form.getAttribute("data-opentt-steps") || "1", 10);
      if (!stepsCount || stepsCount < 2) {
        return;
      }
      var rows = form.querySelectorAll("[data-opentt-step]");
      if (!rows.length) {
        return;
      }
      var stepPills = form.querySelectorAll(".opentt-step-pill");
      var prevBtn = form.querySelector(".opentt-wizard-prev");
      var nextBtn = form.querySelector(".opentt-wizard-next");
      var submitBtn = form.querySelector(".opentt-wizard-submit");
      var help = form.querySelector(".opentt-wizard-help");
      var current = 1;

      function render() {
        rows.forEach(function (row) {
          var step = parseInt(row.getAttribute("data-opentt-step") || "1", 10);
          if (step === current) {
            row.classList.remove("opentt-hidden-row");
          } else {
            row.classList.add("opentt-hidden-row");
          }
        });
        stepPills.forEach(function (pill, idx) {
          pill.classList.toggle("is-active", idx + 1 === current);
        });
        if (prevBtn) {
          prevBtn.disabled = current === 1;
        }
        if (nextBtn) {
          nextBtn.style.display = current >= stepsCount ? "none" : "";
        }
        if (submitBtn) {
          submitBtn.style.display = current >= stepsCount ? "" : "none";
        }
        if (help) {
          help.textContent = "Step " + current + " of " + stepsCount;
        }
      }

      if (prevBtn) {
        prevBtn.addEventListener("click", function () {
          if (current > 1) {
            current--;
            render();
          }
        });
      }
      if (nextBtn) {
        nextBtn.addEventListener("click", function () {
          if (current < stepsCount) {
            current++;
            render();
          }
        });
      }

      render();
    });
  }

  function initPlayerPicker() {
    var modal = document.getElementById("opentt-player-picker-modal");
    if (!modal) {
      return;
    }
    modal.setAttribute("hidden", "hidden");

    var closeBtn = modal.querySelector(".opentt-player-picker-close");
    var search = modal.querySelector(".opentt-player-picker-search");
    var list = modal.querySelector(".opentt-player-picker-list");
    var activeSelect = null;

    function closeModal() {
      modal.setAttribute("hidden", "hidden");
      activeSelect = null;
      if (search) {
        search.value = "";
      }
    }

    if (closeBtn) {
      closeBtn.addEventListener("click", function () {
        closeModal();
      });
    }

    function renderList(query) {
      if (!list) {
        return;
      }
      var q = (query || "").toLowerCase().trim();
      var items = list.querySelectorAll(".opentt-player-picker-item");
      var anyVisible = false;
      items.forEach(function (item) {
        var name = String(item.getAttribute("data-player-name") || "").toLowerCase();
        var fullText = String(item.textContent || "").toLowerCase();
        var visible = !q || name.indexOf(q) !== -1 || fullText.indexOf(q) !== -1;
        item.hidden = !visible;
        if (visible) {
          anyVisible = true;
        }
      });

      var empty = list.querySelector(".opentt-player-picker-empty");
      if (!empty) {
        empty = document.createElement("div");
        empty.className = "opentt-player-picker-empty";
        empty.textContent = "No search results found.";
        list.appendChild(empty);
      }
      empty.hidden = anyVisible || !q;
    }

    function openForSelect(selectEl) {
      if (!selectEl) {
        return;
      }
      activeSelect = selectEl;
      modal.removeAttribute("hidden");
      renderList("");
      if (search) {
        search.focus();
      }
    }

    document.addEventListener("click", function (e) {
      var openBtn = e.target.closest(".opentt-player-picker-open");
      if (openBtn) {
        var targetId = openBtn.getAttribute("data-target-select");
        var sel = targetId ? document.getElementById(targetId) : null;
        if (sel) {
          openForSelect(sel);
        }
        return;
      }

      var item = e.target.closest(".opentt-player-picker-item");
      if (item && activeSelect) {
        var playerId = String(item.getAttribute("data-player-id") || "");
        var playerName = String(item.getAttribute("data-player-name") || "");
        if (playerId) {
          var opt = activeSelect.querySelector(
            'option[value="' + playerId.replace(/"/g, '\\"') + '"]'
          );
          if (!opt) {
            opt = document.createElement("option");
            opt.value = playerId;
            opt.textContent = playerName + " (van trenutnog kluba)";
            activeSelect.appendChild(opt);
          }
          activeSelect.value = playerId;
          activeSelect.dispatchEvent(new Event("change", { bubbles: true }));
        }
        closeModal();
        return;
      }

      if (e.target === modal || e.target.closest(".opentt-player-picker-close")) {
        closeModal();
      }
    });

    if (search) {
      search.addEventListener("input", function () {
        renderList(search.value || "");
      });
    }

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && !modal.hasAttribute("hidden")) {
        closeModal();
      }
    });
  }

  function initHelpGuideModal() {
    var modal = document.getElementById("opentt-help-modal");
    if (!modal) {
      return;
    }

    function closeModal() {
      modal.setAttribute("hidden", "hidden");
    }

    function openModal() {
      modal.removeAttribute("hidden");
    }

    document.addEventListener("click", function (e) {
      var openBtn = e.target.closest(".opentt-help-open");
      if (openBtn) {
        openModal();
        return;
      }
      var closeBtn = e.target.closest(".opentt-help-close");
      if (closeBtn) {
        closeModal();
      }
    });

    modal.addEventListener("click", function (e) {
      if (e.target === modal) {
        closeModal();
      }
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && !modal.hasAttribute("hidden")) {
        closeModal();
      }
    });
  }

  function initCodeEditors() {
    if (!window.wp || !wp.codeEditor || !window.openttCodeEditorSettings) {
      return;
    }
    document.querySelectorAll(".opentt-settings-css-editor").forEach(function (el) {
      if (el.dataset.openttCodeEditorReady === "1") {
        return;
      }
      wp.codeEditor.initialize(el, window.openttCodeEditorSettings);
      el.dataset.openttCodeEditorReady = "1";
    });
  }

  function initColorPickers() {
    if (!window.jQuery || !jQuery.fn || !jQuery.fn.wpColorPicker) {
      return;
    }
    jQuery(".opentt-color-field").each(function () {
      var el = jQuery(this);
      if (el.data("openttColorReady") === 1) {
        return;
      }
      el.wpColorPicker();
      el.data("openttColorReady", 1);
    });
  }

  function initPlayersBulkSelection() {
    function bindBulkSelection(tableId, headerId, checkboxSelector) {
      var table = document.getElementById(tableId);
      if (!table) {
        return;
      }

      var checkAll = document.getElementById(headerId);
      var boxes = Array.prototype.slice.call(
        table.querySelectorAll(checkboxSelector)
      );
      if (!boxes.length) {
        return;
      }

      var lastChecked = null;

      function syncHeader() {
        if (!checkAll) {
          return;
        }
        var checkedCount = boxes.filter(function (b) {
          return b.checked;
        }).length;
        checkAll.checked = checkedCount === boxes.length;
        checkAll.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
      }

      boxes.forEach(function (box) {
        box.addEventListener("click", function (e) {
          if (e.shiftKey && lastChecked && lastChecked !== box) {
            var start = boxes.indexOf(lastChecked);
            var end = boxes.indexOf(box);
            if (start > -1 && end > -1) {
              var min = Math.min(start, end);
              var max = Math.max(start, end);
              for (var i = min; i <= max; i++) {
                boxes[i].checked = box.checked;
              }
            }
          }
          lastChecked = box;
          syncHeader();
        });

        box.addEventListener("change", syncHeader);
      });

      if (checkAll) {
        checkAll.addEventListener("change", function () {
          boxes.forEach(function (box) {
            box.checked = checkAll.checked;
          });
          syncHeader();
        });
      }

      syncHeader();
    }

    bindBulkSelection(
      "opentt-players-table",
      "opentt-players-check-all",
      "input.opentt-player-bulk-checkbox[type='checkbox']"
    );
    bindBulkSelection(
      "opentt-clubs-table",
      "opentt-clubs-check-all",
      "input.opentt-club-bulk-checkbox[type='checkbox']"
    );
    bindBulkSelection(
      "opentt-matches-table",
      "opentt-matches-check-all",
      "input.opentt-match-bulk-checkbox[type='checkbox']"
    );
  }

  document.addEventListener("DOMContentLoaded", function () {
    applyTableFilters();
    initLiveAdminSearch();
    initWizards();
    initPlayerPicker();
    initHelpGuideModal();
    initCodeEditors();
    initColorPickers();
    initPlayersBulkSelection();
  });
})();
