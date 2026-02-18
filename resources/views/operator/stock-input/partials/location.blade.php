<!-- STEP 3: Input Lokasi -->
<div id="step-3" style="display: none;">
    <div class="card" style="border: 2px solid #e5e7eb; border-radius: 12px; overflow: hidden;">
        <div class="card-header" style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%);
                                        color: white;
                                        border: none;
                                        padding: 20px;
                                        font-weight: 600;
                                        font-size: 15px;">
            <i class="bi bi-pin-map"></i> Step 2: Tentukan Lokasi Penyimpanan
        </div>
        <div class="card-body" style="padding: 24px;">
            <label class="form-label fw-bold" style="font-size: 15px; color: #333; margin-bottom: 12px;">
                <i class="bi bi-geo-alt" style="color: #0C7779;"></i> Lokasi Penyimpanan
            </label>

            <!-- Searchable Location Input -->
            <div class="position-relative">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0" style="border: 2px solid #e5e7eb; border-right: none; border-radius: 10px 0 0 10px;"><i class="bi bi-search"></i></span>
                    <input type="text" id="locationSearchInput" class="form-control form-control-lg border-start-0 border-end-0"
                           placeholder="Pilih atau ketik kode lokasi..." autocomplete="off"
                           style="border-top: 2px solid #e5e7eb; border-bottom: 2px solid #e5e7eb; padding: 12px 16px; font-size: 16px;">
                    <button class="btn btn-outline-secondary border-start-0" type="button" id="locationDropdownBtn"
                            style="border-color: #e5e7eb; border-top: 2px solid #e5e7eb; border-bottom: 2px solid #e5e7eb; border-right: 2px solid #e5e7eb; border-radius: 0 10px 10px 0;">
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <input type="hidden" id="selectedLocationId" name="location_id">
                    <input type="hidden" id="selectedLocationCode" name="warehouse_location">
                </div>

                <!-- Dropdown Results -->
                <div id="locationSearchResults" class="list-group position-absolute w-100 shadow mt-1" style="z-index: 1000; display: none; max-height: 200px; overflow-y: auto;">
                    <!-- Items will be populated via JS -->
                </div>
            </div>

            <small class="text-muted d-block mt-3" style="font-size: 14px;" id="locationStatusText">
                <i class="bi bi-info-circle"></i> Pilih lokasi kosong dari dropdown. Tidak boleh menaruh di lokasi yang sudah ada palet lain.
            </small>

            <!-- Action Buttons -->
            <div class="d-grid gap-3 d-md-flex justify-content-md-end mt-4">
                <button type="button" class="btn btn-lg" id="cancel-btn"
                        style="background: #6b7280;
                               color: white;
                               border: none;
                               padding: 14px 28px;
                               border-radius: 10px;
                               font-weight: 600;
                               font-size: 15px;
                               transition: all 0.3s ease;">
                    <i class="bi bi-x-circle"></i> Batal
                </button>
                <button type="button" class="btn btn-lg" id="save-btn"
                        style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%);
                               color: white;
                               border: none;
                               padding: 14px 28px;
                               border-radius: 10px;
                               font-weight: 600;
                               font-size: 15px;
                               transition: all 0.3s ease;
                               box-shadow: 0 4px 12px rgba(12, 119, 121, 0.2);">
                    <i class="bi bi-check-circle"></i> Simpan Stok
                </button>
            </div>
        </div>
    </div>
</div>
