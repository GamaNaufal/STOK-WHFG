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
    const newBoxPartSelect = document.getElementById(
        "deliveryAssignNewBoxPart",
    );
    const newBoxQtyInput = document.getElementById("deliveryAssignNewBoxQty");
    const addNewBoxBtn = document.getElementById("deliveryAssignAddNewBox");
    const newBoxError = document.getElementById("deliveryAssignNewBoxError");
    const partStatus = document.getElementById("deliveryAssignPartStatus");

    const palletModeNew = document.getElementById(
        "deliveryAssignPalletModeNew",
    );
    const palletModeExisting = document.getElementById(
        "deliveryAssignPalletModeExisting",
    );
    const newPalletNumberSection = document.getElementById(
        "deliveryAssignNewPalletNumberSection",
    );
    const existingPalletSection = document.getElementById(
        "deliveryAssignExistingPalletSection",
    );
    const newPalletNumberInput = document.getElementById(
        "deliveryAssignNewPalletNumber",
    );
    const existingPalletSearchInput = document.getElementById(
        "deliveryAssignExistingPalletSearchInput",
    );
    const existingPalletSearchResults = document.getElementById(
        "deliveryAssignExistingPalletSearchResults",
    );
    const selectedExistingPalletId = document.getElementById(
        "deliveryAssignSelectedExistingPalletId",
    );
    const selectedExistingPalletText = document.getElementById(
        "deliveryAssignSelectedExistingPalletText",
    );

    const locationSection = document.getElementById(
        "deliveryAssignLocationSection",
    );
    const locationSearchInput = document.getElementById(
        "deliveryAssignLocationSearchInput",
    );
    const locationSearchResults = document.getElementById(
        "deliveryAssignLocationSearchResults",
    );
    const locationDropdownBtn = document.getElementById(
        "deliveryAssignLocationDropdownBtn",
    );
    const selectedLocationId = document.getElementById(
        "deliveryAssignLocationId",
    );
    const selectedLocationCode = document.getElementById(
        "deliveryAssignLocationCode",
    );

    if (!searchInput || !searchBtn || !palletList || !boxList || !assignBtn) {
        return;
    }

    const selectedPallets = new Map();
    const selectedBoxes = new Map();
    const scannedBoxes = new Map();
    const selectionOrder = [];
    const deliveryOrderParts = new Map();
    const gateState = {
        enabled: false,
        lastDeliveryOrderId: deliveryOrderSelect?.value || "",
    };
    const draftKey = `delivery-assign-draft:v1:${Number(config.userId) || "unknown"}`;
    let draftReady = false;
    let previewInFlight = false;
    let assignmentInFlight = false;

    function escapeHtml(input) {
        return String(input ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function persistDraft() {
        if (!draftReady) {
            return;
        }

        try {
            const orderId = String(deliveryOrderSelect?.value || "");
            if (!orderId) {
                window.sessionStorage.removeItem(draftKey);
                return;
            }

            window.sessionStorage.setItem(
                draftKey,
                JSON.stringify({
                    orderId,
                    selectedPallets: Array.from(selectedPallets.values()),
                    selectedBoxes: Array.from(selectedBoxes.values()),
                    scannedBoxes: Array.from(scannedBoxes.values()),
                    selectionOrder,
                    palletMode: getNewBoxPalletMode(),
                    existingPalletId: selectedExistingPalletId?.value || "",
                    existingPalletText: selectedExistingPalletText?.value || "",
                    locationId: selectedLocationId?.value || "",
                    locationCode: selectedLocationCode?.value || "",
                }),
            );
        } catch (_) {
            // Assignment remains usable when browser storage is unavailable.
        }
    }

    function readDraft() {
        try {
            const draft = JSON.parse(window.sessionStorage.getItem(draftKey) || "null");
            if (!draft || !Array.from(deliveryOrderSelect?.options || []).some((option) => option.value === String(draft.orderId))) {
                window.sessionStorage.removeItem(draftKey);
                return null;
            }
            return draft;
        } catch (_) {
            return null;
        }
    }

    function restoreDraft(draft) {
        if (!draft) {
            return false;
        }

        deliveryOrderSelect.value = String(draft.orderId);
        gateState.lastDeliveryOrderId = deliveryOrderSelect.value;

        const mode = draft.palletMode === "existing" ? "existing" : "new";
        if (palletModeNew) palletModeNew.checked = mode === "new";
        if (palletModeExisting) palletModeExisting.checked = mode === "existing";
        setNewBoxPalletModeUI(mode);

        if (mode === "existing") {
            if (selectedExistingPalletId) selectedExistingPalletId.value = String(draft.existingPalletId || "");
            if (selectedExistingPalletText) selectedExistingPalletText.value = String(draft.existingPalletText || "");
        } else {
            if (selectedLocationId) selectedLocationId.value = String(draft.locationId || "");
            if (selectedLocationCode) selectedLocationCode.value = String(draft.locationCode || "");
            if (locationSearchInput) locationSearchInput.value = String(draft.locationCode || "");
        }

        (Array.isArray(draft.selectedPallets) ? draft.selectedPallets : []).forEach((pallet) => {
            const id = Number(pallet?.id);
            if (Number.isInteger(id) && id > 0) selectedPallets.set(id, pallet);
        });
        (Array.isArray(draft.selectedBoxes) ? draft.selectedBoxes : []).forEach((box) => {
            const id = Number(box?.id);
            if (Number.isInteger(id) && id > 0) selectedBoxes.set(id, box);
        });
        (Array.isArray(draft.scannedBoxes) ? draft.scannedBoxes : []).forEach((box) => {
            const number = String(box?.box_number || "");
            if (/^[0-9]{8}$/.test(number) && Number(box?.pcs_quantity) > 0) scannedBoxes.set(number, box);
        });
        (Array.isArray(draft.selectionOrder) ? draft.selectionOrder : []).forEach((entry) => {
            if (entry?.type === "pallet" && selectedPallets.has(Number(entry.id))) {
                addToOrder({ key: makeKey("pallet", Number(entry.id)), type: "pallet", id: Number(entry.id) });
            } else if (entry?.type === "box" && selectedBoxes.has(Number(entry.id))) {
                addToOrder({ key: makeKey("box", Number(entry.id)), type: "box", id: Number(entry.id) });
            } else if (entry?.type === "new_box" && scannedBoxes.has(String(entry.box_number))) {
                addToOrder({ key: makeKey("new_box", String(entry.box_number)), type: "new_box", box_number: String(entry.box_number) });
            }
        });
        return true;
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

    function showGatePlaceholders() {
        if (partStatus) {
            partStatus.className = "alert alert-info mt-3 mb-0 py-2 small";
            partStatus.textContent =
                "Pilih delivery order untuk melihat part yang masih bisa di-assign.";
            partStatus.style.display = "block";
        }
        if (palletList) {
            palletList.innerHTML =
                '<div class="list-group-item text-muted">Pilih delivery order terlebih dahulu.</div>';
        }
        if (boxList) {
            boxList.innerHTML =
                '<div class="list-group-item text-muted">Pilih delivery order terlebih dahulu.</div>';
        }
        if (palletCount) {
            palletCount.textContent = "0";
        }
        if (boxCount) {
            boxCount.textContent = "0";
        }
        if (newBoxPartSelect) {
            newBoxPartSelect.innerHTML =
                '<option value="">Pilih delivery order terlebih dahulu</option>';
            newBoxPartSelect.disabled = true;
        }

        if (newPalletNumberInput) {
            newPalletNumberInput.value = "";
        }
        if (existingPalletSearchInput) {
            existingPalletSearchInput.value = "";
        }
        if (selectedExistingPalletId) {
            selectedExistingPalletId.value = "";
        }
        if (selectedExistingPalletText) {
            selectedExistingPalletText.value = "";
        }
        if (existingPalletSearchResults) {
            existingPalletSearchResults.style.display = "none";
            existingPalletSearchResults.innerHTML = "";
        }
        if (locationSearchInput) {
            locationSearchInput.value = "";
        }
        if (selectedLocationId) {
            selectedLocationId.value = "";
        }
        if (selectedLocationCode) {
            selectedLocationCode.value = "";
        }
        if (locationSearchResults) {
            locationSearchResults.style.display = "none";
            locationSearchResults.innerHTML = "";
        }

        deliveryOrderParts.clear();
    }

    function setGateState(enabled) {
        gateState.enabled = enabled;
        const disabled = !enabled;
        [
            searchInput,
            searchBtn,
            newBoxNumberInput,
            newBoxPartSelect,
            newBoxQtyInput,
            addNewBoxBtn,
            assignBtn,
            palletModeNew,
            palletModeExisting,
            newPalletNumberInput,
            existingPalletSearchInput,
            locationSearchInput,
            locationDropdownBtn,
        ].forEach((element) => {
            if (!element) {
                return;
            }
            element.disabled = disabled;
        });

        if (!enabled) {
            showGatePlaceholders();
        }
    }

    function renderDeliveryOrderParts(parts) {
        if (!newBoxPartSelect) {
            return;
        }

        newBoxPartSelect.innerHTML = "";
        if (newBoxQtyInput) {
            newBoxQtyInput.value = "";
            newBoxQtyInput.readOnly = true;
        }

        if (!Array.isArray(parts) || parts.length === 0) {
            newBoxPartSelect.innerHTML =
                '<option value="">Tidak ada part tersisa untuk delivery order ini</option>';
            newBoxPartSelect.disabled = true;
            if (partStatus) {
                partStatus.className =
                    "alert alert-warning mt-3 mb-0 py-2 small";
                partStatus.textContent =
                    "Semua part pada delivery order ini sudah habis atau tidak tersisa untuk assign.";
                partStatus.style.display = "block";
            }
            return;
        }

        newBoxPartSelect.disabled = !gateState.enabled;
        newBoxPartSelect.innerHTML =
            '<option value="">-- Pilih No Part --</option>';

        if (partStatus) {
            partStatus.className = "alert alert-success mt-3 mb-0 py-2 small";
            partStatus.textContent = `${parts.length} part masih tersedia untuk delivery order ini.`;
            partStatus.style.display = "block";
        }

        parts.forEach((part) => {
            const partNumber = String(part.part_number || "");
            const requestedQuantity = Number(part.requested_quantity || 0);
            const assignedQuantity = Number(part.assigned_quantity || 0);
            const remainingQuantity = Number(part.remaining_quantity || 0);
            const fixedQuantity = Number(part.qty_box || 0);

            const option = document.createElement("option");
            option.value = partNumber;
            option.textContent = `${partNumber} (fixed ${fixedQuantity}, sisa ${remainingQuantity}/${requestedQuantity})`;
            option.dataset.requestedQuantity = String(requestedQuantity);
            option.dataset.assignedQuantity = String(assignedQuantity);
            option.dataset.remainingQuantity = String(remainingQuantity);
            option.dataset.fixedQuantity = String(fixedQuantity);
            newBoxPartSelect.appendChild(option);
        });
    }

    function loadDeliveryOrderParts(deliveryOrderId) {
        if (!config.deliveryOrderPartsUrl) {
            renderDeliveryOrderParts([]);
            return Promise.resolve([]);
        }

        if (!deliveryOrderId) {
            renderDeliveryOrderParts([]);
            return Promise.resolve([]);
        }

        const url = config.deliveryOrderPartsUrl.replace(
            "__ORDER__",
            String(deliveryOrderId),
        );

        if (newBoxPartSelect) {
            newBoxPartSelect.disabled = true;
            newBoxPartSelect.innerHTML =
                '<option value="">Memuat part delivery order...</option>';
        }

        return fetch(url)
            .then((response) => {
                if (!response.ok) {
                    throw new Error("Gagal memuat part delivery order.");
                }
                return response.json();
            })
            .then((data) => {
                const parts = Array.isArray(data.parts) ? data.parts : [];
                deliveryOrderParts.clear();
                parts.forEach((part) => {
                    deliveryOrderParts.set(
                        String(part.part_number || ""),
                        part,
                    );
                });
                renderDeliveryOrderParts(parts);
                return parts;
            })
            .catch((error) => {
                deliveryOrderParts.clear();
                renderDeliveryOrderParts([]);
                if (partStatus) {
                    partStatus.className =
                        "alert alert-danger mt-3 mb-0 py-2 small";
                    partStatus.textContent = error.message;
                    partStatus.style.display = "block";
                }
                throw error;
            });
    }

    function hasSelections() {
        return (
            selectedPallets.size > 0 ||
            selectedBoxes.size > 0 ||
            scannedBoxes.size > 0
        );
    }

    function confirmChoice({ title, message, confirmText, danger = false }, onConfirm, onCancel = null) {
        if (window.Swal && typeof window.Swal.fire === "function") {
            window.Swal.fire({
                titleText: title,
                text: message,
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: confirmText,
                cancelButtonText: "Batal",
                confirmButtonColor: danger ? "#b53a40" : "#0b7072",
                reverseButtons: true,
                focusCancel: true,
            }).then((result) => {
                if (result.isConfirmed) onConfirm();
                else if (onCancel) onCancel();
            });
            return;
        }

        if (window.confirm(`${title}\n\n${message}`)) onConfirm();
        else if (onCancel) onCancel();
    }

    function confirmDeliveryOrderChange(onConfirm, onCancel) {
        confirmChoice({
            title: "Ganti Delivery Order?",
            message: "Pilihan box dan pallet untuk order saat ini akan dikosongkan. Stok tidak akan dihapus.",
            confirmText: "Ya, ganti order",
        }, onConfirm, onCancel);
    }

    function confirmChoiceRemoval(label, onConfirm, onCancel = null) {
        confirmChoice({
            title: `Hapus ${label} dari pilihan?`,
            message: `${label} hanya dikeluarkan dari draft Delivery Assign. Data box dan stok gudang tidak dihapus.`,
            confirmText: "Hapus dari pilihan",
            danger: true,
        }, onConfirm, onCancel);
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
        persistDraft();
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

    function getNewBoxPalletMode() {
        if (palletModeExisting && palletModeExisting.checked) {
            return "existing";
        }
        return "new";
    }

    function setNewBoxPalletModeUI(mode) {
        if (existingPalletSection) {
            existingPalletSection.style.display =
                mode === "existing" ? "block" : "none";
        }
        if (newPalletNumberSection) {
            newPalletNumberSection.style.display =
                mode === "existing" ? "none" : "block";
        }
        if (locationSection) {
            locationSection.style.display =
                mode === "existing" ? "none" : "block";
        }

        if (mode === "existing") {
            if (newPalletNumberInput) {
                newPalletNumberInput.value = "";
            }
            if (locationSearchInput) {
                locationSearchInput.value = "";
            }
            if (selectedLocationId) {
                selectedLocationId.value = "";
            }
            if (selectedLocationCode) {
                selectedLocationCode.value = "";
            }
            if (locationSearchResults) {
                locationSearchResults.style.display = "none";
                locationSearchResults.innerHTML = "";
            }
            return;
        }

        if (existingPalletSearchInput) {
            existingPalletSearchInput.value = "";
        }
        if (selectedExistingPalletId) {
            selectedExistingPalletId.value = "";
        }
        if (selectedExistingPalletText) {
            selectedExistingPalletText.value = "";
        }
        if (existingPalletSearchResults) {
            existingPalletSearchResults.style.display = "none";
            existingPalletSearchResults.innerHTML = "";
        }
    }

    function validateNewBoxPalletInputs(options = {}) {
        const { silent = false } = options;
        const mode = getNewBoxPalletMode();

        if (mode === "existing") {
            const palletId = String(
                selectedExistingPalletId?.value || "",
            ).trim();
            if (!palletId) {
                if (!silent) {
                    showNewBoxError(
                        "Pilih pallet existing untuk input box baru.",
                    );
                }
                return null;
            }
            return {
                mode,
                pallet_id: Number(palletId),
            };
        }

        const locationId = String(selectedLocationId?.value || "").trim();
        const locationCode = String(selectedLocationCode?.value || "").trim();
        if (!locationId || !locationCode) {
            if (!silent) {
                showNewBoxError("Pilih lokasi untuk pallet baru.");
            }
            return null;
        }

        return {
            mode,
            location_id: Number(locationId),
            location_code: locationCode,
        };
    }

    function searchExistingPallets(query) {
        if (!existingPalletSearchResults || !config.newBoxPalletSearchUrl) {
            return;
        }

        existingPalletSearchResults.style.display = "block";
        existingPalletSearchResults.innerHTML =
            '<div class="list-group-item text-muted">Mencari pallet...</div>';

        fetch(
            config.newBoxPalletSearchUrl +
                "?q=" +
                encodeURIComponent(query || ""),
            {
                headers: {
                    "X-CSRF-TOKEN": config.csrfToken,
                },
            },
        )
            .then((response) => {
                if (!response.ok) {
                    throw new Error("Gagal mencari pallet.");
                }
                return response.json();
            })
            .then((data) => {
                const rows = Array.isArray(data?.pallets) ? data.pallets : [];
                existingPalletSearchResults.innerHTML = "";

                if (rows.length === 0) {
                    existingPalletSearchResults.innerHTML =
                        '<div class="list-group-item text-muted">Pallet tidak ditemukan.</div>';
                    return;
                }

                rows.forEach((row) => {
                    const item = document.createElement("a");
                    item.href = "#";
                    item.className = "list-group-item list-group-item-action";
                    item.innerHTML = `<div class="d-flex justify-content-between align-items-center">
                                        <strong>${escapeHtml(row.pallet_number)}</strong>
                                        <span class="badge bg-secondary">${Number(row.total_boxes || 0)} box</span>
                                      </div>
                                      <small class="text-muted">Lokasi: ${escapeHtml(row.warehouse_location || "-")}</small>`;
                    item.onclick = (e) => {
                        e.preventDefault();
                        if (selectedExistingPalletId) {
                            selectedExistingPalletId.value = String(row.id);
                        }
                        if (selectedExistingPalletText) {
                            selectedExistingPalletText.value = `${row.pallet_number} (Lokasi: ${row.warehouse_location || "-"})`;
                        }
                        existingPalletSearchResults.style.display = "none";
                        persistDraft();
                    };
                    existingPalletSearchResults.appendChild(item);
                });
            })
            .catch((error) => {
                existingPalletSearchResults.innerHTML =
                    '<div class="list-group-item text-danger">' +
                    escapeHtml(error.message) +
                    "</div>";
            });
    }

    function performLocationSearch(query) {
        if (!locationSearchResults || !config.locationSearchUrl) {
            return;
        }

        locationSearchResults.style.display = "block";
        locationSearchResults.innerHTML =
            '<div class="list-group-item text-muted">Mencari lokasi...</div>';

        fetch(
            config.locationSearchUrl + "?q=" + encodeURIComponent(query || ""),
        )
            .then((res) => res.json())
            .then((rows) => {
                locationSearchResults.innerHTML = "";
                if (Array.isArray(rows) && rows.length > 0) {
                    rows.forEach((loc) => {
                        const item = document.createElement("a");
                        item.href = "#";
                        item.className =
                            "list-group-item list-group-item-action";
                        item.innerHTML = `<div class="d-flex justify-content-between align-items-center">
                                            <strong>${escapeHtml(loc.code)}</strong>
                                            <span class="badge bg-success rounded-pill" style="font-size: 0.7em;">Available</span>
                                          </div>`;
                        item.onclick = (e) => {
                            e.preventDefault();
                            if (locationSearchInput) {
                                locationSearchInput.value = String(
                                    loc.code || "",
                                );
                            }
                            if (selectedLocationId) {
                                selectedLocationId.value = String(loc.id || "");
                            }
                            if (selectedLocationCode) {
                                selectedLocationCode.value = String(
                                    loc.code || "",
                                );
                            }
                            locationSearchResults.style.display = "none";
                            persistDraft();
                        };
                        locationSearchResults.appendChild(item);
                    });
                    return;
                }

                locationSearchResults.innerHTML =
                    '<div class="list-group-item text-muted">Tidak ada lokasi tersedia.</div>';
            })
            .catch(() => {
                locationSearchResults.style.display = "none";
            });
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

        selectionOrder.forEach((entry, position) => {
            if (entry.type === "pallet") {
                const pallet = selectedPallets.get(entry.id);
                if (!pallet) {
                    return;
                }

                const item = document.createElement("div");
                item.className = "list-group-item delivery-assign-selected-item";
                item.innerHTML = `
                    <div class="delivery-assign-selected-row">
                        <span class="delivery-assign-position" aria-label="Pilihan ${position + 1}">${position + 1}</span>
                        <div class="delivery-assign-selected-main">
                            <span class="delivery-assign-item-type">Pallet stok</span>
                            <strong class="delivery-assign-item-id">${escapeHtml(pallet.pallet_number || "-")}</strong>
                            <span class="delivery-assign-item-meta"><span><small>Lokasi</small> ${escapeHtml(pallet.location || "-")}</span></span>
                        </div>
                        <div class="delivery-assign-selected-actions">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-action="toggle" aria-expanded="false" aria-controls="deliveryAssignPalletBoxes-${entry.id}">Lihat box</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-action="remove" aria-label="Hapus pallet ${escapeHtml(pallet.pallet_number || "-")} dari pilihan">Hapus</button>
                        </div>
                    </div>
                    <div class="delivery-assign-pallet-details" id="deliveryAssignPalletBoxes-${entry.id}" data-role="pallet-boxes" style="display: none;"></div>
                `;

                const removeBtn = item.querySelector('[data-action="remove"]');
                const toggleBtn = item.querySelector('[data-action="toggle"]');
                const detailBox = item.querySelector(
                    '[data-role="pallet-boxes"]',
                );

                removeBtn.addEventListener("click", () => {
                    confirmChoiceRemoval(`pallet ${pallet.pallet_number || "-"}`, () => {
                        selectedPallets.delete(entry.id);
                        removeFromOrder(entry.key);
                        updateCheckboxState("pallet", entry.id, false);
                        updateSummary();
                        renderSelectedList();
                    });
                });

                toggleBtn.addEventListener("click", () => {
                    if (!detailBox) {
                        return;
                    }

                    const isHidden = detailBox.style.display === "none";
                    detailBox.style.display = isHidden ? "block" : "none";
                    toggleBtn.setAttribute("aria-expanded", String(isHidden));
                    toggleBtn.textContent = isHidden ? "Sembunyikan box" : "Lihat box";

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
                item.className = "list-group-item delivery-assign-selected-item";
                item.innerHTML = `
                    <div class="delivery-assign-selected-row">
                        <span class="delivery-assign-position" aria-label="Pilihan ${position + 1}">${position + 1}</span>
                        <div class="delivery-assign-selected-main">
                            <span class="delivery-assign-item-type">Box stok</span>
                            <strong class="delivery-assign-item-id">${escapeHtml(box.box_number || "-")}</strong>
                            <div class="delivery-assign-item-meta"><span><small>Part</small> ${escapeHtml(box.part_number || "-")}</span><span><small>Pallet</small> ${escapeHtml(box.pallet_number || "-")}</span><span><small>Lokasi</small> ${escapeHtml(box.location || "-")}</span><span><small>PCS</small> ${escapeHtml(box.pcs_quantity ?? "0")}</span></div>
                        </div>
                        <div class="delivery-assign-selected-actions">
                            <button type="button" class="btn btn-sm btn-outline-danger" data-action="remove" aria-label="Hapus box ${escapeHtml(box.box_number || "-")} dari pilihan">Hapus</button>
                        </div>
                    </div>
                `;

                const removeBtn = item.querySelector('[data-action="remove"]');
                removeBtn.addEventListener("click", () => {
                    confirmChoiceRemoval(`box ${box.box_number || "-"}`, () => {
                        selectedBoxes.delete(entry.id);
                        removeFromOrder(entry.key);
                        updateCheckboxState("box", entry.id, false);
                        updateSummary();
                        renderSelectedList();
                    });
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
                item.className = "list-group-item delivery-assign-selected-item";
                item.innerHTML = `
                    <div class="delivery-assign-selected-row">
                        <span class="delivery-assign-position" aria-label="Pilihan ${position + 1}">${position + 1}</span>
                        <div class="delivery-assign-selected-main">
                            <span class="delivery-assign-item-type">Box baru</span>
                            <strong class="delivery-assign-item-id">${escapeHtml(box.box_number)}</strong>
                            <div class="delivery-assign-item-meta"><span><small>Part</small> ${escapeHtml(box.part_number)}</span><span><small>PCS</small> ${escapeHtml(box.pcs_quantity)}</span></div>
                        </div>
                        <div class="delivery-assign-selected-actions">
                            <button type="button" class="btn btn-sm btn-outline-danger" data-action="remove" aria-label="Hapus box baru ${escapeHtml(box.box_number)} dari pilihan">Hapus</button>
                        </div>
                    </div>
                `;

                const removeBtn = item.querySelector('[data-action="remove"]');
                removeBtn.addEventListener("click", () => {
                    confirmChoiceRemoval(`box baru ${box.box_number || "-"}`, () => {
                        scannedBoxes.delete(entry.box_number);
                        removeFromOrder(entry.key);
                        updateSummary();
                        renderSelectedList();
                    });
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

        const deliveryOrderId = String(deliveryOrderSelect?.value || "");
        if (!deliveryOrderId) {
            container.innerHTML =
                '<div class="small text-muted">Pilih delivery order terlebih dahulu.</div>';
            return;
        }

        const url = config.palletBoxesUrl
            .replace("__PALLET__", String(palletId))
            .replace("%7B%7BPALLET%7D%7D", String(palletId));

        container.innerHTML =
            '<div class="small text-muted">Memuat isi pallet...</div>';

        fetch(
            `${url}?limit=60&delivery_order_id=${encodeURIComponent(deliveryOrderId)}`,
        )
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

                let html = `<div class="delivery-assign-pallet-detail-count">${boxes.length} dari ${total} box eligible ditampilkan</div>`;

                if (boxes.length === 0) {
                    html +=
                        '<div class="small text-muted">Tidak ada box eligible di pallet.</div>';
                } else {
                    html += '<ul class="delivery-assign-pallet-box-list">';
                    boxes.forEach((box) => {
                        html += `<li><strong>${escapeHtml(box.box_number || "-")}</strong><span>${escapeHtml(box.part_number || "-")}</span><span>${escapeHtml(box.pcs_quantity ?? "0")} PCS</span></li>`;
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
                container.innerHTML = `<div class="small text-danger">${escapeHtml(
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
            item.className = "list-group-item delivery-assign-stock-option";
            item.innerHTML = `
                <input class="form-check-input" type="checkbox" data-type="pallet" data-id="${palletId}">
                <div class="delivery-assign-stock-option-main">
                    <strong class="delivery-assign-item-id">${escapeHtml(pallet.pallet_number || "-")}</strong>
                    <div class="delivery-assign-item-meta"><span><small>Lokasi</small> ${escapeHtml(pallet.location || "-")}</span></div>
                </div>
                <span class="delivery-assign-stock-quantity">${escapeHtml(pallet.eligible_boxes ?? "0")} box eligible</span>
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
                    confirmChoiceRemoval(`pallet ${pallet.pallet_number || "-"}`, () => {
                        selectedPallets.delete(palletId);
                        removeFromOrder(makeKey("pallet", palletId));
                        updateSummary();
                        renderSelectedList();
                    }, () => { checkbox.checked = true; });
                    return;
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
            item.className = "list-group-item delivery-assign-stock-option";
            item.innerHTML = `
                <input class="form-check-input" type="checkbox" data-type="box" data-id="${boxId}">
                <div class="delivery-assign-stock-option-main">
                    <strong class="delivery-assign-item-id">${escapeHtml(box.box_number || "-")}</strong>
                    <div class="delivery-assign-item-meta"><span><small>Part</small> ${escapeHtml(box.part_number || "-")}</span><span><small>Pallet</small> ${escapeHtml(box.pallet_number || "-")}</span><span><small>Lokasi</small> ${escapeHtml(box.location || "-")}</span></div>
                </div>
                <span class="delivery-assign-stock-quantity">${escapeHtml(box.pcs_quantity ?? "0")} PCS</span>
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
                    confirmChoiceRemoval(`box ${box.box_number || "-"}`, () => {
                        selectedBoxes.delete(boxId);
                        removeFromOrder(makeKey("box", boxId));
                        updateSummary();
                        renderSelectedList();
                    }, () => { checkbox.checked = true; });
                    return;
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
        if (!options.preserveResult) {
            hideResult();
        }

        if (!deliveryOrderSelect?.value) {
            if (strict) {
                return Promise.reject(
                    new Error("Pilih delivery order terlebih dahulu."),
                );
            }
            showResult("warning", "Pilih delivery order terlebih dahulu.");
            showGatePlaceholders();
            return Promise.resolve(null);
        }

        const query = String(searchInput.value || "").trim();
        const deliveryOrderId = String(deliveryOrderSelect?.value || "");

        palletList.innerHTML =
            '<div class="list-group-item text-muted">Mencari pallet...</div>';
        boxList.innerHTML =
            '<div class="list-group-item text-muted">Mencari box...</div>';

        const url =
            config.searchUrl +
            "?q=" +
            encodeURIComponent(query) +
            "&delivery_order_id=" +
            encodeURIComponent(deliveryOrderId);

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

    function clearSelection(options = {}) {
        const { skipSearch = false, silent = false } = options;
        selectedPallets.clear();
        selectedBoxes.clear();
        scannedBoxes.clear();
        selectionOrder.length = 0;
        if (palletModeNew) palletModeNew.checked = true;
        if (palletModeExisting) palletModeExisting.checked = false;
        setNewBoxPalletModeUI("new");
        if (newPalletNumberInput) newPalletNumberInput.value = "";
        if (locationSearchInput) locationSearchInput.value = "";
        if (selectedLocationId) selectedLocationId.value = "";
        if (selectedLocationCode) selectedLocationCode.value = "";
        if (newBoxNumberInput) newBoxNumberInput.value = "";
        if (newBoxPartSelect) newBoxPartSelect.value = "";
        if (newBoxQtyInput) newBoxQtyInput.value = "";
        updateSummary();
        renderSelectedList();
        hideNewBoxError();

        if (skipSearch) {
            if (!deliveryOrderSelect?.value) {
                showGatePlaceholders();
            }
            return;
        }

        if (!deliveryOrderSelect?.value) {
            if (!silent) {
                showResult("warning", "Pilih delivery order terlebih dahulu.");
            }
            showGatePlaceholders();
            return;
        }

        performSearch();
    }

    function addNewBox() {
        hideNewBoxError();

        const deliveryOrderId = deliveryOrderSelect?.value;
        if (!deliveryOrderId) {
            showNewBoxError("Pilih delivery order terlebih dahulu.");
            return;
        }

        const palletInfo = validateNewBoxPalletInputs();
        if (!palletInfo) {
            return;
        }

        const partNumber = String(newBoxPartSelect?.value || "").trim();
        if (!partNumber) {
            showNewBoxError(
                "Pilih No Part dari delivery order terlebih dahulu.",
            );
            return;
        }

        const partInfo = deliveryOrderParts.get(partNumber);
        if (!partInfo || Number(partInfo.remaining_quantity || 0) <= 0) {
            showNewBoxError(
                "No Part tersebut tidak tersedia untuk delivery order ini.",
            );
            return;
        }

        const fixedQuantity = Number(partInfo.qty_box || 0);
        if (!Number.isFinite(fixedQuantity) || fixedQuantity <= 0) {
            showNewBoxError("Fixed qty Master Part tidak valid.");
            return;
        }

        const boxNumber = String(newBoxNumberInput?.value || "").trim();
        const qtyValue = String(newBoxQtyInput?.value || "").trim();

        if (!boxNumber || !partNumber || !qtyValue) {
            showNewBoxError("Lengkapi ID Box, No Part, dan Qty PCS.");
            return;
        }

        if (!/^[0-9]+$/.test(boxNumber)) {
            showNewBoxError("ID Box hanya boleh angka.");
            return;
        }

        if (boxNumber.length !== 8) {
            showNewBoxError("ID Box harus 8 angka.");
            return;
        }

        const pcsQuantity = Number(qtyValue);
        if (!Number.isFinite(pcsQuantity) || pcsQuantity <= 0) {
            showNewBoxError("Qty PCS harus lebih dari 0.");
            return;
        }

        if (pcsQuantity !== fixedQuantity) {
            showNewBoxError(
                pcsQuantity < fixedQuantity
                    ? "Box not full wajib diajukan melalui menu Request Box Not Full dan menunggu approval Supervisi."
                    : `Qty PCS harus sama dengan fixed qty Master Part (${fixedQuantity}).`,
            );
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
        if (newBoxPartSelect) {
            newBoxPartSelect.value = "";
        }
        if (newBoxQtyInput) {
            newBoxQtyInput.value = "";
        }

        updateSummary();
        renderSelectedList();
    }

    function executeAssignment(payload) {
        assignmentInFlight = true;
        assignBtn.disabled = true;
        assignBtn.textContent = "Memproses...";

        const requestPayload = { ...payload };

        const confirmOverage = (overageResponse) => {
            const overages = Array.isArray(overageResponse?.part_overages)
                ? overageResponse.part_overages
                : [];
            const formatQty = (value) => {
                const quantity = Number(value);
                return Number.isFinite(quantity)
                    ? quantity.toLocaleString("id-ID")
                    : "-";
            };
            const overageRows = overages.map((entry) => `
                <li class="delivery-assign-overage-item">
                    <div class="delivery-assign-overage-item-head">
                        <strong>Part ${escapeHtml(entry.part_number || "-")}</strong>
                        <span>+${formatQty(entry.overage_quantity)} PCS</span>
                    </div>
                    <div class="delivery-assign-overage-math">
                        <span>Sisa ${formatQty(entry.remaining_quantity)} PCS</span>
                        <span aria-hidden="true">+</span>
                        <span>Kelebihan ${formatQty(entry.overage_quantity)} PCS</span>
                        <span aria-hidden="true">=</span>
                        <strong>Dipilih ${formatQty(entry.selected_quantity)} PCS</strong>
                    </div>
                    <div class="delivery-assign-overage-request">
                        Request order: <strong>${formatQty(entry.requested_quantity)}</strong>
                        <span aria-hidden="true">→</span>
                        <strong>${formatQty(entry.new_requested_quantity)} PCS</strong>
                        <span class="delivery-assign-overage-assigned">(sudah di-assign ${formatQty(entry.assigned_quantity)} PCS)</span>
                    </div>
                </li>
            `).join("");
            const html = `
                <div class="delivery-assign-overage-body">
                    <p>Jumlah box yang dipilih melebihi sisa permintaan untuk ${overages.length || "satu atau lebih"} part. Kelebihan ini tidak akan dipotong otomatis.</p>
                    ${overageRows ? `<ul class="delivery-assign-overage-list">${overageRows}</ul>` : ""}
                    <p class="delivery-assign-overage-note">Jika dikonfirmasi, quantity request order akan naik sesuai kelebihan di atas. Server lalu memvalidasi ulang dan mencoba assign pilihan box. Stok belum ditarik pada tahap ini.</p>
                    <p class="delivery-assign-overage-cancel">Pilih “Kembali periksa” untuk mempertahankan pilihan tanpa mengubah order.</p>
                </div>`;

            const proceed = () =>
                executeAssignment({
                    ...requestPayload,
                    confirm_overage: true,
                });

            if (window.Swal && typeof window.Swal.fire === "function") {
                window.Swal.fire({
                    title: "Pilihan melebihi sisa order",
                    html,
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Tambah request & assign",
                    cancelButtonText: "Kembali periksa",
                    confirmButtonColor: "#9a6700",
                    reverseButtons: true,
                    focusCancel: true,
                    width: "min(42rem, calc(100vw - 2rem))",
                    customClass: { popup: "delivery-assign-overage" },
                }).then((result) => {
                    if (result.isConfirmed) proceed();
                });
                return;
            }

            const fallbackDetails = overages.map((entry) =>
                `Part ${entry.part_number || "-"}: dipilih ${formatQty(entry.selected_quantity)} PCS, sisa ${formatQty(entry.remaining_quantity)} PCS, kelebihan ${formatQty(entry.overage_quantity)} PCS; request order ${formatQty(entry.requested_quantity)} → ${formatQty(entry.new_requested_quantity)} PCS.`,
            ).join("\n");
            const fallbackMessage = `Pilihan melebihi sisa order.\n${fallbackDetails}\n\nJika dilanjutkan, quantity request order bertambah dan server mencoba assign pilihan box setelah validasi ulang. Stok belum ditarik.\n\nTambah request dan assign?`;

            if (window.confirm(fallbackMessage)) {
                proceed();
            }
        };

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
                        if (
                            response.status === 409 &&
                            err &&
                            err.requires_overage_confirmation
                        ) {
                            throw {
                                type: "overage_confirmation",
                                payload: err,
                            };
                        }

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
                clearSelection({ skipSearch: true });
                performSearch({ preserveResult: true });
            })
            .catch((error) => {
                if (error?.type === "overage_confirmation") {
                    confirmOverage(error.payload);
                    return;
                }

                showResult("danger", error.message);
                showToastMessage(error.message, "danger");
            })
            .finally(() => {
                assignmentInFlight = false;
                assignBtn.disabled = false;
                assignBtn.textContent = "Assign Delivery";
            });
    }

    async function previewAssignment(payload, palletInfo) {
        const response = await fetch(config.previewUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": config.csrfToken,
            },
            body: JSON.stringify({
                delivery_order_id: payload.delivery_order_id,
                box_ids: payload.box_ids,
                pallet_ids: payload.pallet_ids,
            }),
        });
        const data = await response.json().catch(() => ({
            message: "Pratinjau tidak tersedia atau sesi login berubah. Muat ulang halaman.",
        }));
        if (!response.ok) {
            throw new Error(data.message || "Gagal menyiapkan pratinjau assignment.");
        }

        const missingIds = Array.isArray(data.missing_box_ids) ? data.missing_box_ids : [];
        if (missingIds.length > 0) {
            const labels = missingIds.map((id) => selectedBoxes.get(Number(id))?.box_number || `ID ${id}`);
            throw new Error(`Box ${labels.join(", ")} tidak lagi tersedia. Hapus dari pilihan lalu cari ulang.`);
        }
        const emptyPalletIds = Array.isArray(data.empty_pallet_ids) ? data.empty_pallet_ids : [];
        if (emptyPalletIds.length > 0) {
            const labels = emptyPalletIds.map((id) => selectedPallets.get(Number(id))?.pallet_number || `ID ${id}`);
            throw new Error(`Pallet ${labels.join(", ")} tidak lagi memiliki box yang tersedia. Hapus dari pilihan lalu cari ulang.`);
        }

        const existingBoxes = Array.isArray(data.boxes) ? data.boxes : [];
        if (existingBoxes.length === 0 && payload.new_boxes.length === 0) {
            throw new Error("Tidak ada box tersedia dalam pilihan saat ini. Cari ulang stok sebelum assign.");
        }

        const orderLabel = deliveryOrderSelect?.selectedOptions?.[0]?.textContent?.trim() || `#${payload.delivery_order_id}`;
        const newBoxDestination = payload.new_boxes.length === 0
            ? ""
            : palletInfo.mode === "new"
                ? `Box baru akan dicatat sebagai stok masuk pada pallet bernomor otomatis di lokasi ${palletInfo.location_code}.`
                : `Box baru akan dicatat sebagai stok masuk pada ${selectedExistingPalletText?.value || "pallet existing terpilih"}.`;
        const boxRows = [
            ...existingBoxes.map((box) => ({ ...box, source: "Stok" })),
            ...payload.new_boxes.map((box) => ({ ...box, source: "Baru" })),
        ];
        const listHtml = boxRows.map((box) => `
            <li><span class="delivery-assign-confirm-source">${escapeHtml(box.source)}</span>
                <strong>${escapeHtml(box.box_number || "-")}</strong>
                <span>${escapeHtml(box.part_number || "-")}</span>
                <span>${escapeHtml(box.pcs_quantity ?? 0)} PCS</span></li>
        `).join("");
        const palletNames = Array.from(selectedPallets.values()).map((pallet) => escapeHtml(pallet.pallet_number || "-")).join(", ");
        const html = `
            <div class="delivery-assign-confirm-body">
                <p class="delivery-assign-confirm-order">${escapeHtml(orderLabel)}</p>
                <p>${existingBoxes.length} box stok dan ${payload.new_boxes.length} box baru akan diajukan untuk assign${palletNames ? `, termasuk dari pallet ${palletNames}` : ""}.</p>
                <p>Box stok yang lolos validasi akan dihubungkan ke order dan sesi picking pending. ${escapeHtml(newBoxDestination)} Stok belum ditarik; server akan memvalidasi ulang ketersediaan dan kebutuhan part saat diproses.</p>
                <div class="delivery-assign-confirm-list-label">Box yang terlibat (${boxRows.length})</div>
                <ul class="delivery-assign-confirm-list">${listHtml}</ul>
                <p class="delivery-assign-confirm-note">Jika jumlah melebihi sisa request, akan muncul konfirmasi tambahan sebelum quantity order dinaikkan.</p>
            </div>`;

        if (window.Swal && typeof window.Swal.fire === "function") {
            const result = await window.Swal.fire({
                title: "Periksa Assign Delivery",
                html,
                icon: "question",
                showCancelButton: true,
                confirmButtonText: "Assign box tersebut",
                cancelButtonText: "Kembali periksa",
                confirmButtonColor: "#0b7072",
                reverseButtons: true,
                focusCancel: true,
                width: "min(44rem, calc(100vw - 2rem))",
                customClass: { popup: "delivery-assign-confirm" },
            });
            return result.isConfirmed;
        }

        const fallbackList = boxRows.map((box) => `${box.source} ${box.box_number} · ${box.part_number} · ${box.pcs_quantity} PCS`).join("\n");
        return window.confirm(`Assign Delivery ${orderLabel}\n\n${newBoxDestination}\n\nBox yang terlibat:\n${fallbackList}\n\nLanjutkan?`);
    }

    async function submitAssignment() {
        if (previewInFlight || assignmentInFlight) return;
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

        let palletInfo = null;
        if (scannedBoxes.size > 0) {
            palletInfo = validateNewBoxPalletInputs({ silent: true });
            if (!palletInfo) {
                showResult(
                    "warning",
                    "Untuk input box baru, pallet wajib diisi (pallet baru wajib pilih lokasi).",
                );
                return;
            }
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

        if (palletInfo) {
            payload.new_boxes_pallet_mode = palletInfo.mode;
            if (palletInfo.mode === "existing") {
                payload.new_boxes_pallet_id = palletInfo.pallet_id;
            } else {
                payload.new_boxes_location_id = palletInfo.location_id;
            }
        }

        previewInFlight = true;
        assignBtn.disabled = true;
        assignBtn.textContent = "Menyiapkan ringkasan...";
        try {
            if (await previewAssignment(payload, palletInfo)) {
                executeAssignment(payload);
            }
        } catch (error) {
            showResult("danger", error.message);
        } finally {
            previewInFlight = false;
            if (!assignmentInFlight) {
                assignBtn.disabled = false;
                assignBtn.textContent = "Assign Delivery";
            }
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
        clearBtn.addEventListener("click", () => {
            if (!hasSelections()) {
                showResult("info", "Belum ada box atau pallet dalam daftar pilihan.");
                return;
            }
            confirmChoice({
                title: "Reset semua pilihan?",
                message: `${selectionOrder.length} pilihan akan dikeluarkan dari draft di tab ini. Data stok dan delivery order tidak berubah.`,
                confirmText: "Reset pilihan",
                danger: true,
            }, () => clearSelection());
        });
    }

    assignBtn.addEventListener("click", submitAssignment);

    if (addNewBoxBtn) {
        addNewBoxBtn.addEventListener("click", addNewBox);
    }

    if (palletModeNew) {
        palletModeNew.addEventListener("change", function () {
            if (this.checked) {
                setNewBoxPalletModeUI("new");
                persistDraft();
            }
        });
    }

    if (palletModeExisting) {
        palletModeExisting.addEventListener("change", function () {
            if (this.checked) {
                setNewBoxPalletModeUI("existing");
                persistDraft();
            }
        });
    }

    if (existingPalletSearchInput) {
        let existingPalletSearchTimeout;

        existingPalletSearchInput.addEventListener("input", function (e) {
            window.clearTimeout(existingPalletSearchTimeout);
            const query = String(e.target.value || "").trim();

            if (selectedExistingPalletId) {
                selectedExistingPalletId.value = "";
            }
            if (selectedExistingPalletText) {
                selectedExistingPalletText.value = "";
            }
            persistDraft();

            existingPalletSearchTimeout = window.setTimeout(() => {
                searchExistingPallets(query);
            }, 250);
        });

        existingPalletSearchInput.addEventListener("focus", function () {
            searchExistingPallets(String(this.value || "").trim());
        });

        document.addEventListener("click", function (e) {
            if (
                existingPalletSearchResults &&
                !existingPalletSearchInput.contains(e.target) &&
                !existingPalletSearchResults.contains(e.target)
            ) {
                existingPalletSearchResults.style.display = "none";
            }
        });
    }

    if (locationSearchInput) {
        let locationSearchTimeout;

        locationSearchInput.addEventListener("input", function (e) {
            window.clearTimeout(locationSearchTimeout);
            const query = String(e.target.value || "").trim();

            if (selectedLocationId) {
                selectedLocationId.value = "";
            }
            if (selectedLocationCode) {
                selectedLocationCode.value = "";
            }
            persistDraft();

            locationSearchTimeout = window.setTimeout(() => {
                performLocationSearch(query);
            }, 250);
        });

        locationSearchInput.addEventListener("focus", function () {
            performLocationSearch(String(this.value || "").trim());
        });
    }

    if (locationDropdownBtn && locationSearchInput) {
        locationDropdownBtn.addEventListener("click", function () {
            locationSearchInput.focus();
            performLocationSearch(
                String(locationSearchInput.value || "").trim(),
            );
        });
    }

    if (locationSearchResults && locationSearchInput) {
        document.addEventListener("click", function (e) {
            if (
                !locationSearchInput.contains(e.target) &&
                !locationSearchResults.contains(e.target) &&
                (!locationDropdownBtn ||
                    !locationDropdownBtn.contains(e.target))
            ) {
                locationSearchResults.style.display = "none";
            }
        });
    }

    [newBoxNumberInput, newBoxPartSelect, newBoxQtyInput]
        .filter(Boolean)
        .forEach((input) => {
            input.addEventListener("keydown", (event) => {
                if (event.key === "Enter") {
                    event.preventDefault();
                    addNewBox();
                }
            });
        });

    if (deliveryOrderSelect) {
        deliveryOrderSelect.addEventListener("change", () => {
            const nextValue = deliveryOrderSelect.value || "";
            const previousValue = gateState.lastDeliveryOrderId || "";

            if (nextValue === previousValue) {
                return;
            }

            const applyChange = () => {
                clearSelection({ skipSearch: true, silent: true });
                gateState.lastDeliveryOrderId = nextValue;
                setGateState(Boolean(nextValue));
                if (!nextValue) {
                    renderDeliveryOrderParts([]);
                    return Promise.resolve(null);
                }

                return loadDeliveryOrderParts(nextValue)
                    .then(() => performSearch())
                    .catch((error) => {
                        showResult("danger", error.message);
                        showGatePlaceholders();
                        return null;
                    });
            };

            if (hasSelections()) {
                confirmDeliveryOrderChange(applyChange, () => {
                    deliveryOrderSelect.value = previousValue;
                });
                return;
            }

            applyChange().catch(() => null);
        });
    }

    if (newBoxPartSelect && newBoxQtyInput) {
        newBoxPartSelect.addEventListener("change", () => {
            const partInfo = deliveryOrderParts.get(
                String(newBoxPartSelect.value || ""),
            );
            const fixedQuantity = Number(partInfo?.qty_box || 0);
            newBoxQtyInput.value =
                Number.isFinite(fixedQuantity) && fixedQuantity > 0
                    ? String(fixedQuantity)
                    : "";
            newBoxQtyInput.readOnly = fixedQuantity > 0;
        });
    }

    const restoredDraft = restoreDraft(readDraft());
    if (!restoredDraft) {
        setNewBoxPalletModeUI(getNewBoxPalletMode());
    }
    draftReady = true;
    setGateState(Boolean(deliveryOrderSelect?.value));
    if (deliveryOrderSelect?.value) {
        loadDeliveryOrderParts(deliveryOrderSelect.value)
            .then(() => restoredDraft && performSearch())
            .then(() => {
                if (restoredDraft && hasSelections()) {
                    showResult("info", "Draft pilihan dipulihkan dari tab ini. Ketersediaan stok akan divalidasi ulang saat assign.");
                }
            })
            .catch((error) => showResult("danger", error.message));
    } else {
        renderDeliveryOrderParts([]);
    }
    updateSummary();
    renderSelectedList();
})();
