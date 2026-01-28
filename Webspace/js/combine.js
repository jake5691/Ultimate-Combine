    const disciplineSelect = document.querySelector("[data-discipline-select]");
    if (disciplineSelect) {
      let isDirty = false;
      let lastValue = disciplineSelect.value;
      const resultInputs = document.querySelectorAll(".result-input");
      resultInputs.forEach((input) => {
        input.addEventListener("input", () => {
          isDirty = true;
        });
      });

      disciplineSelect.addEventListener("change", () => {
        const nextValue = disciplineSelect.value;
        if (isDirty) {
          const confirmMessage = disciplineSelect.dataset.confirmUnsaved || "Ungesicherte Aenderungen gehen verloren. Trotzdem wechseln?";
          const ok = window.confirm(confirmMessage);
          if (!ok) {
            disciplineSelect.value = lastValue;
            return;
          }
        }
        const combineId = disciplineSelect.dataset.combineId;
        window.location.href = `combine.php?id=${combineId}&mode=start&discipline_id=${encodeURIComponent(nextValue)}`;
      });

      disciplineSelect.addEventListener("focus", () => {
        lastValue = disciplineSelect.value;
      });
    }

    const drawRadarChart = (radarDataEl, radarCanvas) => {
      const data = JSON.parse(radarDataEl.textContent || "[]");
      if (!data.length) return;
      const drawRadar = () => {
        const container = radarCanvas.parentElement;
        if (!container) return;
        const rect = container.getBoundingClientRect();
        const size = Math.max(0, Math.floor(rect.width));
        if (!size) return;
        const dpi = window.devicePixelRatio || 1;
        radarCanvas.width = size * dpi;
        radarCanvas.height = size * dpi;
        radarCanvas.style.width = `${size}px`;
        radarCanvas.style.height = `${size}px`;

        const ctx = radarCanvas.getContext("2d");
        ctx.setTransform(dpi, 0, 0, dpi, 0, 0);

        const rootStyles = getComputedStyle(document.documentElement);
        const accent = rootStyles.getPropertyValue("--accent").trim() || "#ff7b4b";
        const accent2 = rootStyles.getPropertyValue("--accent-2").trim() || "#2c2a4a";
        const ink = rootStyles.getPropertyValue("--ink").trim() || "#1f1a14";
        const muted = rootStyles.getPropertyValue("--muted").trim() || "#6f6259";

        const center = size / 2;
        const radius = center - 60;
        const labelOffset = 6;
        const maxValue = 2;
        const midValue = 1;
        const midRatio = 0.4;
        const upperRings = 3;
        const angleStep = (Math.PI * 2) / data.length;

        ctx.clearRect(0, 0, size, size);
        ctx.save();
        ctx.translate(center, center);

        ctx.strokeStyle = "rgba(44, 42, 74, 0.2)";
        ctx.lineWidth = 1;
        const rings = [midRatio];
        for (let i = 1; i <= upperRings; i += 1) {
          rings.push(midRatio + (i / (upperRings + 1)) * (1 - midRatio));
        }
        rings.push(1);
        rings.forEach((ratio) => {
          const r = radius * ratio;
          ctx.beginPath();
          for (let i = 0; i < data.length; i += 1) {
            const angle = i * angleStep - Math.PI / 2;
            const x = Math.cos(angle) * r;
            const y = Math.sin(angle) * r;
            if (i === 0) {
              ctx.moveTo(x, y);
            } else {
              ctx.lineTo(x, y);
            }
          }
          ctx.closePath();
          ctx.stroke();
        });

        ctx.strokeStyle = "rgba(44, 42, 74, 0.25)";
        for (let i = 0; i < data.length; i += 1) {
          const angle = i * angleStep - Math.PI / 2;
          ctx.beginPath();
          ctx.moveTo(0, 0);
          ctx.lineTo(Math.cos(angle) * radius, Math.sin(angle) * radius);
          ctx.stroke();
        }

        const normalizeValue = (value) => {
          if (value <= midValue) {
            return (value / midValue) * midRatio;
          }
          return midRatio + ((value - midValue) / (maxValue - midValue)) * (1 - midRatio);
        };

        const drawShape = (values, stroke, fill) => {
          ctx.beginPath();
          values.forEach((value, index) => {
            const normalized = Math.max(0, Math.min(normalizeValue(value), 1));
            const angle = index * angleStep - Math.PI / 2;
            const x = Math.cos(angle) * radius * normalized;
            const y = Math.sin(angle) * radius * normalized;
            if (index === 0) {
              ctx.moveTo(x, y);
            } else {
              ctx.lineTo(x, y);
            }
          });
          ctx.closePath();
          ctx.fillStyle = fill;
          ctx.strokeStyle = stroke;
          ctx.lineWidth = 2;
          ctx.fill();
          ctx.stroke();
        };

        const hasCompare = data.some((item) => Object.prototype.hasOwnProperty.call(item, "playerB"));
        const hasTeam = data.some((item) => Object.prototype.hasOwnProperty.call(item, "team"));
        if (hasCompare) {
          const playerValues = data.map((item) => item.player || 0);
          const compareValues = data.map((item) => item.playerB || 0);
          if (hasTeam) {
            const teamValues = data.map((item) => item.team || 0);
            drawShape(teamValues, muted, "rgba(111, 98, 89, 0.18)");
          }
          drawShape(compareValues, accent2, "rgba(44, 42, 74, 0.2)");
          drawShape(playerValues, accent, "rgba(255, 123, 75, 0.22)");
        } else {
          const teamValues = data.map((item) => item.team || 0);
          const playerValues = data.map((item) => item.player || 0);
          drawShape(teamValues, accent2, "rgba(44, 42, 74, 0.15)");
          drawShape(playerValues, accent, "rgba(255, 123, 75, 0.22)");
        }

        ctx.fillStyle = ink;
        ctx.font = "12px \"Space Grotesk\", sans-serif";
        data.forEach((item, index) => {
          const angle = index * angleStep - Math.PI / 2;
          const x = Math.cos(angle) * (radius + labelOffset);
          const y = Math.sin(angle) * (radius + labelOffset);
          ctx.textAlign = x > 5 ? "left" : x < -5 ? "right" : "center";
          ctx.textBaseline = y > 5 ? "top" : y < -5 ? "bottom" : "middle";
          ctx.fillStyle = muted;
          ctx.fillText(item.label, x, y);
        });
        ctx.restore();
      };

      const drawBarChart = () => {
        const container = radarCanvas.parentElement;
        if (!container) return;
        const rect = container.getBoundingClientRect();
        const size = Math.max(0, Math.floor(rect.width));
        if (!size) return;
        const dpi = window.devicePixelRatio || 1;
        radarCanvas.width = size * dpi;
        radarCanvas.height = size * dpi;
        radarCanvas.style.width = `${size}px`;
        radarCanvas.style.height = `${size}px`;

        const ctx = radarCanvas.getContext("2d");
        ctx.setTransform(dpi, 0, 0, dpi, 0, 0);

        const rootStyles = getComputedStyle(document.documentElement);
        const accent = rootStyles.getPropertyValue("--accent").trim() || "#ff7b4b";
        const accent2 = rootStyles.getPropertyValue("--accent-2").trim() || "#2c2a4a";
        const ink = rootStyles.getPropertyValue("--ink").trim() || "#1f1a14";
        const muted = rootStyles.getPropertyValue("--muted").trim() || "#6f6259";

        const maxValue = 2;
        const hasCompare = data.some((item) => Object.prototype.hasOwnProperty.call(item, "playerB"));
        const hasTeam = data.some((item) => Object.prototype.hasOwnProperty.call(item, "team"));
        const series = [];
        if (hasTeam) {
          series.push({ key: "team", color: "rgba(111, 98, 89, 0.35)", stroke: muted });
        }
        if (hasCompare) {
          series.push({ key: "playerB", color: "rgba(44, 42, 74, 0.25)", stroke: accent2, size: 0.7 });
        }
        series.push({ key: "player", color: "rgba(255, 123, 75, 0.25)", stroke: accent, size: 0.8 });

        const padding = 18;
        const rowCount = data.length;
        const groupGap = 18;
        const barHeight = Math.max(8, Math.floor((size - padding * 2 - (rowCount - 1) * groupGap) / rowCount));
        const labelFont = "12px \"Space Grotesk\", sans-serif";

        ctx.clearRect(0, 0, size, size);
        ctx.font = labelFont;
        ctx.textBaseline = "middle";

        let maxLabelWidth = 0;
        data.forEach((item) => {
          maxLabelWidth = Math.max(maxLabelWidth, ctx.measureText(item.label).width);
        });
        const labelWidth = Math.min(maxLabelWidth + 8, Math.max(120, size * 0.35));
        const chartX = padding + labelWidth;
        const chartWidth = size - padding - chartX;

        const truncateText = (text, width) => {
          if (ctx.measureText(text).width <= width) return text;
          const ellipsis = "…";
          let trimmed = text;
          while (trimmed.length > 0 && ctx.measureText(trimmed + ellipsis).width > width) {
            trimmed = trimmed.slice(0, -1);
          }
          return trimmed.length ? trimmed + ellipsis : text;
        };

        ctx.strokeStyle = "rgba(44, 42, 74, 0.18)";
        ctx.lineWidth = 1;
        [0.5, 1, 1.5, 2].forEach((tick) => {
          const x = chartX + (tick / maxValue) * chartWidth;
          ctx.beginPath();
          ctx.moveTo(x, padding - 6);
          ctx.lineTo(x, size - padding + 6);
          ctx.stroke();
        });

        let y = padding;
        data.forEach((item) => {
          const label = truncateText(item.label, labelWidth - 8);
          const groupHeight = barHeight;
          const groupCenter = y + groupHeight / 2;
          ctx.fillStyle = muted;
          ctx.textAlign = "right";
          ctx.fillText(label, chartX - 10, groupCenter);

          const teamBarH = Math.max(4, Math.floor(barHeight * 0.55));
          const playerBarH = Math.max(3, Math.floor(teamBarH * 0.8));
          const compareBarH = Math.max(3, Math.floor(teamBarH * 0.7));
          const teamBarY = groupCenter - teamBarH / 2;
          const playerBarY = groupCenter - playerBarH / 2;
          const compareBarY = groupCenter - compareBarH / 2;

          series.forEach((serie, index) => {
            const rawValue = Number(item[serie.key] || 0);
            const value = Math.max(0, Math.min(rawValue, maxValue));
            const barWidth = (value / maxValue) * chartWidth;
            const barH = serie.key === "playerB" ? compareBarH : (serie.key === "player" ? playerBarH : teamBarH);
            const barY = serie.key === "playerB" ? compareBarY : (serie.key === "player" ? playerBarY : teamBarY);
            ctx.fillStyle = serie.color;
            ctx.strokeStyle = serie.stroke;
            ctx.lineWidth = 1.5;
            ctx.beginPath();
            ctx.rect(chartX, barY, barWidth, barH);
            ctx.fill();
            ctx.stroke();
          });

          y += groupHeight + groupGap;
        });
      };

      const resizeRadar = () => {
        window.requestAnimationFrame(() => {
          if (data.length <= 2) {
            drawBarChart();
          } else {
            drawRadar();
          }
        });
      };

      if (data.length <= 2) {
        drawBarChart();
      } else {
        drawRadar();
      }
      window.addEventListener("resize", resizeRadar);
    };

    const radarDataEl = document.getElementById("radar-data");
    const radarCanvas = document.getElementById("radar-chart");
    if (radarDataEl && radarCanvas) {
      drawRadarChart(radarDataEl, radarCanvas);
    }

    const radarDataH2h = document.getElementById("radar-data-h2h");
    const radarCanvasH2h = document.getElementById("radar-chart-h2h");
    if (radarDataH2h && radarCanvasH2h) {
      drawRadarChart(radarDataH2h, radarCanvasH2h);
    }

    const h2hPlayerA = document.querySelector("select[name=\"player_a\"]");
    const h2hPlayerB = document.querySelector("select[name=\"player_b\"]");
    if (h2hPlayerA && h2hPlayerB) {
      const syncH2hOptions = () => {
        const aValue = h2hPlayerA.value;
        const bValue = h2hPlayerB.value;
        Array.from(h2hPlayerA.options).forEach((option) => {
          option.disabled = option.value !== "" && option.value === bValue;
        });
        Array.from(h2hPlayerB.options).forEach((option) => {
          option.disabled = option.value !== "" && option.value === aValue;
        });
        if (aValue !== "" && aValue === bValue) {
          h2hPlayerB.value = "";
        }
      };
      h2hPlayerA.addEventListener("change", syncH2hOptions);
      h2hPlayerB.addEventListener("change", syncH2hOptions);
      syncH2hOptions();
    }

    const toggleButtons = document.querySelectorAll("[data-target]");
    toggleButtons.forEach((button) => {
      const targetId = button.getAttribute("data-target");
      const target = targetId ? document.getElementById(targetId) : null;
      if (!target) return;
      button.addEventListener("click", () => {
        const isHidden = target.classList.toggle("is-hidden");
        button.setAttribute("aria-expanded", String(!isHidden));
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
  
