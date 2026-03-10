(function () {
    const config = window.stockInputConfig || {};

    const barcodeInput = document.getElementById("barcodeInput");
    const partInput = document.getElementById("partInput");
    const scanForm = document.getElementById("scanForm");
    const barcodeSubmitBtn = document.getElementById("barcodeSubmitBtn");
    const partSubmitBtn = document.getElementById("partSubmitBtn");
    const infoBox = document.getElementById("info-box");
    const infoText = document.getElementById("info-text");
    const errorMessage = document.getElementById("error-message");
    const errorText = document.getElementById("error-text");
    const step2 = document.getElementById("step-2");
    const step3 = document.getElementById("step-3");
    const palletModeNew = document.getElementById("palletModeNew");
    const palletModeExisting = document.getElementById("palletModeExisting");
    const existingPalletPicker = document.getElementById(
        "existingPalletPicker",
    );
    const existingPalletSearchInput = document.getElementById(
        "existingPalletSearchInput",
    );
    const existingPalletSearchResults = document.getElementById(
        "existingPalletSearchResults",
    );
    const selectedExistingPalletId = document.getElementById(
        "selectedExistingPalletId",
    );
    const selectedExistingPalletText = document.getElementById(
        "selectedExistingPalletText",
    );
    const printPalletBtn = document.getElementById("print-pallet-btn");

    if (!barcodeInput || !partInput || !scanForm) {
        return;
    }

    let currentPalletId = null;
    let currentPalletHasStoredLocation = false;
    let currentPalletLocationCode = null;
    let currentPalletSource = "new";
    let lastScannedCode = null;
    let lastScanTime = 0;
    let existingPalletSearchTimeout;
    let isSelectingExistingPallet = false;

    function normalizeBarcodeInput(rawValue) {
        return String(rawValue || "")
            .replace(/[\u0000-\u001F\u007F\s]+/g, "")
            .trim();
    }

    function validateBarcodeInput(rawValue) {
        const barcode = normalizeBarcodeInput(rawValue);

        if (!barcode) {
            return null;
        }

        if (!/^\d+$/.test(barcode)) {
            const message = "Input ID Box tidak boleh karakter, harus angka.";
            showBarcodeError(message);
            barcodeInput.value = "";
            barcodeInput.focus();
            return null;
        }

        return barcode;
    }

    function showPostSaveBannerIfAny() {
        const popup = document.getElementById("postSavePopup");
        const popupText = document.getElementById("postSavePopupText");
        if (!popup || !popupText) {
            return;
        }

        const message = sessionStorage.getItem("stockInputPostSaveMessage");
        if (!message) {
            return;
        }

        popupText.textContent = message;
        popup.style.display = "block";
        sessionStorage.removeItem("stockInputPostSaveMessage");

        setTimeout(() => {
            popup.style.display = "none";
        }, 1800);
    }

    function setPalletModeUI(mode) {
        if (existingPalletPicker) {
            existingPalletPicker.style.display =
                mode === "existing" ? "block" : "none";
        }

        const locationPickerSection = document.getElementById(
            "locationPickerSection",
        );
        const locationSearchInput = document.getElementById(
            "locationSearchInput",
        );
        const selectedLocationId =
            document.getElementById("selectedLocationId");
        const selectedLocationCode = document.getElementById(
            "selectedLocationCode",
        );
        const locationStatusText =
            document.getElementById("locationStatusText");

        if (mode === "existing") {
            if (locationPickerSection) {
                locationPickerSection.style.display = "none";
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
            if (locationStatusText) {
                locationStatusText.innerHTML =
                    '<i class="bi bi-info-circle"></i> Mode pallet existing aktif. Lokasi akan mengikuti lokasi pallet yang dipilih.';
            }
            return;
        }

        if (locationPickerSection) {
            locationPickerSection.style.display = "block";
        }
        if (locationStatusText) {
            locationStatusText.innerHTML =
                '<i class="bi bi-info-circle"></i> Untuk pallet baru: pilih lokasi kosong dari dropdown. Untuk pallet existing: lokasi boleh dikosongkan (akan pakai lokasi pallet saat ini).';
        }
    }

    function showBarcodeStatus(text) {
        const statusEl = document.getElementById("barcode-status");
        document.getElementById("barcode-status-text").textContent = text;
        statusEl.style.display = "block";
        document.getElementById("barcode-error").style.display = "none";
    }

    function showBarcodeError(text) {
        const errorEl = document.getElementById("barcode-error");
        document.getElementById("barcode-error-text").textContent = text;
        errorEl.style.display = "block";
        document.getElementById("barcode-status").style.display = "none";
    }

    function showPartStatus(text) {
        const statusEl = document.getElementById("part-status");
        document.getElementById("part-status-text").textContent = text;
        statusEl.style.display = "block";
        document.getElementById("part-error").style.display = "none";
    }

    function showPartError(text) {
        const errorEl = document.getElementById("part-error");
        document.getElementById("part-error-text").textContent = text;
        errorEl.style.display = "block";
        document.getElementById("part-status").style.display = "none";
    }

    function showError(message) {
        errorText.textContent = message;
        errorMessage.style.display = "block";
        infoBox.style.display = "none";
    }

    function showInfo(message) {
        infoText.textContent = message;
        infoBox.style.display = "block";
        errorMessage.style.display = "none";
    }

    function escapeHtml(input) {
        return String(input ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function renderAndPrintPalletInfo(pallet) {
        const boxes = Array.isArray(pallet?.boxes_for_print)
            ? pallet.boxes_for_print
            : Array.isArray(pallet?.boxes)
              ? pallet.boxes
              : [];
        const totalBoxesCombined = Number(
            pallet?.total_boxes_combined ?? boxes.length,
        );
        const totalBoxesExisting = Number(pallet?.total_boxes_existing ?? 0);
        const totalBoxesPending = Number(pallet?.total_boxes_pending ?? 0);
        const rowsHtml = boxes.length
            ? boxes
                  .map(
                      (box) => `
                <tr>
                    <td>${escapeHtml(box.box_number || "-")}</td>
                    <td>${escapeHtml(box.part_number || "-")}</td>
                    <td>${escapeHtml(box.pcs_quantity ?? "-")}</td>
                    <td>${escapeHtml(box.qty_box ?? "-")}${box.is_not_full ? " (Not Full)" : ""}</td>
                </tr>
            `,
                  )
                  .join("")
            : '<tr><td colspan="4" style="text-align:center;">Belum ada box yang diinput.</td></tr>';

        const printWindow = window.open("", "_blank");
        if (!printWindow) {
            showError("Popup diblokir browser. Izinkan popup untuk mencetak.");
            return;
        }

        const printedAt = new Date().toLocaleString("id-ID");
        const documentHtml = `
            <!doctype html>
            <html>
            <head>
                <meta charset="utf-8" />
                <title>Keterangan Pallet ${escapeHtml(pallet?.pallet_number || "")}</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 24px; color: #111827; }
                    .company-letterhead {
                        display: flex;
                        align-items: center;
                        gap: 16px;
                        border-bottom: 2px solid #111827;
                        padding-bottom: 10px;
                        margin-bottom: 12px;
                    }
                    .letterhead-logo img { width: 82px; max-height: 82px; object-fit: contain; }
                    .letterhead-info { flex: 1; text-align: center; line-height: 1.35; font-size: 12px; color: #111827; }
                    .letterhead-info .company-name { font-size: 24px; font-weight: 700; letter-spacing: 0.3px; }
                    h1 { margin: 0 0 14px 0; font-size: 20px; }
                    .meta { margin-bottom: 14px; line-height: 1.7; }
                    .meta strong { display: inline-block; min-width: 130px; }
                    table { width: 100%; border-collapse: collapse; font-size: 12px; }
                    th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; }
                    thead th { background: #f3f4f6; }
                    .footer { margin-top: 12px; color: #6b7280; font-size: 11px; }
                </style>
            </head>
            <body>
                <div class="company-letterhead">
                    <div class="letterhead-logo">
                        <img src="${window.location.origin}/logo.png" alt="Company Logo" />
                    </div>
                    <div class="letterhead-info">
                        <div class="company-name">PT. YAMATOGOMU INDONESIA</div>
                        <div>Kawasan Industri Indotaisei</div>
                        <div>Blok K - 6, Cikampek</div>
                        <div>Jawa Barat - Indonesia 41373</div>
                        <div>Phone : 0264 - 351216, 351217&nbsp;&nbsp;Fax : 0264 - 351137</div>
                    </div>
                </div>
                <h1>Keterangan Pallet</h1>
                <div class="meta">
                    <div><strong>No Pallet</strong>: ${escapeHtml(pallet?.pallet_number || "-")}</div>
                    <div><strong>Lokasi</strong>: ${escapeHtml(pallet?.warehouse_location || "-")}</div>
                    <div><strong>Total Box (Gabungan)</strong>: ${escapeHtml(totalBoxesCombined)}</div>
                    <div><strong>Box Existing</strong>: ${escapeHtml(totalBoxesExisting)}</div>
                    <div><strong>Box Tambahan (Pending)</strong>: ${escapeHtml(totalBoxesPending)}</div>
                    <div><strong>Sumber Pallet</strong>: ${escapeHtml(pallet?.source || "new")}</div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>No Box</th>
                            <th>No Part</th>
                            <th>PCS</th>
                            <th>Qty Box</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsHtml}
                    </tbody>
                </table>
                <div class="footer">Dicetak pada: ${escapeHtml(printedAt)}</div>
                <script>
                    window.onload = function() { window.print(); };
                <\/script>
            </body>
            </html>
        `;

        printWindow.document.open();
        printWindow.document.write(documentHtml);
        printWindow.document.close();
    }

    function printCurrentPalletInfo() {
        if (!currentPalletId) {
            showError("Belum ada pallet aktif untuk dicetak.");
            return;
        }

        fetch(config.getPalletDataUrl, {
            headers: {
                "X-CSRF-TOKEN": config.csrfToken,
            },
        })
            .then((response) => response.json())
            .then((data) => {
                if (!data.success || !data.pallet) {
                    throw new Error(
                        data.message || "Data pallet tidak ditemukan.",
                    );
                }
                renderAndPrintPalletInfo(data.pallet);
            })
            .catch((error) => {
                showError("Gagal menyiapkan data print: " + error.message);
            });
    }

    const showConfirm = ({ title, message, confirmText, onConfirm }) => {
        WarehouseAlert.confirm({
            title: title,
            message: message,
            confirmText: confirmText,
            confirmColor: "#0C7779",
            onConfirm: onConfirm,
        });
    };

    function loadAndDisplayPalletData() {
        $.ajax({
            url: config.getPalletDataUrl,
            method: "GET",
            headers: {
                "X-CSRF-TOKEN": config.csrfToken,
            },
            success: function (data) {
                if (data.success) {
                    const pallet = data.pallet;
                    currentPalletId = pallet.id;
                    currentPalletHasStoredLocation =
                        !!pallet.has_stock_location;
                    currentPalletLocationCode =
                        pallet.warehouse_location || null;
                    currentPalletSource = pallet.source || "new";
                    document.getElementById(
                        "display_pallet_number",
                    ).textContent = pallet.pallet_number;
                    document.getElementById("box_count").textContent =
                        pallet.total_boxes;

                    const palletSaveStatus =
                        document.getElementById("palletSaveStatus");
                    const boxInputStatus =
                        document.getElementById("boxInputStatus");

                    if (palletSaveStatus) {
                        if (pallet.has_stock_location) {
                            palletSaveStatus.style.background = "#ecfdf5";
                            palletSaveStatus.style.border = "2px solid #86efac";
                            palletSaveStatus.style.color = "#166534";
                            palletSaveStatus.innerHTML = `<i class="bi bi-check-circle"></i> Status pallet: <strong>Sudah tersimpan</strong> di lokasi <strong>${pallet.warehouse_location || "-"}</strong>`;
                        } else {
                            palletSaveStatus.style.background = "#fff7ed";
                            palletSaveStatus.style.border = "2px solid #fdba74";
                            palletSaveStatus.style.color = "#9a3412";
                            palletSaveStatus.innerHTML =
                                '<i class="bi bi-clock-history"></i> Status pallet: <strong>Belum tersimpan</strong>';
                        }
                    }

                    const itemsList = document.getElementById("itemsList");
                    itemsList.innerHTML = "";

                    if (pallet.boxes && pallet.boxes.length > 0) {
                        if (boxInputStatus) {
                            boxInputStatus.style.background = "#ecfdf5";
                            boxInputStatus.style.border = "2px solid #86efac";
                            boxInputStatus.style.color = "#166534";
                            boxInputStatus.innerHTML = `<i class="bi bi-check-circle"></i> <strong>${pallet.boxes.length} box sudah diinput</strong> ke pallet ini.`;
                        }

                        pallet.boxes.forEach((box) => {
                            const boxRow = document.createElement("div");
                            boxRow.className = "row mb-2 p-2 bg-light rounded";
                            boxRow.innerHTML = `
                                <div class="col-md-3">
                                    <small class="text-muted">Box</small>
                                    <div class="fw-bold">${box.box_number}</div>
                                    <span class="badge bg-success mt-1">Sudah diinput</span>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Part</small>
                                    <div class="fw-bold">${box.part_number}</div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">PCS</small>
                                    <div class="fw-bold">${box.pcs_quantity}</div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Qty Box</small>
                                    <div class="fw-bold">${box.qty_box ?? "-"} ${box.is_not_full ? '<span class="badge bg-warning text-dark ms-1">Not Full</span>' : ""}</div>
                                </div>
                            `;
                            itemsList.appendChild(boxRow);
                        });
                    } else if (boxInputStatus) {
                        boxInputStatus.style.background = "#ecfeff";
                        boxInputStatus.style.border = "2px solid #67e8f9";
                        boxInputStatus.style.color = "#155e75";
                        boxInputStatus.innerHTML =
                            '<i class="bi bi-info-circle"></i> Belum ada box yang diinput.';
                    }
                }
            },
            error: function (xhr) {
                console.error("Error loading pallet data:", xhr);
            },
        });
    }

    function scanBarcodeHardware(barcode) {
        showBarcodeStatus("Memproses: " + barcode);

        $.ajax({
            url: config.scanBarcodeUrl,
            method: "POST",
            data: {
                _token: config.csrfToken,
                barcode: barcode,
            },
            success: function (response) {
                if (response.success) {
                    currentPalletId = response.pallet_id;
                    currentPalletHasStoredLocation =
                        !!response.has_stock_location;
                    currentPalletLocationCode =
                        response.warehouse_location || null;
                    showBarcodeStatus(
                        "✓ Box: " +
                            response.box_number +
                            " | Lanjut scan No Part",
                    );

                    barcodeInput.disabled = true;
                    partInput.disabled = false;
                    if (barcodeSubmitBtn) barcodeSubmitBtn.disabled = true;
                    if (partSubmitBtn) partSubmitBtn.disabled = false;

                    document.getElementById("step-2").style.display = "block";
                    document.getElementById("step-3").style.display = "block";
                    setTimeout(() => partInput.focus(), 300);
                } else {
                    showBarcodeError(response.message);
                    barcodeInput.disabled = false;
                    partInput.disabled = true;
                    if (barcodeSubmitBtn) barcodeSubmitBtn.disabled = false;
                    if (partSubmitBtn) partSubmitBtn.disabled = true;
                    setTimeout(() => barcodeInput.focus(), 300);
                }
            },
            error: function (xhr) {
                let msg = "Terjadi kesalahan";
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                showBarcodeError(msg);
                barcodeInput.disabled = false;
                partInput.disabled = true;
                if (barcodeSubmitBtn) barcodeSubmitBtn.disabled = false;
                if (partSubmitBtn) partSubmitBtn.disabled = true;
                setTimeout(() => barcodeInput.focus(), 300);
            },
        });
    }

    function scanPartNumber(partNumber) {
        showPartStatus("Memproses: " + partNumber);

        $.ajax({
            url: config.scanPartUrl,
            method: "POST",
            data: {
                _token: config.csrfToken,
                part_number: partNumber,
            },
            success: function (response) {
                if (response.success) {
                    currentPalletHasStoredLocation =
                        !!response.has_stock_location;
                    currentPalletLocationCode =
                        response.warehouse_location || null;
                    showPartStatus("✓ Part sesuai: " + response.part_number);
                    loadAndDisplayPalletData();

                    partInput.disabled = true;
                    barcodeInput.disabled = false;
                    if (partSubmitBtn) partSubmitBtn.disabled = true;
                    if (barcodeSubmitBtn) barcodeSubmitBtn.disabled = false;
                    setTimeout(() => barcodeInput.focus(), 300);
                } else {
                    showPartError(response.message);
                    setTimeout(() => partInput.focus(), 300);
                }
            },
            error: function (xhr) {
                let msg = "Terjadi kesalahan";
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                showPartError(msg);
                setTimeout(() => partInput.focus(), 300);
            },
        });
    }

    document.addEventListener("DOMContentLoaded", function () {
        showPostSaveBannerIfAny();
        setPalletModeUI("new");
        barcodeInput.focus();
    });

    function searchExistingPallet(query) {
        if (!existingPalletSearchResults || !config.searchExistingPalletUrl) {
            return;
        }

        existingPalletSearchResults.style.display = "block";
        existingPalletSearchResults.innerHTML =
            '<div class="list-group-item text-muted">Mencari pallet...</div>';

        fetch(
            config.searchExistingPalletUrl +
                "?q=" +
                encodeURIComponent(query || ""),
            {
                headers: {
                    "X-CSRF-TOKEN": config.csrfToken,
                },
            },
        )
            .then((response) => response.json())
            .then((rows) => {
                existingPalletSearchResults.innerHTML = "";

                if (!rows || rows.length === 0) {
                    existingPalletSearchResults.innerHTML =
                        '<div class="list-group-item text-muted">Pallet existing tidak ditemukan.</div>';
                    return;
                }

                rows.forEach((row) => {
                    const item = document.createElement("a");
                    item.href = "#";
                    item.className = "list-group-item list-group-item-action";
                    item.innerHTML = `<div class="d-flex justify-content-between align-items-center">
                                        <strong>${row.pallet_number}</strong>
                                        <span class="badge bg-secondary">${row.total_boxes} box</span>
                                      </div>
                                      <small class="text-muted">Lokasi: ${row.warehouse_location || "-"}</small>`;
                    item.onclick = (e) => {
                        e.preventDefault();
                        selectedExistingPalletId.value = row.id;
                        selectedExistingPalletText.value = row.pallet_number;
                        existingPalletSearchInput.value = `${row.pallet_number} (${row.warehouse_location || "-"})`;
                        existingPalletSearchResults.style.display = "none";
                        selectExistingPallet(row.id);
                    };
                    existingPalletSearchResults.appendChild(item);
                });
            })
            .catch(() => {
                existingPalletSearchResults.style.display = "none";
            });
    }

    function selectExistingPallet(palletId = null) {
        const targetPalletId = palletId || selectedExistingPalletId?.value;

        if (!targetPalletId) {
            showError("Pilih pallet existing terlebih dahulu.");
            return;
        }

        if (isSelectingExistingPallet) {
            return;
        }

        if (
            currentPalletSource === "existing" &&
            Number(currentPalletId) === Number(targetPalletId)
        ) {
            return;
        }

        isSelectingExistingPallet = true;
        if (existingPalletSearchInput) {
            existingPalletSearchInput.disabled = true;
        }

        const form = new FormData();
        form.append("pallet_id", targetPalletId);
        form.append("_token", config.csrfToken);

        fetch(config.selectExistingPalletUrl, {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": config.csrfToken,
            },
            body: form,
        })
            .then((response) => response.json())
            .then((data) => {
                if (!data.success) {
                    throw new Error(
                        data.message || "Gagal memilih pallet existing",
                    );
                }

                currentPalletId = data.pallet_id;
                currentPalletSource = "existing";
                currentPalletHasStoredLocation = true;
                currentPalletLocationCode = data.warehouse_location || null;
                step2.style.display = "block";
                step3.style.display = "block";
                loadAndDisplayPalletData();

                showInfo(
                    `Pallet existing ${data.pallet_number} dipilih. Lokasi aktif: ${data.warehouse_location || "-"}.`,
                );
            })
            .catch((error) => {
                showError(error.message || "Gagal memilih pallet existing.");
            })
            .finally(() => {
                isSelectingExistingPallet = false;
                if (existingPalletSearchInput) {
                    existingPalletSearchInput.disabled = false;
                }
            });
    }

    scanForm.addEventListener("submit", function (e) {
        e.preventDefault();
        if (!barcodeInput.disabled) {
            const barcode = validateBarcodeInput(barcodeInput.value);
            if (barcode) {
                scanBarcodeHardware(barcode);
                barcodeInput.value = "";
            }
            return;
        }

        if (!partInput.disabled) {
            const partNumber = partInput.value.trim();
            if (partNumber) {
                scanPartNumber(partNumber);
                partInput.value = "";
            }
        }
    });

    if (barcodeSubmitBtn) {
        barcodeSubmitBtn.addEventListener("click", function () {
            if (barcodeInput.disabled) return;
            const barcode = validateBarcodeInput(barcodeInput.value);
            if (barcode) {
                scanBarcodeHardware(barcode);
                barcodeInput.value = "";
            }
        });
    }

    if (partSubmitBtn) {
        partSubmitBtn.addEventListener("click", function () {
            if (partInput.disabled) return;
            const partNumber = partInput.value.trim();
            if (partNumber) {
                scanPartNumber(partNumber);
                partInput.value = "";
            }
        });
    }

    barcodeInput.addEventListener("keydown", function (e) {
        if (e.key === "Enter" || e.keyCode === 13) {
            e.preventDefault();
            const barcode = validateBarcodeInput(this.value);
            if (barcode) {
                scanBarcodeHardware(barcode);
                this.value = "";
            }
        }
    });

    barcodeInput.addEventListener("input", function () {
        const currentValue = normalizeBarcodeInput(this.value);

        if (!currentValue) {
            const errorEl = document.getElementById("barcode-error");
            if (errorEl) {
                errorEl.style.display = "none";
            }
            return;
        }

        if (currentValue.length > 0) {
            const errorEl = document.getElementById("barcode-error");
            if (errorEl) {
                errorEl.style.display = "none";
            }
        }
    });

    partInput.addEventListener("keydown", function (e) {
        if (e.key === "Enter" || e.keyCode === 13) {
            e.preventDefault();
            const partNumber = this.value.trim();
            if (partNumber) {
                scanPartNumber(partNumber);
                this.value = "";
            }
        }
    });

    document.addEventListener("keydown", function (e) {
        if (e.key === "Enter" || e.keyCode === 13) {
            const active = document.activeElement;
            if (active === barcodeInput && !barcodeInput.disabled) {
                e.preventDefault();
                const barcode = validateBarcodeInput(barcodeInput.value);
                if (barcode) {
                    scanBarcodeHardware(barcode);
                    barcodeInput.value = "";
                }
            }
            if (active === partInput && !partInput.disabled) {
                e.preventDefault();
                const partNumber = partInput.value.trim();
                if (partNumber) {
                    scanPartNumber(partNumber);
                    partInput.value = "";
                }
            }
        }
    });

    document
        .getElementById("clear-pallet-btn")
        .addEventListener("click", function () {
            showConfirm({
                title: "Mulai Palet Baru",
                message:
                    "Palet saat ini belum disimpan. Jika lanjut, data palet saat ini akan dihapus.",
                confirmText: "Mulai Baru",
                onConfirm: () => {
                    fetch(config.clearSessionUrl, {
                        method: "POST",
                        headers: {
                            "X-CSRF-TOKEN": config.csrfToken,
                        },
                    })
                        .then((response) => response.json())
                        .then(() => {
                            lastScannedCode = null;
                            lastScanTime = 0;
                            barcodeInput.value = "";
                            barcodeInput.focus();
                            location.reload();
                        });
                },
            });
        });

    document
        .getElementById("cancel-btn")
        .addEventListener("click", function () {
            showConfirm({
                title: "Batalkan Input",
                message: "Data palet saat ini akan hilang jika dibatalkan.",
                confirmText: "Batalkan",
                onConfirm: () => {
                    lastScannedCode = null;
                    lastScanTime = 0;

                    fetch(config.clearSessionUrl, {
                        method: "POST",
                        headers: {
                            "X-CSRF-TOKEN": config.csrfToken,
                        },
                    }).then(() => location.reload());
                },
            });
        });

    if (printPalletBtn) {
        printPalletBtn.addEventListener("click", function () {
            printCurrentPalletInfo();
        });
    }

    document.getElementById("save-btn").addEventListener("click", function () {
        if (
            palletModeExisting &&
            palletModeExisting.checked &&
            !currentPalletHasStoredLocation
        ) {
            showError("Pilih pallet existing dari daftar terlebih dahulu.");
            return;
        }

        const selectedId = document.getElementById("selectedLocationId").value;
        const selectedCode = document.getElementById(
            "selectedLocationCode",
        ).value;
        const useExistingLocation =
            currentPalletHasStoredLocation && !selectedId && !selectedCode;

        if ((!selectedId || !selectedCode) && !useExistingLocation) {
            showError(
                "Pilih lokasi dari list yang tersedia. Lokasi tidak boleh diketik manual.",
            );
            return;
        }

        const form = new FormData();
        form.append("pallet_id", currentPalletId);
        if (selectedId && selectedCode) {
            form.append("location_id", selectedId);
            form.append("warehouse_location", selectedCode);
        }
        form.append("_token", config.csrfToken);

        fetch(config.storeUrl, {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": config.csrfToken,
            },
            body: form,
        })
            .then((response) => {
                if (!response.ok) {
                    return response.json().then((err) => {
                        throw new Error(err.message || "Error saving stock");
                    });
                }
                return response.json();
            })
            .then(() => {
                const savedLocation =
                    selectedCode ||
                    currentPalletLocationCode ||
                    "(tanpa lokasi)";
                sessionStorage.setItem(
                    "stockInputPostSaveMessage",
                    "Data pallet berhasil tersimpan di lokasi: " +
                        savedLocation,
                );
                showToast(
                    "Stok berhasil disimpan di lokasi: " + savedLocation,
                    "success",
                );
                window.location.href = config.indexUrl;
            })
            .catch((error) => {
                showError("Terjadi kesalahan saat menyimpan: " + error.message);
            });
    });

    const searchInput = document.getElementById("locationSearchInput");
    const searchResults = document.getElementById("locationSearchResults");
    const dropdownBtn = document.getElementById("locationDropdownBtn");
    const selectedLocationId = document.getElementById("selectedLocationId");
    const selectedLocationCode = document.getElementById(
        "selectedLocationCode",
    );
    let searchTimeout;

    function performSearch(query) {
        selectedLocationId.value = "";

        searchResults.style.display = "block";
        searchResults.innerHTML =
            '<div class="list-group-item text-muted">Mencari...</div>';

        fetch(config.locationSearchUrl + "?q=" + encodeURIComponent(query))
            .then((res) => res.json())
            .then((data) => {
                searchResults.innerHTML = "";
                if (data.length > 0) {
                    data.forEach((loc) => {
                        const item = document.createElement("a");
                        item.href = "#";
                        item.className =
                            "list-group-item list-group-item-action";
                        item.innerHTML = `<div class="d-flex justify-content-between align-items-center">
                                            <strong>${loc.code}</strong>
                                            <span class="badge bg-success rounded-pill" style="font-size: 0.7em;">Available</span>
                                          </div>`;
                        item.style.cursor = "pointer";
                        item.dataset.value = loc.code;
                        item.onclick = (e) => {
                            e.preventDefault();
                            searchInput.value = loc.code;
                            selectedLocationId.value = loc.id;
                            selectedLocationCode.value = loc.code;
                            searchResults.style.display = "none";
                        };
                        searchResults.appendChild(item);
                    });
                    searchResults.style.display = "block";
                } else {
                    if (query === "") {
                        searchResults.innerHTML =
                            '<div class="list-group-item text-muted">Tidak ada lokasi tersedia.</div>';
                    } else {
                        searchResults.innerHTML =
                            '<div class="list-group-item text-muted">Lokasi tidak ditemukan di Master. Gunakan sebagai lokasi baru?</div>';
                    }
                    searchResults.style.display = "block";
                }
            })
            .catch((err) => {
                console.error("Search error:", err);
                searchResults.style.display = "none";
            });
    }

    if (searchInput) {
        searchInput.addEventListener("input", function (e) {
            clearTimeout(searchTimeout);
            const query = e.target.value.trim();
            searchTimeout = setTimeout(() => {
                performSearch(query);
            }, 300);
        });

        searchInput.addEventListener("focus", function () {
            if (this.value.trim() === "") {
                performSearch("");
            } else {
                searchResults.style.display = "block";
            }
        });

        if (dropdownBtn) {
            dropdownBtn.addEventListener("click", function () {
                searchInput.focus();
                performSearch(searchInput.value.trim());
            });
        }

        document.addEventListener("click", function (e) {
            if (
                !searchInput.contains(e.target) &&
                !searchResults.contains(e.target) &&
                (!dropdownBtn || !dropdownBtn.contains(e.target))
            ) {
                searchResults.style.display = "none";
            }
        });
    }

    if (palletModeNew) {
        palletModeNew.addEventListener("change", function () {
            if (this.checked) {
                currentPalletSource = "new";
                setPalletModeUI("new");
            }
        });
    }

    if (palletModeExisting) {
        palletModeExisting.addEventListener("change", function () {
            if (this.checked) {
                currentPalletSource = "existing";
                setPalletModeUI("existing");
            }
        });
    }

    if (existingPalletSearchInput) {
        existingPalletSearchInput.addEventListener("input", function (e) {
            clearTimeout(existingPalletSearchTimeout);
            const query = e.target.value.trim();
            selectedExistingPalletId.value = "";
            selectedExistingPalletText.value = "";

            existingPalletSearchTimeout = setTimeout(() => {
                searchExistingPallet(query);
            }, 300);
        });

        existingPalletSearchInput.addEventListener("focus", function () {
            searchExistingPallet(this.value.trim());
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

    document.addEventListener("DOMContentLoaded", function () {
        fetch(config.getPalletDataUrl, {
            headers: {
                "X-CSRF-TOKEN": config.csrfToken,
            },
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    currentPalletId = data.pallet.id;
                    currentPalletHasStoredLocation =
                        !!data.pallet.has_stock_location;
                    currentPalletLocationCode =
                        data.pallet.warehouse_location || null;
                    currentPalletSource = data.pallet.source || "new";
                    step2.style.display = "block";
                    step3.style.display = "block";
                    if (
                        palletModeExisting &&
                        palletModeNew &&
                        currentPalletSource === "existing"
                    ) {
                        palletModeExisting.checked = true;
                        palletModeNew.checked = false;
                        setPalletModeUI("existing");
                    }
                    loadAndDisplayPalletData();
                    showInfo(
                        "Palet aktif ditemukan. Lanjutkan scan box atau tentukan lokasi.",
                    );
                }
            })
            .catch(() => {
                // No active pallet, ready for new scan
            });
    });
})();
