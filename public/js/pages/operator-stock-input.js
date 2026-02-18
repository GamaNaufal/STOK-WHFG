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

    if (!barcodeInput || !partInput || !scanForm) {
        return;
    }

    let currentPalletId = null;
    let lastScannedCode = null;
    let lastScanTime = 0;

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
                    document.getElementById(
                        "display_pallet_number",
                    ).textContent = pallet.pallet_number;
                    document.getElementById("box_count").textContent =
                        pallet.total_boxes;

                    const itemsList = document.getElementById("itemsList");
                    itemsList.innerHTML = "";

                    if (pallet.boxes && pallet.boxes.length > 0) {
                        pallet.boxes.forEach((box) => {
                            const boxRow = document.createElement("div");
                            boxRow.className = "row mb-2 p-2 bg-light rounded";
                            boxRow.innerHTML = `
                                <div class="col-md-3">
                                    <small class="text-muted">Box</small>
                                    <div class="fw-bold">${box.box_number}</div>
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
        barcodeInput.focus();
    });

    scanForm.addEventListener("submit", function (e) {
        e.preventDefault();
        if (!barcodeInput.disabled) {
            const barcode = barcodeInput.value.trim();
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
            const barcode = barcodeInput.value.trim();
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
            const barcode = this.value.trim();
            if (barcode) {
                scanBarcodeHardware(barcode);
                this.value = "";
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
                const barcode = barcodeInput.value.trim();
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

    document.getElementById("save-btn").addEventListener("click", function () {
        const selectedId = document.getElementById("selectedLocationId").value;
        const selectedCode = document.getElementById(
            "selectedLocationCode",
        ).value;

        if (!selectedId || !selectedCode) {
            showError(
                "Pilih lokasi dari list yang tersedia. Lokasi tidak boleh diketik manual.",
            );
            return;
        }

        const form = new FormData();
        form.append("pallet_id", currentPalletId);
        form.append("location_id", selectedId);
        form.append("warehouse_location", selectedCode);
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
                showToast(
                    "Stok berhasil disimpan di lokasi: " + selectedCode,
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
                    step2.style.display = "block";
                    step3.style.display = "block";
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
