(function () {
    const config = window.deliveryAssignConfig || {};

    const deliveryOrderSelect = document.getElementById("deliveryOrderSelect");
    const searchInput = document.getElementById("deliveryAssignSearchInput");
    const searchBtn = document.getElementById("deliveryAssignSearchBtn");
    const palletList = document.getElementById("deliveryAssignPalletList");
    const boxList = document.getElementById("deliveryAssignBoxList");
    const palletCount = document.getElementById("deliveryAssignPalletCount");
    const boxCount = document.getElementById("deliveryAssignBoxCount");
    const summary = document.getElementById("deliveryAssignSummary");
    const assignBtn = document.getElementById("deliveryAssignSubmit");
    const clearBtn = document.getElementById("deliveryAssignClearBtn");
    const resultBox = document.getElementById("deliveryAssignResult");

    if (!searchInput || !searchBtn || !palletList || !boxList || !assignBtn) {
        return;
    }

    const selectedPalletIds = new Set();
    const selectedBoxIds = new Set();

    function escapeHtml(input) {
        return String(input ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function showResult(type, message) {
        if (!resultBox) {
            return;
        }
        resultBox.className = `alert alert-${type}`;
        resultBox.textContent = message;
        resultBox.style.display = "block";
    }

    function showResultHtml(type, html) {
        if (!resultBox) {
            return;
        }
        resultBox.className = `alert alert-${type}`;
        resultBox.innerHTML = html;
        resultBox.style.display = "block";
    }

    function hideResult() {
        if (!resultBox) {
            return;
        }
        resultBox.style.display = "none";
    }

    function updateSummary() {
        const palletCountValue = selectedPalletIds.size;
        const boxCountValue = selectedBoxIds.size;
        const text =
            palletCountValue === 0 && boxCountValue === 0
                ? "Belum ada box/pallet dipilih."
                : `Pallet dipilih: ${palletCountValue}, Box dipilih: ${boxCountValue}`;
        summary.textContent = text;
    }

    function renderPallets(pallets) {
        palletList.innerHTML = "";

        if (!Array.isArray(pallets) || pallets.length === 0) {
            palletList.innerHTML =
                '<div class="list-group-item text-muted">Tidak ada pallet ditemukan.</div>';
            palletCount.textContent = "0";
            return;
        }

        pallets.forEach((pallet) => {
            const palletId = Number(pallet.id);
            const item = document.createElement("label");
            item.className = "list-group-item d-flex gap-2 align-items-start";
            item.innerHTML = `
                <input class="form-check-input mt-1" type="checkbox" data-type="pallet" data-id="${palletId}">
                <div>
                    <div class="fw-semibold">${escapeHtml(
                        pallet.pallet_number || "-",
                    )}</div>
                    <div class="small text-muted">Lokasi: ${escapeHtml(
                        pallet.location || "-",
                    )} | Eligible box: ${escapeHtml(
                        pallet.eligible_boxes ?? "0",
                    )}</div>
                </div>
            `;

            const checkbox = item.querySelector("input");
            checkbox.checked = selectedPalletIds.has(palletId);
            checkbox.addEventListener("change", (event) => {
                if (event.target.checked) {
                    selectedPalletIds.add(palletId);
                } else {
                    selectedPalletIds.delete(palletId);
                }
                updateSummary();
            });

            palletList.appendChild(item);
        });

        palletCount.textContent = String(pallets.length);
    }

    function renderBoxes(boxes) {
        boxList.innerHTML = "";

        if (!Array.isArray(boxes) || boxes.length === 0) {
            boxList.innerHTML =
                '<div class="list-group-item text-muted">Tidak ada box ditemukan.</div>';
            boxCount.textContent = "0";
            return;
        }

        boxes.forEach((box) => {
            const boxId = Number(box.id);
            const item = document.createElement("label");
            item.className = "list-group-item d-flex gap-2 align-items-start";
            item.innerHTML = `
                <input class="form-check-input mt-1" type="checkbox" data-type="box" data-id="${boxId}">
                <div>
                    <div class="fw-semibold">${escapeHtml(
                        box.box_number || "-",
                    )} - ${escapeHtml(box.part_number || "-")}</div>
                    <div class="small text-muted">Pallet: ${escapeHtml(
                        box.pallet_number || "-",
                    )} | Lokasi: ${escapeHtml(
                        box.location || "-",
                    )} | PCS: ${escapeHtml(box.pcs_quantity ?? "0")}</div>
                </div>
            `;

            const checkbox = item.querySelector("input");
            checkbox.checked = selectedBoxIds.has(boxId);
            checkbox.addEventListener("change", (event) => {
                if (event.target.checked) {
                    selectedBoxIds.add(boxId);
                } else {
                    selectedBoxIds.delete(boxId);
                }
                updateSummary();
            });

            boxList.appendChild(item);
        });

        boxCount.textContent = String(boxes.length);
    }

    function performSearch(options = {}) {
        const strict = options.strict === true;
        hideResult();
        const query = String(searchInput.value || "").trim();

        palletList.innerHTML =
            '<div class="list-group-item text-muted">Mencari pallet...</div>';
        boxList.innerHTML =
            '<div class="list-group-item text-muted">Mencari box...</div>';

        const url = config.searchUrl + "?q=" + encodeURIComponent(query);

        return fetch(url)
            .then((response) => {
                if (!response.ok) {
                    throw new Error("Gagal mengambil data.");
                }
                return response.json();
            })
            .then((data) => {
                renderPallets(data.pallets || []);
                renderBoxes(data.boxes || []);
                return data;
            })
            .catch((error) => {
                palletList.innerHTML =
                    '<div class="list-group-item text-danger">' +
                    escapeHtml(error.message) +
                    "</div>";
                boxList.innerHTML =
                    '<div class="list-group-item text-danger">' +
                    escapeHtml(error.message) +
                    "</div>";
                if (strict) {
                    throw error;
                }
                return null;
            });
    }

    function clearSelection() {
        selectedPalletIds.clear();
        selectedBoxIds.clear();
        updateSummary();
        performSearch();
    }

    function submitAssignment() {
        hideResult();

        const deliveryOrderId = deliveryOrderSelect?.value;
        if (!deliveryOrderId) {
            showResult("warning", "Pilih delivery order terlebih dahulu.");
            return;
        }

        if (selectedPalletIds.size === 0 && selectedBoxIds.size === 0) {
            showResult("warning", "Pilih minimal satu box atau pallet.");
            return;
        }

        const payload = {
            delivery_order_id: Number(deliveryOrderId),
            box_ids: Array.from(selectedBoxIds),
            pallet_ids: Array.from(selectedPalletIds),
        };

        assignBtn.disabled = true;
        assignBtn.textContent = "Memproses...";

        performSearch({ strict: true })
            .then(() =>
                fetch(config.assignUrl, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": config.csrfToken,
                    },
                    body: JSON.stringify(payload),
                }),
            )
            .then((response) => {
                if (!response.ok) {
                    return response.json().then((err) => {
                        throw new Error(err.message || "Gagal assign.");
                    });
                }
                return response.json();
            })
            .then((data) => {
                const assigned = Number(data.assigned_count || 0);
                const skipped = Number(data.skipped_count || 0);
                let html = `<div class="fw-semibold">Assign selesai. Assigned: ${assigned}, Skipped: ${skipped}.</div>`;

                if (Array.isArray(data.skipped) && data.skipped.length > 0) {
                    html +=
                        '<div class="mt-2">Detail skipped:</div><ul class="mb-0">';
                    data.skipped.forEach((entry) => {
                        const boxLabel =
                            entry.box_number || entry.box_id || "-";
                        const reason = entry.reason || "-";
                        html += `<li>Box ${escapeHtml(
                            boxLabel,
                        )}: ${escapeHtml(reason)}</li>`;
                    });
                    html += "</ul>";
                }

                showResultHtml("success", html);
                clearSelection();
            })
            .catch((error) => {
                showResult("danger", error.message);
            })
            .finally(() => {
                assignBtn.disabled = false;
                assignBtn.textContent = "Assign Delivery";
            });
    }

    searchBtn.addEventListener("click", () => performSearch());
    searchInput.addEventListener("keydown", (event) => {
        if (event.key === "Enter") {
            event.preventDefault();
            performSearch();
        }
    });

    if (clearBtn) {
        clearBtn.addEventListener("click", clearSelection);
    }

    assignBtn.addEventListener("click", submitAssignment);

    updateSummary();
})();
