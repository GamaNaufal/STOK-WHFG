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
    const selectedList = document.getElementById("deliveryAssignSelectedList");
    const newBoxNumberInput = document.getElementById(
        "deliveryAssignNewBoxNumber",
    );
    const newBoxPartInput = document.getElementById("deliveryAssignNewBoxPart");
    const newBoxQtyInput = document.getElementById("deliveryAssignNewBoxQty");
    const addNewBoxBtn = document.getElementById("deliveryAssignAddNewBox");
    const newBoxError = document.getElementById("deliveryAssignNewBoxError");

    if (!searchInput || !searchBtn || !palletList || !boxList || !assignBtn) {
        return;
    }

    const selectedPallets = new Map();
    const selectedBoxes = new Map();
    const scannedBoxes = new Map();
    const selectionOrder = [];

    function escapeHtml(input) {
        return String(input ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function makeKey(type, value) {
        return `${type}:${value}`;
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

    function showToastMessage(message, type) {
        if (typeof window.showToast === "function") {
            window.showToast(message, type);
        }
    }

    function hideResult() {
        if (!resultBox) {
            return;
        }
        resultBox.style.display = "none";
    }

    function updateSummary() {
        const palletCountValue = selectedPallets.size;
        const boxCountValue = selectedBoxes.size;
        const scannedCountValue = scannedBoxes.size;
        const text =
            palletCountValue === 0 &&
            boxCountValue === 0 &&
            scannedCountValue === 0
                ? "Belum ada box/pallet dipilih."
                : `Pallet: ${palletCountValue}, Box stok: ${boxCountValue}, Box baru: ${scannedCountValue}`;
        if (summary) {
            summary.textContent = text;
        }
    }

    function showNewBoxError(message) {
        if (!newBoxError) {
            return;
        }
        newBoxError.textContent = message;
        newBoxError.style.display = "block";
    }

    function hideNewBoxError() {
        if (!newBoxError) {
            return;
        }
        newBoxError.style.display = "none";
        newBoxError.textContent = "";
    }

    function addToOrder(entry) {
        if (!selectionOrder.some((item) => item.key === entry.key)) {
            selectionOrder.push(entry);
        }
    }

    function removeFromOrder(key) {
        const index = selectionOrder.findIndex((item) => item.key === key);
        if (index >= 0) {
            selectionOrder.splice(index, 1);
        }
    }

    function updateCheckboxState(type, id, checked) {
        const selector = `input[data-type="${type}"][data-id="${id}"]`;
        const checkbox = document.querySelector(selector);
        if (checkbox) {
            checkbox.checked = checked;
        }
    }

    function renderSelectedList() {
        if (!selectedList) {
            return;
        }

        selectedList.innerHTML = "";

        if (selectionOrder.length === 0) {
            selectedList.innerHTML =
                '<div class="list-group-item text-muted">Belum ada box/pallet dipilih.</div>';
            return;
        }

        selectionOrder.forEach((entry) => {
            if (entry.type === "pallet") {
                const pallet = selectedPallets.get(entry.id);
                if (!pallet) {
                    return;
                }

                const item = document.createElement("div");
                item.className = "list-group-item";
                item.innerHTML = `
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div>
                            <div class="fw-semibold">Pallet ${escapeHtml(
                                pallet.pallet_number || "-",
                            )}</div>
                            <div class="small text-muted">Lokasi: ${escapeHtml(
                                pallet.location || "-",
                            )}</div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-action="toggle">Lihat box</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-action="remove">Hapus</button>
                        </div>
                    </div>
                    <div class="mt-2" data-role="pallet-boxes" style="display: none;"></div>
                `;

                const removeBtn = item.querySelector('[data-action="remove"]');
                const toggleBtn = item.querySelector('[data-action="toggle"]');
                const detailBox = item.querySelector('[data-role="pallet-boxes"]');

                removeBtn.addEventListener("click", () => {
                    selectedPallets.delete(entry.id);
                    removeFromOrder(entry.key);
                    updateCheckboxState("pallet", entry.id, false);
                    updateSummary();
                    renderSelectedList();
                });

                toggleBtn.addEventListener("click", () => {
                    if (!detailBox) {
                        return;
                    }

                    const isHidden = detailBox.style.display === "none";
                    detailBox.style.display = isHidden ? "block" : "none";

                    if (isHidden && detailBox.dataset.loaded !== "1") {
                        loadPalletBoxes(entry.id, detailBox, toggleBtn);
                    }
                });

                selectedList.appendChild(item);
                return;
            }

            if (entry.type === "box") {
                const box = selectedBoxes.get(entry.id);
                if (!box) {
                    return;
                }

                const item = document.createElement("div");
                item.className = "list-group-item";
                item.innerHTML = `
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div>
                            <div class="fw-semibold">Box ${escapeHtml(
                                box.box_number || "-",
                            )} - ${escapeHtml(box.part_number || "-")}</div>
                            <div class="small text-muted">Pallet: ${escapeHtml(
                                box.pallet_number || "-",
                            )} | Lokasi: ${escapeHtml(
                                box.location || "-",
                            )} | PCS: ${escapeHtml(box.pcs_quantity ?? "0")}</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-action="remove">Hapus</button>
                    </div>
                `;

                const removeBtn = item.querySelector('[data-action="remove"]');
                removeBtn.addEventListener("click", () => {
                    selectedBoxes.delete(entry.id);
                    removeFromOrder(entry.key);
                    updateCheckboxState("box", entry.id, false);
                    updateSummary();
                    renderSelectedList();
                });

                selectedList.appendChild(item);
                return;
            }

            if (entry.type === "new_box") {
                const box = scannedBoxes.get(entry.box_number);
                if (!box) {
                    return;
                }

                const item = document.createElement("div");
                item.className = "list-group-item";
                item.innerHTML = `
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div>
                            <div class="fw-semibold">Box Baru ${escapeHtml(
                                box.box_number,
                            )} - ${escapeHtml(box.part_number)}</div>
                            <div class="small text-muted">Qty PCS: ${escapeHtml(
                                box.pcs_quantity,
                            )}</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-action="remove">Hapus</button>
                    </div>
                `;

                const removeBtn = item.querySelector('[data-action="remove"]');
                removeBtn.addEventListener("click", () => {
                    scannedBoxes.delete(entry.box_number);
                    removeFromOrder(entry.key);
                    updateSummary();
                    renderSelectedList();
                });

                selectedList.appendChild(item);
            }
        });
    }

    function loadPalletBoxes(palletId, container) {
        if (!config.palletBoxesUrl) {
            container.innerHTML =
                '<div class="small text-danger">Config pallet boxes tidak tersedia.</div>';
            return;
        }

        const url = config.palletBoxesUrl
            .replace("__PALLET__", String(palletId))
            .replace("%7B%7BPALLET%7D%7D", String(palletId));

        container.innerHTML =
            '<div class="small text-muted">Memuat isi pallet...</div>';

        fetch(`${url}?limit=60`)
            .then((response) => {
                if (!response.ok) {
                    throw new Error("Gagal memuat isi pallet.");
                }
                return response.json();
            })
            .then((data) => {
                const total = Number(data.total || 0);
                const boxes = Array.isArray(data.boxes) ? data.boxes : [];
                const limit = Number(data.limit || boxes.length || 0);

                let html = `<div class="small text-muted mb-2">Total box: ${total}. Ditampilkan: ${boxes.length}.</div>`;

                if (boxes.length === 0) {
                    html +=
                        '<div class="small text-muted">Tidak ada box eligible di pallet.</div>';
                } else {
                    html += '<ul class="mb-0">';
                    boxes.forEach((box) => {
                        html += `<li>Box ${escapeHtml(
                            box.box_number || "-",
                        )} - ${escapeHtml(box.part_number || "-")} (PCS: ${escapeHtml(
                            box.pcs_quantity ?? "0",
                        )})</li>`;
                    });
                    html += "</ul>";
                }

                if (total > limit) {
                    html +=
                        '<div class="small text-muted mt-2">Menampilkan sebagian box. Perkecil filter untuk melihat lebih banyak.</div>';
                }

                container.innerHTML = html;
                container.dataset.loaded = "1";
            })
            .catch((error) => {
                container.innerHTML =
                    `<div class="small text-danger">${escapeHtml(
                        error.message,
                    )}</div>`;
            });
    }

    function renderPallets(pallets) {
        palletList.innerHTML = "";

        if (!Array.isArray(pallets) || pallets.length === 0) {
            palletList.innerHTML =
                '<div class="list-group-item text-muted">Tidak ada pallet ditemukan.</div>';
            if (palletCount) {
                palletCount.textContent = "0";
            }
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
            checkbox.checked = selectedPallets.has(palletId);
            checkbox.addEventListener("change", (event) => {
                if (event.target.checked) {
                    selectedPallets.set(palletId, {
                        id: palletId,
                        pallet_number: pallet.pallet_number || "-",
                        location: pallet.location || "-",
                    });
                    addToOrder({
                        key: makeKey("pallet", palletId),
                        type: "pallet",
                        id: palletId,
                    });
                } else {
                    selectedPallets.delete(palletId);
                    removeFromOrder(makeKey("pallet", palletId));
                }
                updateSummary();
                renderSelectedList();
            });

            palletList.appendChild(item);
        });

        if (palletCount) {
            palletCount.textContent = String(pallets.length);
        }
    }

    function renderBoxes(boxes) {
        boxList.innerHTML = "";

        if (!Array.isArray(boxes) || boxes.length === 0) {
            boxList.innerHTML =
                '<div class="list-group-item text-muted">Tidak ada box ditemukan.</div>';
            if (boxCount) {
                boxCount.textContent = "0";
            }
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
            checkbox.checked = selectedBoxes.has(boxId);
            checkbox.addEventListener("change", (event) => {
                if (event.target.checked) {
                    selectedBoxes.set(boxId, {
                        id: boxId,
                        box_number: box.box_number || "-",
                        part_number: box.part_number || "-",
                        pallet_number: box.pallet_number || "-",
                        location: box.location || "-",
                        pcs_quantity: box.pcs_quantity ?? 0,
                    });
                    addToOrder({
                        key: makeKey("box", boxId),
                        type: "box",
                        id: boxId,
                    });
                } else {
                    selectedBoxes.delete(boxId);
                    removeFromOrder(makeKey("box", boxId));
                }
                updateSummary();
                renderSelectedList();
            });

            boxList.appendChild(item);
        });

        if (boxCount) {
            boxCount.textContent = String(boxes.length);
        }
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
        selectedPallets.clear();
        selectedBoxes.clear();
        scannedBoxes.clear();
        selectionOrder.length = 0;
        updateSummary();
        renderSelectedList();
        hideNewBoxError();
        performSearch();
    }

    function addNewBox() {
        hideNewBoxError();

        const deliveryOrderId = deliveryOrderSelect?.value;
        if (!deliveryOrderId) {
            showNewBoxError("Pilih delivery order terlebih dahulu.");
            return;
        }

        const boxNumber = String(newBoxNumberInput?.value || "").trim();
        const partNumber = String(newBoxPartInput?.value || "").trim();
        const qtyValue = String(newBoxQtyInput?.value || "").trim();

        if (!boxNumber || !partNumber || !qtyValue) {
            showNewBoxError("Lengkapi ID Box, No Part, dan Qty PCS.");
            return;
        }

        if (!/^[0-9]+$/.test(boxNumber)) {
            showNewBoxError("ID Box hanya boleh angka.");
            return;
        }

        const pcsQuantity = Number(qtyValue);
        if (!Number.isFinite(pcsQuantity) || pcsQuantity <= 0) {
            showNewBoxError("Qty PCS harus lebih dari 0.");
            return;
        }

        if (scannedBoxes.has(boxNumber)) {
            showNewBoxError("ID Box sudah ada di daftar scan.");
            return;
        }

        const duplicateSelected = Array.from(selectedBoxes.values()).some(
            (box) => String(box.box_number || "") === boxNumber,
        );
        if (duplicateSelected) {
            showNewBoxError("ID Box sudah dipilih dari stok existing.");
            return;
        }

        scannedBoxes.set(boxNumber, {
            box_number: boxNumber,
            part_number: partNumber,
            pcs_quantity: pcsQuantity,
        });

        addToOrder({
            key: makeKey("new_box", boxNumber),
            type: "new_box",
            box_number: boxNumber,
        });

        if (newBoxNumberInput) {
            newBoxNumberInput.value = "";
        }
        if (newBoxPartInput) {
            newBoxPartInput.value = "";
        }
        if (newBoxQtyInput) {
            newBoxQtyInput.value = "";
        }

        updateSummary();
        renderSelectedList();
    }

    function executeAssignment(payload) {
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
                        let message = err.message || "Gagal assign.";
                        if (Array.isArray(err.new_box_errors)) {
                            const details = err.new_box_errors
                                .map((entry) => {
                                    const boxLabel =
                                        entry.box_number ||
                                        entry.part_number ||
                                        "-";
                                    const reason = entry.reason || "-";
                                    return `${boxLabel}: ${reason}`;
                                })
                                .join("; ");
                            if (details) {
                                message += " Detail: " + details;
                            }
                        }
                        throw new Error(message);
                    });
                }
                return response.json();
            })
            .then((data) => {
                const assigned = Number(data.assigned_existing_count || 0);
                const created = Number(data.created_new_count || 0);
                const skipped = Number(data.skipped_count || 0);
                const toastMessage =
                    `Assign selesai. Existing: ${assigned}, ` +
                    `Box baru: ${created}, Skipped: ${skipped}.`;
                let html = `<div class="fw-semibold">${toastMessage}</div>`;

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
                showToastMessage(toastMessage, "success");
                clearSelection();
            })
            .catch((error) => {
                showResult("danger", error.message);
                showToastMessage(error.message, "danger");
            })
            .finally(() => {
                assignBtn.disabled = false;
                assignBtn.textContent = "Assign Delivery";
            });
    }

    function submitAssignment() {
        hideResult();

        const deliveryOrderId = deliveryOrderSelect?.value;
        if (!deliveryOrderId) {
            showResult("warning", "Pilih delivery order terlebih dahulu.");
            return;
        }

        if (
            selectedPallets.size === 0 &&
            selectedBoxes.size === 0 &&
            scannedBoxes.size === 0
        ) {
            showResult(
                "warning",
                "Pilih minimal satu box/pallet atau scan box baru.",
            );
            return;
        }

        const payload = {
            delivery_order_id: Number(deliveryOrderId),
            box_ids: Array.from(selectedBoxes.keys()),
            pallet_ids: Array.from(selectedPallets.keys()),
            new_boxes: Array.from(scannedBoxes.values()).map((box) => ({
                box_number: box.box_number,
                part_number: box.part_number,
                pcs_quantity: box.pcs_quantity,
            })),
        };

        const infoText =
            `Pallet: ${selectedPallets.size}, ` +
            `Box stok: ${selectedBoxes.size}, ` +
            `Box baru: ${scannedBoxes.size}.`;

        const proceed = () => executeAssignment(payload);

        if (
            window.WarehouseAlert &&
            typeof window.WarehouseAlert.confirm === "function"
        ) {
            window.WarehouseAlert.confirm({
                title: "Konfirmasi Assign",
                message:
                    "Assign ini tidak bisa di-rewind. Pastikan daftar box/pallet sudah benar.",
                warningItems: [
                    "Assign tidak dapat dibatalkan setelah diproses.",
                    "Periksa kembali daftar box/pallet sebelum lanjut.",
                ],
                infoText,
                confirmText: "Ya, Assign",
                cancelText: "Batal",
                confirmColor: "#0C7779",
                onConfirm: proceed,
            });
        } else if (window.confirm("Assign ini tidak bisa di-rewind. Lanjutkan?")) {
            proceed();
        }
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

    if (addNewBoxBtn) {
        addNewBoxBtn.addEventListener("click", addNewBox);
    }

    [newBoxNumberInput, newBoxPartInput, newBoxQtyInput]
        .filter(Boolean)
        .forEach((input) => {
            input.addEventListener("keydown", (event) => {
                if (event.key === "Enter") {
                    event.preventDefault();
                    addNewBox();
                }
            });
        });

    updateSummary();
    renderSelectedList();
})();
